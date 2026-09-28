<?php

declare(strict_types=1);

/**
 * Patient clinical CRM routes (visits, medical, documents, plans, quick search).
 */

use Sarva\Core\Auth;
use Sarva\Core\Csrf;
use Sarva\Services\PatientClinicalService;
use Sarva\Services\PatientCrmService;

/** @var \Sarva\Core\Router $router */

$crm = static fn (): PatientCrmService => new PatientCrmService(db());
$clinical = static fn (): PatientClinicalService => new PatientClinicalService(db());

$router->get('/admin/patients/api/quick-search', static function () use ($crm): void {
    Auth::requireAdminAny(['patients.view', 'patients.manage', 'appointments.manage']);
    $q = (string) ($_GET['q'] ?? '');
    json_response(['ok' => true, 'items' => $crm()->quickSearch($q)]);
});

$router->post('/admin/patients/{id}/visits/save', static function (string $id) use ($clinical): void {
    Auth::requireAdminAny(['patient_visits.create', 'patient_visits.edit', 'patients.manage']);
    Csrf::assertValid();
    $visitId = (int) ($_POST['visit_id'] ?? 0);
    if ($visitId > 0) {
        Auth::requireAdminAny(['patient_visits.edit', 'patients.manage']);
    }
    $data = $_POST;
    $data['patient_id'] = (int) $id;
    $result = $clinical()->saveVisit($data, $visitId > 0 ? $visitId : null, (int) Auth::adminId());
    flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'جلسه ذخیره شد.' : ($result['message'] ?? 'خطا'));
    redirect('/admin/patients/' . (int) $id . '?tab=visits' . (!empty($result['id']) ? '&visit=' . (int) $result['id'] : ''));
});

$router->post('/admin/patients/{id}/medical', static function (string $id) use ($clinical): void {
    Auth::requireAdminAny(['patient_medical.edit', 'patients.manage']);
    Csrf::assertValid();
    $clinical()->saveMedical((int) $id, $_POST, (int) Auth::adminId());
    flash('success', 'اطلاعات پزشکی ذخیره شد.');
    redirect('/admin/patients/' . (int) $id . '?tab=medical');
});

$router->post('/admin/patients/{id}/documents/upload', static function (string $id) use ($clinical): void {
    Auth::requireAdminAny(['patient_documents.upload', 'patients.manage']);
    Csrf::assertValid();
    $file = $_FILES['document'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'فایل انتخاب نشده است.');
        redirect('/admin/patients/' . (int) $id . '?tab=documents');
    }
    $allowed = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        flash('error', 'فقط تصویر یا PDF مجاز است.');
        redirect('/admin/patients/' . (int) $id . '?tab=documents');
    }
    if (($file['size'] ?? 0) > 12 * 1024 * 1024) {
        flash('error', 'حجم فایل بیش از ۱۲ مگابایت است.');
        redirect('/admin/patients/' . (int) $id . '?tab=documents');
    }

    $dirRel = 'uploads/patients/' . (int) $id . '/' . date('Y/m');
    $originalName = (string) ($file['name'] ?? '');
    $storedPath = null;
    $storedMime = $mime;
    $storedSize = (int) ($file['size'] ?? 0);

    if (str_starts_with($mime, 'image/')) {
        $storedPath = store_uploaded_image('document', false, 12 * 1024 * 1024, $dirRel);
        if ($storedPath === null) {
            flash('error', 'ذخیره تصویر ناموفق بود.');
            redirect('/admin/patients/' . (int) $id . '?tab=documents');
        }
        $storedMime = str_ends_with(strtolower($storedPath), '.webp') ? 'image/webp' : $mime;
        foreach (asset_storage_roots() as $root) {
            $check = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
            if (is_file($check)) {
                $storedSize = (int) filesize($check);
                break;
            }
        }
    } else {
        $name = bin2hex(random_bytes(8)) . '.pdf';
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sarva_doc_' . $name;
        if (!move_uploaded_file($file['tmp_name'], $tmp)) {
            flash('error', 'ذخیره فایل ناموفق بود.');
            redirect('/admin/patients/' . (int) $id . '?tab=documents');
        }
        $storedPath = $dirRel . '/' . $name;
        if (!publish_asset_file($tmp, $storedPath)) {
            @unlink($tmp);
            flash('error', 'ذخیره فایل ناموفق بود.');
            redirect('/admin/patients/' . (int) $id . '?tab=documents');
        }
        $storedSize = (int) (@filesize($tmp) ?: $storedSize);
        @unlink($tmp);
        $storedMime = 'application/pdf';
    }

    $title = trim((string) ($_POST['title'] ?? '')) ?: pathinfo($originalName !== '' ? $originalName : (string) $storedPath, PATHINFO_FILENAME);
    $result = $clinical()->addDocument((int) $id, [
        'title' => $title,
        'description' => trim((string) ($_POST['description'] ?? '')),
        'category' => (string) ($_POST['category'] ?? 'other'),
        'file_path' => $storedPath,
        'original_name' => $originalName !== '' ? $originalName : basename((string) $storedPath),
        'mime_type' => $storedMime,
        'file_size' => $storedSize,
        'document_date' => trim((string) ($_POST['document_date'] ?? '')) ?: null,
    ], (int) Auth::adminId());
    flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'مدرک پیوست شد.' : ($result['message'] ?? 'خطا'));
    redirect('/admin/patients/' . (int) $id . '?tab=documents');
});

$router->post('/admin/patients/{id}/plans/save', static function (string $id) use ($clinical): void {
    Auth::requireAdminAny(['patient_treatment_plans.manage', 'patients.manage']);
    Csrf::assertValid();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $data = $_POST;
    $data['patient_id'] = (int) $id;
    $result = $clinical()->savePlan($data, $planId > 0 ? $planId : null, (int) Auth::adminId());
    flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'طرح درمان ذخیره شد.' : ($result['message'] ?? 'خطا'));
    redirect('/admin/patients/' . (int) $id . '?tab=plans');
});

$router->post('/admin/patients/{id}/plans/item', static function (string $id) use ($clinical): void {
    Auth::requireAdminAny(['patient_treatment_plans.manage', 'patients.manage']);
    Csrf::assertValid();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $result = $clinical()->savePlanItem($_POST, $itemId > 0 ? $itemId : null);
    flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'آیتم طرح درمان ذخیره شد.' : ($result['message'] ?? 'خطا'));
    redirect('/admin/patients/' . (int) $id . '?tab=plans');
});
