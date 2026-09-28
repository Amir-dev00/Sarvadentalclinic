<?php

declare(strict_types=1);

/**
 * Patient CRM + SMS center admin routes.
 */

use Sarva\Core\Auth;
use Sarva\Core\Csrf;
use Sarva\Services\PatientCrmService;
use Sarva\Services\SmsAudienceService;
use Sarva\Services\SmsAutomationService;
use Sarva\Services\SmsService;
use Sarva\Services\SmsTemplateRenderer;
use Sarva\Sms\SmsManager;

/** @var \Sarva\Core\Router $router */

$crm = static fn (): PatientCrmService => new PatientCrmService(db());
$sms = static fn (): SmsService => new SmsService(db());

$router->get('/admin/patients', static function () use ($crm): void {
    Auth::requireAdminAny(['patients.manage', 'patients.view']);
    $filters = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'source' => (string) ($_GET['source'] ?? ''),
        'status' => (string) ($_GET['status'] ?? 'active'),
        'doctor_id' => (int) ($_GET['doctor_id'] ?? 0),
        'service_id' => (int) ($_GET['service_id'] ?? 0),
        'upcoming' => !empty($_GET['upcoming']),
        'registered_from' => (string) ($_GET['registered_from'] ?? ''),
        'registered_to' => (string) ($_GET['registered_to'] ?? ''),
    ];
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $result = $crm()->search($filters, $page);
    $doctors = db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $services = db()->query('SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    view('admin/patients', $result + [
        'title' => 'بیماران',
        'filters' => $filters,
        'doctors' => $doctors,
        'services' => $services,
        'query' => $_GET,
    ]);
});

$router->post('/admin/patients/purge-all', static function () use ($crm): void {
    Auth::requireAdmin('patients.manage');
    Csrf::assertValid();
    @set_time_limit(300);

    $confirm = trim((string) ($_POST['confirm_phrase'] ?? ''));
    if ($confirm !== 'حذف همه') {
        flash('error', 'برای تأیید باید عبارت «حذف همه» را دقیقاً وارد کنید.');
        redirect('/admin/patients');
    }

    $purge = $crm()->purgeAllPatients();
    if (empty($purge['ok'])) {
        flash('error', 'حذف بیماران ناموفق بود: ' . (string) ($purge['message'] ?? 'خطای نامشخص'));
        redirect('/admin/patients');
    }

    flash('success', 'همه بیماران حذف شدند (' . (int) ($purge['deleted'] ?? 0) . ' پرونده).');
    redirect('/admin/patients');
});

$router->get('/admin/patients/create', static function () use ($crm): void {
    Auth::requireAdminAny(['patients.manage', 'patients.create']);
    $doctors = db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    view('admin/patient-form', [
        'title' => 'ایجاد پرونده',
        'patient' => [],
        'doctors' => $doctors,
        'suggested_file_number' => $crm()->suggestNextFileNumber(),
        'mode' => 'create',
    ]);
});

$router->get('/admin/patients/{id}', static function (string $id) use ($crm): void {
    Auth::requireAdminAny(['patients.manage', 'patients.view']);
    $bundle = $crm()->profileBundle((int) $id);
    if (!$bundle) {
        flash('error', 'بیمار یافت نشد.');
        redirect('/admin/patients');
    }
    $templates = db()->query("SELECT * FROM sms_templates WHERE is_active=1 AND deleted_at IS NULL ORDER BY name")->fetchAll();
    $doctors = db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $services = db()->query('SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    view('admin/patient-profile', $bundle + [
        'title' => 'پرونده بیمار',
        'tab' => (string) ($_GET['tab'] ?? 'summary'),
        'templates' => $templates,
        'doctors' => $doctors,
        'services' => $services,
    ]);
});

