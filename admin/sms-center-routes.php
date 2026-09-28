<?php

declare(strict_types=1);

/**
 * SMS Center recipient builder + batch APIs.
 * Included from admin/crm-routes.php
 */

use Sarva\Core\Auth;
use Sarva\Core\Csrf;
use Sarva\Services\SmsAudienceService;
use Sarva\Services\SmsBatchService;
use Sarva\Services\SmsService;
use Sarva\Services\SmsTemplateRenderer;

/** @var \Sarva\Core\Router $router */

$audience = static fn (): SmsAudienceService => new SmsAudienceService(db());
$batches = static fn (): SmsBatchService => new SmsBatchService(db(), new SmsAudienceService(db()), new SmsService(db()));

$readJson = static function (): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    return array_merge($_GET, $_POST);
};

$requireCsrfJson = static function () use ($readJson): array {
    $data = $readJson();
    $token = (string) ($data['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
    if (!Csrf::validate($token)) {
        json_response(['ok' => false, 'message' => 'نشست منقضی شده است. صفحه را تازه کنید.'], 419);
    }
    return $data;
};

// ---------- Send page (3-step builder) ----------
$router->get('/admin/sms/send', static function () use ($audience): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    $templates = [];
    try {
        $templates = db()->query(
            "SELECT * FROM sms_templates WHERE is_active=1 AND deleted_at IS NULL
             ORDER BY is_favorite DESC, category ASC, name ASC"
        )->fetchAll() ?: [];
    } catch (\Throwable) {
        $templates = db()->query("SELECT * FROM sms_templates WHERE is_active=1 AND deleted_at IS NULL ORDER BY name")->fetchAll() ?: [];
    }
    $doctors = db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $services = db()->query('SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $saved = [];
    try {
        $saved = $audience()->listAudiences();
    } catch (\Throwable) {
        $saved = [];
    }
    $counts = [];
    try {
        $counts = $audience()->quickCounts();
    } catch (\Throwable) {
        $counts = [];
    }

    $preselect = array_values(array_filter(array_map('intval', (array) ($_GET['patient_ids'] ?? []))));
    $preset = (string) ($_GET['preset'] ?? $_GET['mode'] ?? '');
    if ($preset === 'selected' || $preselect) {
        $preset = $preselect ? 'selected' : $preset;
    }

    view('admin/sms-send', [
        'title' => 'ارسال پیامک',
        'templates' => $templates,
        'doctors' => $doctors,
        'services' => $services,
        'audiences' => $saved,
        'quickCounts' => $counts,
        'modeLabels' => SmsAudienceService::modeLabels(),
        'placeholders' => SmsTemplateRenderer::placeholders(),
        'preselectIds' => $preselect,
        'initialMode' => $preset !== '' ? $preset : ($preselect ? 'selected' : 'manual'),
        'csrf' => csrf_token(),
        'bulkLimit' => (int) setting('sms_bulk_limit', 500),
        'canBulk' => Auth::adminHasPermission('sms.send.bulk') || Auth::adminHasPermission('sms.send'),
    ]);
});

// ---------- JSON APIs ----------
$router->get('/admin/sms/api/patients', static function () use ($audience): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk', 'sms.automation.manage', 'sms.view', 'patients.view', 'patients.manage']);
    $filters = $audience()->normalizeFilters($_GET);
    if (!empty($_GET['mode'])) {
        $filters['mode'] = (string) $_GET['mode'];
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = max(10, min(50, (int) ($_GET['per_page'] ?? 25)));
    json_response(['ok' => true] + $audience()->searchPatients($filters, $page, $per));
});

$router->get('/admin/sms/api/counts', static function () use ($audience): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk', 'sms.view']);
    json_response(['ok' => true, 'counts' => $audience()->quickCounts()]);
});

$router->post('/admin/sms/api/summary', static function () use ($audience, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk', 'sms.automation.manage', 'sms.view']);
    $data = $requireCsrfJson();
    $summary = $audience()->summarize($data);
    json_response(['ok' => true, 'summary' => $summary]);
});

$router->post('/admin/sms/api/preview-recipients', static function () use ($audience, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk', 'sms.automation.manage', 'sms.view']);
    $data = $requireCsrfJson();
    $page = max(1, (int) ($data['page'] ?? 1));
    $q = trim((string) ($data['q'] ?? ''));
    $result = $audience()->previewRecipients($data, $page, 30, $q);
    json_response(['ok' => true] + $result);
});

$router->post('/admin/sms/api/preview-message', static function () use ($audience, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    $data = $requireCsrfJson();
    $tplId = (int) ($data['template_id'] ?? 0);
    $custom = trim((string) ($data['message'] ?? ''));
    $body = $custom;
    $tplName = 'پیام سفارشی';
    if ($tplId > 0) {
        $st = db()->prepare('SELECT * FROM sms_templates WHERE id=? AND deleted_at IS NULL');
        $st->execute([$tplId]);
        $tpl = $st->fetch();
        if ($tpl) {
            $body = (string) $tpl['body'];
            $tplName = (string) $tpl['name'];
        }
    }
    $recipients = $audience()->recipientsForSend($data, 1);
    $sample = $recipients[0] ?? [
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'file_number' => 'P1001',
        'mobile' => '09121234567',
        'starts_at' => date('Y-m-d 10:30:00', strtotime('+1 day')),
        'doctor_name' => 'دکتر نمونه',
        'service_name' => 'ویزیت',
    ];
    $rendered = SmsTemplateRenderer::render($body, SmsTemplateRenderer::varsFrom($sample, [
        'starts_at' => $sample['starts_at'] ?? null,
        'doctor_name' => $sample['doctor_name'] ?? '',
        'service_name' => $sample['service_name'] ?? '',
    ]));
    json_response([
        'ok' => true,
        'template' => $tplName,
        'body' => $body,
        'rendered' => $rendered,
        'chars' => mb_strlen($rendered),
        'segments' => SmsTemplateRenderer::segmentCount($rendered),
        'sample_name' => trim(($sample['first_name'] ?? '') . ' ' . ($sample['last_name'] ?? '')),
    ]);
});

$router->post('/admin/sms/api/send', static function () use ($batches, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    $data = $requireCsrfJson();
    $lastBulk = (int) ($_SESSION['sms_bulk_at'] ?? 0);
    if ($lastBulk > 0 && (time() - $lastBulk) < 15) {
        json_response(['ok' => false, 'message' => 'لطفاً چند ثانیه صبر کنید و دوباره ارسال نکنید.'], 429);
    }
    if (empty($data['confirm'])) {
        json_response(['ok' => false, 'message' => 'تأیید ارسال الزامی است.'], 422);
    }
    $finalCount = (int) ($data['expected_count'] ?? 0);
    if ($finalCount > 1 && !Auth::adminHasPermission('sms.send.bulk') && !Auth::adminHasPermission('sms.send')) {
        json_response(['ok' => false, 'message' => 'مجوز ارسال گروهی ندارید.'], 403);
    }
    $result = $batches()->createAndEnqueue($data, (int) (Auth::adminId() ?? 0));
    if (!empty($result['ok'])) {
        $_SESSION['sms_bulk_at'] = time();
    }
    json_response($result, !empty($result['ok']) ? 200 : 422);
});

$router->post('/admin/sms/api/test-send', static function () use ($batches, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    $data = $requireCsrfJson();
    $result = $batches()->testSend($data, (int) Auth::adminId());
    json_response($result, !empty($result['ok']) ? 200 : 422);
});

// Saved audiences
$router->get('/admin/sms/api/audiences', static function () use ($audience): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk', 'sms.view']);
    json_response(['ok' => true, 'items' => $audience()->listAudiences()]);
});