$router->get('/admin/patients/{id}/edit', static function (string $id) use ($crm): void {
    Auth::requireAdminAny(['patients.manage', 'patients.edit']);
    $patient = $crm()->find((int) $id, true);
    if (!$patient) {
        flash('error', 'بیمار یافت نشد.');
        redirect('/admin/patients');
    }
    $doctors = db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    view('admin/patient-form', [
        'title' => 'ویرایش پرونده',
        'patient' => $patient,
        'doctors' => $doctors,
        'mode' => 'edit',
    ]);
});

$router->post('/admin/patients/save', static function () use ($crm): void {
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    Auth::requireAdminAny($id ? ['patients.manage', 'patients.edit'] : ['patients.manage', 'patients.create']);
    $result = $crm()->save($_POST, $id > 0 ? $id : null);
    if (!$result['ok']) {
        flash('error', $result['message'] ?? 'ذخیره ناموفق بود.');
        redirect($id ? '/admin/patients/' . $id . '/edit' : '/admin/patients/create');
    }
    flash('success', $id ? 'تغییرات پرونده ذخیره شد.' : 'پرونده جدید با موفقیت ایجاد شد.');
    redirect('/admin/patients/' . (int) $result['id']);
});

$router->post('/admin/patients/{id}/archive', static function (string $id) use ($crm): void {
    Auth::requireAdminAny(['patients.manage', 'patients.archive']);
    Csrf::assertValid();
    $crm()->archive((int) $id);
    flash('success', 'بیمار بایگانی شد.');
    redirect('/admin/patients');
});

$router->post('/admin/patients/{id}/restore', static function (string $id) use ($crm): void {
    Auth::requireAdminAny(['patients.manage', 'patients.archive']);
    Csrf::assertValid();
    $crm()->restore((int) $id);
    flash('success', 'بیمار بازیابی شد.');
    $fromList = (string) ($_POST['from'] ?? '') === 'list';
    redirect($fromList ? '/admin/patients?status=archived' : '/admin/patients/' . (int) $id);
});

$router->post('/admin/patients/{id}/note', static function (string $id): void {
    Auth::requireAdminAny(['patients.manage', 'patients.edit']);
    Csrf::assertValid();
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body === '') {
        flash('error', 'متن یادداشت خالی است.');
        redirect('/admin/patients/' . (int) $id . '?tab=notes');
    }
    db()->prepare('INSERT INTO patient_notes (patient_id, admin_user_id, body) VALUES (?,?,?)')
        ->execute([(int) $id, Auth::adminId(), $body]);
    audit('patient.note', 'patients', (int) $id);
    flash('success', 'یادداشت ثبت شد.');
    redirect('/admin/patients/' . (int) $id . '?tab=notes');
});

$router->post('/admin/patients/{id}/sms', static function (string $id) use ($crm, $sms): void {
    Auth::requireAdmin('sms.send');
    Csrf::assertValid();
    $patient = $crm()->find((int) $id);
    if (!$patient) {
        flash('error', 'بیمار یافت نشد.');
        redirect('/admin/patients');
    }
    $tplId = (int) ($_POST['template_id'] ?? 0);
    $custom = trim((string) ($_POST['message'] ?? ''));
    $body = $custom;
    if ($tplId > 0) {
        $tpl = db()->prepare('SELECT * FROM sms_templates WHERE id=? AND deleted_at IS NULL');
        $tpl->execute([$tplId]);
        $t = $tpl->fetch() ?: null;
        if ($t) {
            $body = SmsTemplateRenderer::render((string) $t['body'], SmsTemplateRenderer::varsFrom($patient));
        }
    }
    if ($body === '') {
        flash('error', 'متن پیامک خالی است.');
        redirect('/admin/patients/' . (int) $id . '?tab=sms');
    }
    $qid = $sms()->enqueue([
        'patient_id' => (int) $id,
        'template_id' => $tplId ?: null,
        'admin_user_id' => Auth::adminId(),
        'mobile' => (string) $patient['mobile'],
        'message' => $body,
        'message_type' => 'general',
        'source' => 'manual',
    ]);
    audit('sms.manual', 'patients', (int) $id, ['queue_id' => $qid]);
    flash('success', $qid ? 'پیامک در صف ارسال قرار گرفت.' : 'ارسال انجام نشد (شماره نامعتبر یا تکراری).');
    redirect('/admin/patients/' . (int) $id . '?tab=sms');
});

$router->get('/admin/sms-logs', static function (): void {
    redirect('/admin/sms/logs');
});

$router->get('/admin/sms', static function () use ($sms): void {
    Auth::requireAdmin('sms.view');
    view('admin/sms-center', [
        'title' => 'مرکز پیامک',
        'stats' => $sms()->stats(),
        'driver' => SmsManager::driver(),
    ]);
});

require __DIR__ . '/sms-center-routes.php';
require __DIR__ . '/patient-clinical-routes.php';

$router->get('/admin/sms/templates', static function (): void {
    Auth::requireAdminAny(['sms.templates.manage', 'sms.view']);
    $items = db()->query("SELECT * FROM sms_templates WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
    view('admin/sms-templates', ['title' => 'پیام‌های آماده', 'items' => $items]);
});

$router->post('/admin/sms/templates/save', static function (): void {
    Auth::requireAdmin('sms.templates.manage');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? '')) ?: admin_slug($name, 'sms');
    $body = trim((string) ($_POST['body'] ?? ''));
    $type = (string) ($_POST['type'] ?? 'custom');
    $category = (string) ($_POST['category'] ?? 'general');
    $active = isset($_POST['is_active']) ? 1 : 0;
    $favorite = isset($_POST['is_favorite']) ? 1 : 0;
    if ($name === '' || $body === '') {
        flash('error', 'عنوان و متن قالب الزامی است.');
        redirect('/admin/sms/templates');
    }
    $types = array_keys(sms_type_labels());
    if (!in_array($type, $types, true)) {
        $type = 'custom';
    }
    $cats = ['appointment', 'reminder', 'payment', 'operational', 'followup', 'marketing', 'general'];
    if (!in_array($category, $cats, true)) {
        $category = 'general';
    }
    try {
        if ($id > 0) {
            db()->prepare('UPDATE sms_templates SET name=?, slug=?, type=?, category=?, body=?, is_active=?, is_favorite=? WHERE id=?')
                ->execute([$name, $slug, $type, $category, $body, $active, $favorite, $id]);
            audit('sms.template_update', 'sms_templates', $id);
        } else {
            db()->prepare('INSERT INTO sms_templates (name, slug, type, category, body, is_active, is_favorite) VALUES (?,?,?,?,?,?,?)')
                ->execute([$name, $slug, $type, $category, $body, $active, $favorite]);
            audit('sms.template_create', 'sms_templates', (int) db()->lastInsertId());
        }
    } catch (\Throwable) {
        if ($id > 0) {
            db()->prepare('UPDATE sms_templates SET name=?, slug=?, type=?, body=?, is_active=? WHERE id=?')
                ->execute([$name, $slug, $type, $body, $active, $id]);
        } else {
            db()->prepare('INSERT INTO sms_templates (name, slug, type, body, is_active) VALUES (?,?,?,?,?)')
                ->execute([$name, $slug, $type, $body, $active]);
        }
    }
    flash('success', 'قالب ذخیره شد.');
    redirect('/admin/sms/templates');
});