$router->post('/admin/sms/api/audiences/save', static function () use ($audience, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk', 'sms.automation.manage']);
    $data = $requireCsrfJson();
    $result = $audience()->saveAudience(
        (string) ($data['name'] ?? ''),
        (array) ($data['filters'] ?? $data),
        isset($data['description']) ? (string) $data['description'] : null,
        !empty($data['id']) ? (int) $data['id'] : null,
        Auth::adminId()
    );
    if (!empty($result['ok'])) {
        audit('sms.audience_save', 'sms_saved_audiences', (int) $result['id']);
    }
    json_response($result, !empty($result['ok']) ? 200 : 422);
});

$router->post('/admin/sms/api/audiences/duplicate', static function () use ($audience, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    $data = $requireCsrfJson();
    $result = $audience()->duplicateAudience((int) ($data['id'] ?? 0), Auth::adminId());
    json_response($result, !empty($result['ok']) ? 200 : 422);
});

$router->post('/admin/sms/api/audiences/delete', static function () use ($audience, $requireCsrfJson): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    $data = $requireCsrfJson();
    $id = (int) ($data['id'] ?? 0);
    $audience()->deleteAudience($id);
    audit('sms.audience_delete', 'sms_saved_audiences', $id);
    json_response(['ok' => true]);
});