$router->post('/admin/sms/templates/action', static function (): void {
    Auth::requireAdmin('sms.templates.manage');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $act = (string) ($_POST['action'] ?? '');
    if ($id <= 0) {
        redirect('/admin/sms/templates');
    }
    if ($act === 'duplicate') {
        $row = db()->prepare('SELECT * FROM sms_templates WHERE id=?');
        $row->execute([$id]);
        $t = $row->fetch();
        if ($t) {
            db()->prepare('INSERT INTO sms_templates (slug, name, type, body, is_active) VALUES (?,?,?,?,0)')
                ->execute([$t['slug'] . '-copy-' . substr(bin2hex(random_bytes(2)), 0, 4), $t['name'] . ' (کپی)', $t['type'], $t['body']]);
            audit('sms.template_duplicate', 'sms_templates', $id);
            flash('success', 'قالب کپی شد.');
        }
    } elseif ($act === 'toggle') {
        db()->prepare('UPDATE sms_templates SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
        audit('sms.template_toggle', 'sms_templates', $id);
        flash('success', 'وضعیت قالب به‌روز شد.');
    } elseif ($act === 'delete') {
        db()->prepare('UPDATE sms_templates SET deleted_at=NOW(), is_active=0 WHERE id=?')->execute([$id]);
        audit('sms.template_archive', 'sms_templates', $id);
        flash('success', 'قالب بایگانی شد.');
    } elseif ($act === 'test') {
        $mobile = normalize_mobile((string) ($_POST['test_mobile'] ?? ''));
        $t = db()->prepare('SELECT * FROM sms_templates WHERE id=?');
        $t->execute([$id]);
        $tpl = $t->fetch();
        if (!$mobile || !$tpl) {
            flash('error', 'شماره یا قالب نامعتبر است.');
            redirect('/admin/sms/templates');
        }
        $msg = SmsTemplateRenderer::render((string) $tpl['body'], [
            'full_name' => 'بیمار آزمایشی',
            'first_name' => 'آزمایش',
            'last_name' => 'سروا',
            'patient_number' => 'PTEST',
            'appointment_date' => to_jalali(date('Y-m-d H:i:s'), 'Y/m/d'),
            'appointment_time' => date('H:i'),
            'doctor_name' => 'پزشک نمونه',
            'service_name' => 'ویزیت',
            'clinic_name' => (string) setting('clinic_name', 'کلینیک سروا'),
            'clinic_phone' => (string) setting('phone', ''),
        ]);
        (new SmsService(db()))->enqueue([
            'template_id' => $id,
            'admin_user_id' => Auth::adminId(),
            'mobile' => $mobile,
            'message' => $msg,
            'message_type' => $tpl['type'] ?? 'custom',
            'source' => 'manual',
        ]);
        audit('sms.template_test', 'sms_templates', $id);
        flash('success', 'پیام آزمایشی در صف قرار گرفت.');
    }
    redirect('/admin/sms/templates');
});

$router->get('/admin/sms/automation', static function (): void {
    Auth::requireAdminAny(['sms.automation.manage', 'sms.view']);
    $items = db()->query(
        'SELECT r.*, t.name template_name FROM sms_automation_rules r JOIN sms_templates t ON t.id=r.template_id ORDER BY r.id DESC'
    )->fetchAll();
    $templates = db()->query("SELECT * FROM sms_templates WHERE deleted_at IS NULL AND is_active=1 ORDER BY name")->fetchAll();
    $doctors = db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $services = db()->query('SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $auto = new SmsAutomationService(db(), new SmsService(db()));
    $previews = [];
    $tplMap = [];
    foreach ($templates as $t) {
        $tplMap[(int) $t['id']] = $t;
    }
    foreach ($items as $item) {
        $previews[(int) $item['id']] = $auto->preview($item, $tplMap[(int) $item['template_id']] ?? ['body' => '']);
    }
    view('admin/sms-automation', [
        'title' => 'پیام‌های خودکار',
        'items' => $items,
        'templates' => $templates,
        'doctors' => $doctors,
        'services' => $services,
        'previews' => $previews,
    ]);
});

$router->post('/admin/sms/automation/save', static function (): void {
    Auth::requireAdmin('sms.automation.manage');
    Csrf::assertValid();
    new SmsAutomationService(db(), new SmsService(db()));

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $tpl = (int) ($_POST['template_id'] ?? 0);
    if ($name === '' || $tpl <= 0) {
        flash('error', 'نام و قالب پیام الزامی است.');
        redirect('/admin/sms/automation');
    }

    $patientFilter = SmsAutomationService::normalizePatientFilter($_POST['patient_filter'] ?? 'all');
    $st = substr((string) ($_POST['send_time'] ?? '18:00'), 0, 5);
    $sendTime = preg_match('/^\d{2}:\d{2}$/', $st) ? $st . ':00' : '18:00:00';
    $unit = ($_POST['offset_unit'] ?? 'days') === 'hours' ? 'hours' : 'days';

    // Keep audience_json empty so enqueue uses the simple who/doctor/service filters.
    $data = [
        $name,
        'appointment',
        max(1, (int) ($_POST['offset_value'] ?? 1)),
        $unit,
        $sendTime,
        $tpl,
        (int) ($_POST['doctor_id'] ?? 0) ?: null,
        (int) ($_POST['service_id'] ?? 0) ?: null,
        trim((string) ($_POST['appointment_statuses'] ?? 'confirmed')) ?: 'confirmed',
        $patientFilter,
        null,
        isset($_POST['is_active']) ? 1 : 0,
    ];
    try {
        if ($id > 0) {
            db()->prepare(
                'UPDATE sms_automation_rules SET name=?, trigger_type=?, offset_value=?, offset_unit=?, send_time=?, template_id=?, doctor_id=?, service_id=?, appointment_statuses=?, patient_filter=?, audience_json=?, is_active=? WHERE id=?'
            )->execute([...$data, $id]);
            audit('sms.automation_update', 'sms_automation_rules', $id);
        } else {
            db()->prepare(
                'INSERT INTO sms_automation_rules (name, trigger_type, offset_value, offset_unit, send_time, template_id, doctor_id, service_id, appointment_statuses, patient_filter, audience_json, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute($data);
            audit('sms.automation_create', 'sms_automation_rules', (int) db()->lastInsertId());
        }
        flash('success', 'یادآوری ذخیره شد.');
    } catch (Throwable $e) {
        try {
            if ($id > 0) {
                db()->prepare(
                    'UPDATE sms_automation_rules SET name=?, trigger_type=?, offset_value=?, offset_unit=?, send_time=?, template_id=?, doctor_id=?, service_id=?, appointment_statuses=?, patient_filter=?, is_active=? WHERE id=?'
                )->execute([
                    $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[11], $id,
                ]);
            } else {
                db()->prepare(
                    'INSERT INTO sms_automation_rules (name, trigger_type, offset_value, offset_unit, send_time, template_id, doctor_id, service_id, appointment_statuses, patient_filter, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[11],
                ]);
            }
            flash('success', 'یادآوری ذخیره شد.');
        } catch (Throwable $e2) {
            flash('error', 'ذخیره ناموفق بود: ' . $e2->getMessage());
        }
    }
    redirect('/admin/sms/automation');
});

$router->post('/admin/sms/automation/toggle', static function (): void {
    Auth::requireAdmin('sms.automation.manage');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('UPDATE sms_automation_rules SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
    audit('sms.automation_toggle', 'sms_automation_rules', $id);
    flash('success', 'وضعیت قانون به‌روز شد.');
    redirect('/admin/sms/automation');
});

$router->get('/admin/sms/queue', static function (): void {
    Auth::requireAdmin('sms.view');
    $items = db()->query(
        "SELECT q.*, CONCAT(p.first_name,' ',p.last_name) patient_name
         FROM sms_queue q LEFT JOIN patients p ON p.id=q.patient_id
         ORDER BY q.id DESC LIMIT 150"
    )->fetchAll();
    view('admin/sms-queue', ['title' => 'پیام‌های زمان‌بندی‌شده', 'items' => $items]);
});

$router->post('/admin/sms/queue/cancel', static function (): void {
    Auth::requireAdmin('sms.send');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare("UPDATE sms_queue SET status='cancelled' WHERE id=? AND status IN ('pending','retrying')")->execute([$id]);
    flash('success', 'ارسال لغو شد.');
    redirect('/admin/sms/queue');
});

$router->get('/admin/sms/logs', static function (): void {
    Auth::requireAdmin('sms.view');
    $where = ['1=1'];
    $params = [];
    if (($s = (string) ($_GET['status'] ?? '')) !== '') {
        $where[] = 'l.status=?';
        $params[] = $s;
    }
    if (($t = (string) ($_GET['type'] ?? '')) !== '') {
        $where[] = 'l.message_type=?';
        $params[] = $t;
    }
    if (($src = (string) ($_GET['source'] ?? '')) !== '') {
        $where[] = 'l.source=?';
        $params[] = $src;
    }
    if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') {
        $where[] = '(l.mobile LIKE ? OR l.message_body LIKE ? OR CONCAT(p.first_name,\' \',p.last_name) LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (($df = (string) ($_GET['date_from'] ?? '')) !== '') {
        $where[] = 'DATE(l.created_at) >= ?';
        $params[] = $df;
    }
    if (($dt = (string) ($_GET['date_to'] ?? '')) !== '') {
        $where[] = 'DATE(l.created_at) <= ?';
        $params[] = $dt;
    }
    if (($bid = (int) ($_GET['batch_id'] ?? 0)) > 0) {
        $where[] = 'l.batch_id=?';
        $params[] = $bid;
    }
    $sqlWhere = implode(' AND ', $where);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 30;
    $cnt = db()->prepare("SELECT COUNT(*) FROM sms_logs l LEFT JOIN patients p ON p.id=l.patient_id WHERE {$sqlWhere}");
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();
    $off = ($page - 1) * $per;
    $stmt = db()->prepare(
        "SELECT l.*, CONCAT(p.first_name,' ',p.last_name) patient_name, t.name template_name,
                CONCAT(u.first_name,' ',u.last_name) admin_name
         FROM sms_logs l
         LEFT JOIN patients p ON p.id=l.patient_id
         LEFT JOIN sms_templates t ON t.id=l.template_id
         LEFT JOIN admin_users u ON u.id=l.admin_user_id
         WHERE {$sqlWhere}
         ORDER BY l.id DESC LIMIT {$per} OFFSET {$off}"
    );
    $stmt->execute($params);
    view('admin/sms-logs', [
        'title' => 'تاریخچه پیامک',
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => max(1, (int) ceil($total / $per)),
        'query' => $_GET,
    ]);
});

$router->get('/admin/sms/settings', static function (): void {
    Auth::requireAdmin('sms.settings.manage');
    $templates = db()->query("SELECT id, name FROM sms_templates WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    view('admin/sms-settings', [
        'title' => 'تنظیمات پیامک',
        'templates' => $templates,
        'driver' => SmsManager::driver(),
        'masked_key' => mask_secret((string) config('sms.api_key')),
        'line' => (string) config('sms.line_number'),
    ]);
});

$router->post('/admin/sms/settings', static function (): void {
    Auth::requireAdmin('sms.settings.manage');
    Csrf::assertValid();
    $keys = ['sms_enabled', 'sms_max_retries', 'sms_retry_delay_minutes', 'sms_default_reminder_time', 'sms_bulk_limit', 'timezone'];
    $stmt = db()->prepare('INSERT INTO site_settings (`key`, `value`, group_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
    $stmt->execute(['sms_enabled', isset($_POST['sms_enabled']) ? '1' : '0', 'sms']);
    foreach (['sms_max_retries', 'sms_retry_delay_minutes', 'sms_default_reminder_time', 'sms_bulk_limit'] as $k) {
        if (isset($_POST[$k])) {
            $stmt->execute([$k, trim((string) $_POST[$k]), 'sms']);
        }
    }
    if (!empty($_POST['timezone'])) {
        $stmt->execute(['timezone', trim((string) $_POST['timezone']), 'general']);
    }
    audit('sms.settings', 'site_settings', null, ['keys' => $keys]);
    flash('success', 'تنظیمات پیامک ذخیره شد. کلید API فقط از فایل .env خوانده می‌شود.');
    redirect('/admin/sms/settings');
});