// Batches
$router->get('/admin/sms/batches', static function () use ($batches): void {
    Auth::requireAdmin('sms.view');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $list = $batches()->listBatches($page);
    view('admin/sms-batches', $list + ['title' => 'ارسال‌های گروهی']);
});

$router->get('/admin/sms/batches/{id}', static function (string $id) use ($batches): void {
    Auth::requireAdmin('sms.view');
    $batch = $batches()->refreshStats((int) $id);
    if (!$batch) {
        flash('error', 'ارسال گروهی یافت نشد.');
        redirect('/admin/sms/batches');
    }
    $qPage = max(1, (int) ($_GET['page'] ?? 1));
    $status = (string) ($_GET['status'] ?? '');
    $items = $batches()->batchQueueItems((int) $id, $qPage, 40, $status !== '' ? $status : null);
    view('admin/sms-batch', [
        'title' => 'ارسال گروهی ' . ($batch['public_code'] ?? ''),
        'batch' => $batch,
        'queue' => $items,
        'statusFilter' => $status,
    ]);
});

$router->get('/admin/sms/api/batches/{id}', static function (string $id) use ($batches): void {
    Auth::requireAdmin('sms.view');
    $batch = $batches()->refreshStats((int) $id);
    if (!$batch) {
        json_response(['ok' => false, 'message' => 'یافت نشد'], 404);
    }
    json_response(['ok' => true, 'batch' => $batch]);
});

$router->post('/admin/sms/batches/{id}/cancel', static function (string $id) use ($batches): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    Csrf::assertValid();
    $result = $batches()->cancel((int) $id, (int) Auth::adminId());
    flash($result['ok'] ? 'success' : 'error', $result['message'] ?? '');
    redirect('/admin/sms/batches/' . (int) $id);
});

$router->post('/admin/sms/batches/{id}/retry', static function (string $id) use ($batches): void {
    Auth::requireAdminAny(['sms.send', 'sms.send.bulk']);
    Csrf::assertValid();
    $result = $batches()->retryFailed((int) $id, (int) Auth::adminId());
    flash($result['ok'] ? 'success' : 'error', $result['message'] ?? '');
    redirect('/admin/sms/batches/' . (int) $id);
});

$router->post('/admin/sms/templates/{id}/favorite', static function (string $id): void {
    Auth::requireAdmin('sms.templates.manage');
    Csrf::assertValid();
    try {
        db()->prepare('UPDATE sms_templates SET is_favorite=IF(is_favorite=1,0,1) WHERE id=?')->execute([(int) $id]);
        flash('success', 'علاقه‌مندی قالب به‌روز شد.');
    } catch (\Throwable) {
        flash('error', 'ابتدا مهاجرت پایگاه‌داده را اجرا کنید.');
    }
    redirect('/admin/sms/templates');
});
