<?php

declare(strict_types=1);

use Sarva\Core\Auth;
use Sarva\Core\Csrf;
use Sarva\Core\Database;
use Sarva\Core\Router;
use Sarva\Services\AppointmentService;

/** @var Router $router */

$router->get('/admin/login', static function (): void {
    if (Auth::isAdmin()) {
        redirect('/admin');
    }
    view('admin/login', ['title' => 'ورود مدیریت']);
});

$router->post('/admin/login', static function (): void {
    Csrf::assertValid();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
        flash('error', 'حساب موقتاً قفل شده است.');
        redirect('/admin/login');
    }

    if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password_hash'])) {
        if ($user) {
            $attempts = (int) $user['login_attempts'] + 1;
            $lock = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            db()->prepare('UPDATE admin_users SET login_attempts=?, locked_until=? WHERE id=?')
                ->execute([$attempts, $lock, $user['id']]);
        }
        flash('error', 'ایمیل یا رمز عبور نادرست است.');
        redirect('/admin/login');
    }

    db()->prepare('UPDATE admin_users SET login_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')
        ->execute([$user['id']]);
    Auth::loginAdmin((int) $user['id']);
    audit('admin.login', 'admin_user', (int) $user['id']);
    redirect('/admin');
});

$router->post('/admin/logout', static function (): void {
    Csrf::assertValid();
    Auth::logout();
    redirect('/admin/login');
});

$router->get('/admin/account', static function (): void {
    Auth::requireAdmin();
    $adminId = Auth::adminId();
    $stmt = db()->prepare('SELECT id, first_name, last_name, email FROM admin_users WHERE id=? AND deleted_at IS NULL');
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch() ?: [];
    view('admin/account', ['title' => 'حساب کاربری', 'admin' => $admin]);
});

$router->post('/admin/account/password', static function (): void {
    Auth::requireAdmin();
    Csrf::assertValid();
    $adminId = (int) Auth::adminId();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM admin_users WHERE id=? AND deleted_at IS NULL AND is_active=1 LIMIT 1');
    $stmt->execute([$adminId]);
    $user = $stmt->fetch();
    if (!$user) {
        flash('error', 'حساب کاربری یافت نشد.');
        redirect('/admin/account');
    }
    if ($current === '' || !password_verify($current, (string) $user['password_hash'])) {
        flash('error', 'رمز عبور فعلی نادرست است.');
        redirect('/admin/account');
    }
    if (strlen($new) < 8) {
        flash('error', 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.');
        redirect('/admin/account');
    }
    if ($new !== $confirm) {
        flash('error', 'تکرار رمز عبور با رمز جدید یکسان نیست.');
        redirect('/admin/account');
    }
    if (password_verify($new, (string) $user['password_hash'])) {
        flash('error', 'رمز عبور جدید باید با رمز فعلی متفاوت باشد.');
        redirect('/admin/account');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    db()->prepare('UPDATE admin_users SET password_hash=?, login_attempts=0, locked_until=NULL WHERE id=?')
        ->execute([$hash, $adminId]);
    \Sarva\Core\Session::regenerate();
    audit('admin.password_change', 'admin_users', $adminId);
    flash('success', 'رمز عبور با موفقیت در پایگاه داده به‌روز شد.');
    redirect('/admin/account');
});

$router->get('/admin', static function (): void {
    Auth::requireAdmin();
    $stats = [
        'today' => 0, 'tomorrow' => 0, 'week' => 0,
        'pending_payments' => 0, 'paid_payments' => 0,
        'patients' => 0, 'new_patients' => 0,
    ];
    if (Database::connected()) {
        $stats['today'] = (int) db()->query("SELECT COUNT(*) FROM appointments WHERE deleted_at IS NULL AND DATE(starts_at)=CURDATE() AND status IN ('confirmed','awaiting_payment')")->fetchColumn();
        $stats['tomorrow'] = (int) db()->query("SELECT COUNT(*) FROM appointments WHERE deleted_at IS NULL AND DATE(starts_at)=CURDATE()+INTERVAL 1 DAY AND status='confirmed'")->fetchColumn();
        $stats['week'] = (int) db()->query("SELECT COUNT(*) FROM appointments WHERE deleted_at IS NULL AND starts_at >= CURDATE() AND starts_at < CURDATE()+INTERVAL 7 DAY AND status='confirmed'")->fetchColumn();
        $stats['pending_payments'] = (int) db()->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
        $stats['paid_payments'] = (int) db()->query("SELECT COUNT(*) FROM payments WHERE status='paid' AND DATE(verified_at)=CURDATE()")->fetchColumn();
        $stats['patients'] = (int) db()->query('SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL')->fetchColumn();
        $stats['new_patients'] = (int) db()->query('SELECT COUNT(*) FROM patients WHERE DATE(created_at)=CURDATE()')->fetchColumn();
        $upcoming = db()->query(
            "SELECT a.*, CONCAT(p.first_name,' ',p.last_name) patient_name, s.name service_name
             FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN services s ON s.id=a.service_id
             WHERE a.deleted_at IS NULL AND a.starts_at >= NOW() AND a.status='confirmed' ORDER BY a.starts_at ASC LIMIT 8"
        )->fetchAll();
        $viewsToday = (int) db()->query('SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at)=CURDATE()')->fetchColumn();
        $viewsWeek = (int) db()->query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= CURDATE()-INTERVAL 7 DAY')->fetchColumn();
        try {
            $smsStats = (new \Sarva\Services\SmsService(db()))->stats();
        } catch (\Throwable) {
            $smsStats = [];
        }
    } else {
        $upcoming = [];
        $viewsToday = $viewsWeek = 0;
        $smsStats = [];
    }
    view('admin/dashboard', compact('stats', 'upcoming', 'viewsToday', 'viewsWeek', 'smsStats') + ['title' => 'داشبورد']);
});

$adminCrud = [
    '/admin/pages' => ['cms.pages', 'pages', 'صفحات وب‌سایت'],
    '/admin/services' => ['cms.services', 'services', 'خدمات'],
    '/admin/appointments' => ['appointments.manage', 'appointments', 'نوبت‌ها'],
    '/admin/payments' => ['payments.manage', 'payments', 'پرداخت‌ها'],
    '/admin/faqs' => ['cms.pages', 'faqs', 'سؤالات متداول'],
    '/admin/testimonials' => ['cms.pages', 'testimonials', 'نظرات'],
    '/admin/case-studies' => ['cms.pages', 'case-studies', 'قبل و بعد'],
    '/admin/gallery' => ['cms.gallery', 'gallery', 'گالری'],
    '/admin/settings' => ['settings.manage', 'settings', 'تنظیمات'],
    '/admin/analytics' => ['analytics.view', 'analytics', 'آمار بازدید'],
    '/admin/media' => ['cms.pages', 'media', 'رسانه'],
    '/admin/imports' => ['patients.import', 'imports', 'ورود اکسل'],
    '/admin/audit' => ['admins.manage', 'audit', 'گزارش فعالیت'],
];

foreach ($adminCrud as $path => [$perm, $viewName, $title]) {
    $router->get($path, static function () use ($perm, $viewName, $title): void {
        Auth::requireAdmin($perm);
        $data = ['title' => $title, 'items' => []];
        try {
            $data['items'] = match ($viewName) {
                'pages' => db()->query('SELECT * FROM pages ORDER BY title')->fetchAll(),
                'services' => db()->query('SELECT * FROM services WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll(),
                'doctors' => db()->query('SELECT * FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll(),
                'patients' => db()->query('SELECT * FROM patients WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 100')->fetchAll(),
                'appointments' => (static function () {
                    $pid = (int) ($_GET['patient_id'] ?? 0);
                    $date = trim((string) ($_GET['date'] ?? ''));
                    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        $date = '';
                    }
                    $sql = "SELECT a.*, CONCAT(p.first_name,' ',p.last_name) patient_name, s.name service_name,
                            CONCAT(d.first_name,' ',d.last_name) doctor_name
                     FROM appointments a
                     JOIN patients p ON p.id=a.patient_id
                     JOIN services s ON s.id=a.service_id
                     JOIN doctors d ON d.id=a.doctor_id
                     WHERE a.deleted_at IS NULL";
                    $params = [];
                    if ($pid > 0) {
                        $sql .= ' AND a.patient_id=?';
                        $params[] = $pid;
                    }
                    if ($date !== '') {
                        $sql .= ' AND DATE(a.starts_at)=?';
                        $params[] = $date;
                    }
                    $sql .= ' ORDER BY a.starts_at DESC LIMIT 200';
                    $st = db()->prepare($sql);
                    $st->execute($params);
                    return $st->fetchAll();
                })(),
                'payments' => db()->query('SELECT * FROM payments ORDER BY id DESC LIMIT 100')->fetchAll(),
                'sms-logs' => db()->query('SELECT * FROM sms_logs ORDER BY id DESC LIMIT 100')->fetchAll(),
                'faqs' => db()->query('SELECT * FROM faqs ORDER BY sort_order, id')->fetchAll(),
                'testimonials' => db()->query('SELECT * FROM testimonials ORDER BY is_active ASC, id DESC')->fetchAll(),
                'case-studies' => db()->query('SELECT * FROM case_studies ORDER BY sort_order, id DESC')->fetchAll(),
                'gallery' => db()->query('SELECT * FROM gallery_items ORDER BY sort_order, id DESC')->fetchAll(),
                'media' => db()->query('SELECT * FROM media ORDER BY id DESC LIMIT 100')->fetchAll(),
                'imports' => db()->query('SELECT * FROM excel_imports ORDER BY id DESC LIMIT 50')->fetchAll(),
                'audit' => db()->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100')->fetchAll(),
                'analytics' => [],
                'settings' => db()->query('SELECT * FROM site_settings ORDER BY group_name, `key`')->fetchAll(),
                default => [],
            };
            if ($viewName === 'settings') {
                $branding = [
                    'logo_path' => 'images/logo.svg',
                    'favicon_path' => 'images/favicon.png',
                    'og_default_image' => 'images/about-us-image.jpg',
                ];
                $existing = [];
                foreach ($data['items'] as $row) {
                    $existing[(string) ($row['key'] ?? '')] = true;
                }
                $ins = db()->prepare(
                    'INSERT INTO site_settings (`key`, `value`, group_name) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE group_name=COALESCE(VALUES(group_name), group_name)'
                );
                foreach ($branding as $k => $v) {
                    if (!isset($existing[$k])) {
                        $ins->execute([$k, $v, 'branding']);
                    }
                }
                $data['items'] = db()->query('SELECT * FROM site_settings ORDER BY group_name, `key`')->fetchAll();
            }
            if ($viewName === 'analytics') {
                $data['total'] = (int) db()->query('SELECT COUNT(*) FROM page_views')->fetchColumn();
                $data['today'] = (int) db()->query('SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at)=CURDATE()')->fetchColumn();
                $data['week'] = (int) db()->query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= CURDATE()-INTERVAL 7 DAY')->fetchColumn();
                $data['month'] = (int) db()->query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= CURDATE()-INTERVAL 30 DAY')->fetchColumn();
                $data['top'] = db()->query('SELECT path, COUNT(*) c FROM page_views GROUP BY path ORDER BY c DESC LIMIT 15')->fetchAll();
                $data['daily'] = db()->query('SELECT DATE(viewed_at) d, COUNT(*) c FROM page_views WHERE viewed_at >= CURDATE()-INTERVAL 14 DAY GROUP BY DATE(viewed_at) ORDER BY d')->fetchAll();
            }
            if ($viewName === 'appointments') {
                $selectedPatientId = (int) ($_GET['patient_id'] ?? 0);
                $filterDate = trim((string) ($_GET['date'] ?? ''));
                if ($filterDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
                    $filterDate = '';
                }
                $data['selected_patient_id'] = $selectedPatientId;
                $data['selected_patient'] = null;
                $data['filter_date'] = $filterDate;
                $data['doctors'] = db()->query(
                    "SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_order, id"
                )->fetchAll();
                $data['services'] = db()->query(
                    "SELECT id, name, duration_minutes, price FROM services WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_order, id"
                )->fetchAll();
                if ($selectedPatientId > 0) {
                    $st = db()->prepare(
                        "SELECT id, first_name, last_name, mobile, file_number, public_code
                         FROM patients WHERE id=? AND deleted_at IS NULL"
                    );
                    $st->execute([$selectedPatientId]);
                    $data['selected_patient'] = $st->fetch() ?: null;
                    if (!$data['selected_patient']) {
                        $data['selected_patient_id'] = 0;
                        $selectedPatientId = 0;
                    }
                }
                $apptSvc = new AppointmentService(db());
                $apptSvc->ensureCancelSchema();
                $data['day_cancellable_count'] = $filterDate !== '' ? $apptSvc->countEligibleForDay($filterDate) : 0;
                $data['cancellable_statuses'] = AppointmentService::cancellableStatuses();
                $data['slots_url'] = url('/api/appointment/slots');
                $data['patient_search_url'] = url('/admin/patients/api/quick-search');
            }
        } catch (Throwable $e) {
            $data['error'] = config('app.debug') ? $e->getMessage() : 'خطا در بارگذاری داده';
        }
        view('admin/' . $viewName, $data);
    });
}

// Settings save
$router->post('/admin/settings', static function (): void {
    Auth::requireAdmin('settings.manage');
    Csrf::assertValid();
    $settings = $_POST['settings'] ?? [];
    if (!is_array($settings)) {
        $settings = [];
    }

    foreach (['logo_path', 'favicon_path', 'og_default_image'] as $imageKey) {
        $uploaded = store_uploaded_brand_asset('setting_file_' . $imageKey);
        if ($uploaded !== null) {
            $settings[$imageKey] = $uploaded;
        }
    }

    if ($settings !== []) {
        $stmt = db()->prepare(
            'INSERT INTO site_settings (`key`, `value`, group_name) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), group_name=COALESCE(VALUES(group_name), group_name)'
        );
        $brandingKeys = ['logo_path', 'favicon_path', 'og_default_image'];
        foreach ($settings as $key => $value) {
            $key = (string) $key;
            $group = in_array($key, $brandingKeys, true) ? 'branding' : null;
            $stmt->execute([$key, (string) $value, $group]);
        }
        audit('settings.update', 'site_settings', null, ['keys' => array_keys($settings)]);
    }
    flash('success', 'تنظیمات ذخیره شد.');
    redirect('/admin/settings');
});

// Page section edit
$router->get('/admin/pages/{id}', static function (string $id): void {
    Auth::requireAdmin('cms.pages');
    $page = db()->prepare('SELECT * FROM pages WHERE id=?');
    $page->execute([(int) $id]);
    $page = $page->fetch();
    if (!$page) {
        http_response_code(404);
        echo 'صفحه یافت نشد';
        return;
    }
    $sections = db()->prepare('SELECT * FROM page_sections WHERE page_id=? ORDER BY sort_order');
    $sections->execute([(int) $id]);
    view('admin/page-edit', [
        'title' => 'ویرایش صفحه: ' . $page['title'],
        'page' => $page,
        'sections' => $sections->fetchAll(),
    ]);
});

$router->post('/admin/pages/{id}', static function (string $id): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $stmt = db()->prepare('UPDATE pages SET title=?, meta_title=?, meta_description=?, status=?, updated_at=NOW() WHERE id=?');
    $stmt->execute([
        trim((string) ($_POST['title'] ?? '')),
        trim((string) ($_POST['meta_title'] ?? '')),
        trim((string) ($_POST['meta_description'] ?? '')),
        ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        (int) $id,
    ]);
    if (!empty($_POST['sections']) && is_array($_POST['sections'])) {
        $upd = db()->prepare('UPDATE page_sections SET title=?, subtitle=?, description=?, image=?, cta_text=?, cta_link=?, is_visible=? WHERE id=? AND page_id=?');
        foreach ($_POST['sections'] as $sid => $sec) {
            $sid = (int) $sid;
            $image = resolve_image_path('section_image_' . $sid, $sec['image'] ?? null);
            $upd->execute([
                $sec['title'] ?? null,
                $sec['subtitle'] ?? null,
                $sec['description'] ?? null,
                $image,
                $sec['cta_text'] ?? null,
                $sec['cta_link'] ?? null,
                isset($sec['is_visible']) ? 1 : 0,
                $sid,
                (int) $id,
            ]);
        }
    }
    audit('cms.page_update', 'pages', (int) $id);
    flash('success', 'صفحه ذخیره شد.');
    redirect('/admin/pages/' . $id);
});

// Service create/update
$router->post('/admin/services/save', static function (): void {
    Auth::requireAdmin('cms.services');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? '')) ?: admin_slug($name, 'service');
    $short = trim((string) ($_POST['short_description'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $image = resolve_image_path('image_file', $_POST['image'] ?? null);
    $iconUploaded = store_uploaded_brand_asset('icon_file');
    $icon = $iconUploaded ?? (trim((string) ($_POST['icon'] ?? '')) ?: null);
    $active = isset($_POST['is_active']) ? 1 : 0;
    $price = (int) ($_POST['price'] ?? 0);
    $duration = (int) ($_POST['duration_minutes'] ?? 30);
    $sort = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flash('error', 'نام خدمت الزامی است.');
        redirect('/admin/services');
    }
    if ($id > 0) {
        db()->prepare(
            'UPDATE services SET name=?, slug=?, short_description=?, description=?, image=?, icon=?, price=?, duration_minutes=?, is_active=?, sort_order=? WHERE id=?'
        )->execute([$name, $slug, $short, $description ?: null, $image, $icon, $price, $duration, $active, $sort, $id]);
        audit('cms.service_update', 'services', $id);
    } else {
        db()->prepare(
            'INSERT INTO services (name, slug, short_description, description, image, icon, price, duration_minutes, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$name, $slug, $short, $description ?: null, $image, $icon, $price, $duration, $active, $sort]);
        audit('cms.service_create', 'services', (int) db()->lastInsertId());
    }
    flash('success', 'خدمت ذخیره شد.');
    redirect('/admin/services');
});

$router->get('/admin/doctors', static function (): void {
    Auth::requireAdmin('cms.doctors');
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = (string) ($_GET['status'] ?? '');
    $where = ['deleted_at IS NULL'];
    $params = [];
    if ($q !== '') {
        $where[] = '(first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, \' \', last_name) LIKE ? OR specialty LIKE ?)';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like];
    }
    if ($status === 'active') {
        $where[] = 'is_active=1';
    } elseif ($status === 'inactive') {
        $where[] = 'is_active=0';
    }
    $sql = 'SELECT * FROM doctors WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $media = [];
    try {
        $media = db()->query('SELECT path, original_name FROM media ORDER BY id DESC LIMIT 40')->fetchAll();
    } catch (Throwable) {
        $media = [];
    }
    view('admin/doctors', [
        'title' => 'تیم پزشکی',
        'items' => $stmt->fetchAll(),
        'media' => $media,
        'filters' => ['q' => $q, 'status' => $status],
    ]);
});

$router->post('/admin/doctors/save', static function (): void {
    Auth::requireAdmin('cms.doctors');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? '')) ?: admin_slug($first . '-' . $last, 'doctor');
    $specialty = trim((string) ($_POST['specialty'] ?? ''));
    $biography = trim((string) ($_POST['biography'] ?? ''));
    $photo = resolve_image_path('photo_file', $_POST['photo'] ?? null);
    $active = isset($_POST['is_active']) ? 1 : 0;
    $sort = (int) ($_POST['sort_order'] ?? 0);
    if ($id <= 0 && $sort === 0) {
        $sort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM doctors WHERE deleted_at IS NULL')->fetchColumn();
    }
    if ($first === '' || $last === '') {
        flash('error', 'نام و نام خانوادگی الزامی است.');
        redirect('/admin/doctors');
    }
    if ($id > 0) {
        db()->prepare(
            'UPDATE doctors SET first_name=?, last_name=?, slug=?, specialty=?, biography=?, photo=?, is_active=?, sort_order=? WHERE id=?'
        )->execute([$first, $last, $slug, $specialty, $biography ?: null, $photo, $active, $sort, $id]);
        audit('cms.doctor_update', 'doctors', $id);
    } else {
        db()->prepare(
            'INSERT INTO doctors (first_name, last_name, slug, specialty, biography, photo, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$first, $last, $slug, $specialty, $biography ?: null, $photo, $active, $sort]);
        audit('cms.doctor_create', 'doctors', (int) db()->lastInsertId());
    }
    flash('success', 'پزشک ذخیره شد.');
    redirect('/admin/doctors');
});

$router->post('/admin/doctors/action', static function (): void {
    Auth::requireAdmin('cms.doctors');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $act = (string) ($_POST['action'] ?? '');
    if ($id <= 0) {
        redirect('/admin/doctors');
    }
    $row = db()->prepare('SELECT * FROM doctors WHERE id=? AND deleted_at IS NULL');
    $row->execute([$id]);
    $doctor = $row->fetch();
    if (!$doctor) {
        flash('error', 'پزشک یافت نشد.');
        redirect('/admin/doctors');
    }
    if ($act === 'archive') {
        db()->prepare('UPDATE doctors SET deleted_at=NOW(), is_active=0 WHERE id=?')->execute([$id]);
        audit('cms.doctor_archive', 'doctors', $id);
        flash('success', 'عضو تیم بایگانی شد و از صفحه اصلی حذف گردید.');
    } elseif ($act === 'toggle') {
        db()->prepare('UPDATE doctors SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
        audit('cms.doctor_toggle', 'doctors', $id);
        flash('success', 'وضعیت نمایش در صفحه اصلی به‌روز شد.');
    } elseif ($act === 'up' || $act === 'down') {
        $list = db()->query('SELECT id, sort_order FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC')->fetchAll();
        $idx = null;
        foreach ($list as $i => $item) {
            if ((int) $item['id'] === $id) {
                $idx = $i;
                break;
            }
        }
        $swap = $act === 'up' ? ($idx - 1) : ($idx + 1);
        if ($idx !== null && isset($list[$swap])) {
            $a = $list[$idx];
            $b = $list[$swap];
            db()->prepare('UPDATE doctors SET sort_order=? WHERE id=?')->execute([(int) $b['sort_order'], (int) $a['id']]);
            db()->prepare('UPDATE doctors SET sort_order=? WHERE id=?')->execute([(int) $a['sort_order'], (int) $b['id']]);
            if ((int) $a['sort_order'] === (int) $b['sort_order']) {
                db()->prepare('UPDATE doctors SET sort_order=? WHERE id=?')->execute([$idx, (int) $a['id']]);
                db()->prepare('UPDATE doctors SET sort_order=? WHERE id=?')->execute([$swap, (int) $b['id']]);
            }
            audit('cms.doctor_reorder', 'doctors', $id);
            flash('success', 'ترتیب نمایش به‌روز شد.');
        }
    }
    redirect('/admin/doctors');
});

$router->post('/admin/case-studies/save', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? '')) ?: admin_slug($title, 'case');
    $description = trim((string) ($_POST['description'] ?? ''));
    $before = resolve_image_path('before_file', $_POST['before_image'] ?? null);
    $after = resolve_image_path('after_file', $_POST['after_image'] ?? null);
    $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
    $sort = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '') {
        flash('error', 'عنوان الزامی است.');
        redirect('/admin/case-studies');
    }
    try {
        if ($id > 0) {
            db()->prepare(
                'UPDATE case_studies SET title=?, slug=?, description=?, before_image=?, after_image=?, status=?, sort_order=? WHERE id=?'
            )->execute([$title, $slug, $description ?: null, $before, $after, $status, $sort, $id]);
            audit('cms.case_update', 'case_studies', $id);
        } else {
            db()->prepare(
                'INSERT INTO case_studies (title, slug, description, before_image, after_image, status, sort_order) VALUES (?,?,?,?,?,?,?)'
            )->execute([$title, $slug, $description ?: null, $before, $after, $status, $sort]);
            audit('cms.case_create', 'case_studies', (int) db()->lastInsertId());
        }
        flash('success', 'نمونه کار ذخیره شد.');
    } catch (Throwable $e) {
        flash('error', config('app.debug') ? $e->getMessage() : 'ذخیره با خطا مواجه شد (نامک تکراری؟).');
    }
    redirect('/admin/case-studies');
});

$router->post('/admin/case-studies/delete', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM case_studies WHERE id=?')->execute([$id]);
        audit('cms.case_delete', 'case_studies', $id);
        flash('success', 'نمونه کار حذف شد.');
    }
    redirect('/admin/case-studies');
});

$router->post('/admin/testimonials/save', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['patient_name'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $avatar = resolve_image_path('avatar_file', $_POST['avatar'] ?? null);
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '' || $content === '') {
        flash('error', 'نام و متن نظر الزامی است.');
        redirect('/admin/testimonials');
    }
    if ($id > 0) {
        db()->prepare(
            'UPDATE testimonials SET patient_name=?, content=?, rating=?, avatar=?, is_active=?, sort_order=? WHERE id=?'
        )->execute([$name, $content, $rating, $avatar, $active, $sort, $id]);
        audit('cms.testimonial_update', 'testimonials', $id);
    } else {
        db()->prepare(
            'INSERT INTO testimonials (patient_name, content, rating, avatar, is_active, sort_order) VALUES (?,?,?,?,?,?)'
        )->execute([$name, $content, $rating, $avatar, $active, $sort]);
        audit('cms.testimonial_create', 'testimonials', (int) db()->lastInsertId());
    }
    flash('success', 'نظر ذخیره شد.');
    redirect('/admin/testimonials');
});

$router->post('/admin/testimonials/toggle', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE testimonials SET is_active = IF(is_active=1, 0, 1) WHERE id=?')->execute([$id]);
        audit('cms.testimonial_toggle', 'testimonials', $id);
        flash('success', 'وضعیت نظر به‌روز شد.');
    }
    redirect('/admin/testimonials');
});

$router->post('/admin/testimonials/delete', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM testimonials WHERE id=?')->execute([$id]);
        audit('cms.testimonial_delete', 'testimonials', $id);
        flash('success', 'نظر حذف شد.');
    }
    redirect('/admin/testimonials');
});

$router->post('/admin/faqs/save', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $question = trim((string) ($_POST['question'] ?? ''));
    $answer = trim((string) ($_POST['answer'] ?? ''));
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($question === '' || $answer === '') {
        flash('error', 'سؤال و پاسخ الزامی است.');
        redirect('/admin/faqs');
    }
    if ($id > 0) {
        db()->prepare('UPDATE faqs SET question=?, answer=?, is_active=?, sort_order=? WHERE id=?')
            ->execute([$question, $answer, $active, $sort, $id]);
        audit('cms.faq_update', 'faqs', $id);
    } else {
        db()->prepare('INSERT INTO faqs (question, answer, is_active, sort_order) VALUES (?,?,?,?)')
            ->execute([$question, $answer, $active, $sort]);
        audit('cms.faq_create', 'faqs', (int) db()->lastInsertId());
    }
    flash('success', 'سؤال ذخیره شد.');
    redirect('/admin/faqs');
});

$router->post('/admin/faqs/delete', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM faqs WHERE id=?')->execute([$id]);
        audit('cms.faq_delete', 'faqs', $id);
        flash('success', 'سؤال حذف شد.');
    }
    redirect('/admin/faqs');
});

$router->post('/admin/gallery/save', static function (): void {
    Auth::requireAdmin('cms.gallery');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $type = ($_POST['type'] ?? 'image') === 'video' ? 'video' : 'image';
    $media = resolve_image_path('media_file', $_POST['media_path'] ?? null);
    $thumb = resolve_image_path('thumb_file', $_POST['thumb_path'] ?? null);
    $category = trim((string) ($_POST['category'] ?? '')) ?: null;
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($media === null || $media === '') {
        flash('error', 'مسیر رسانه الزامی است.');
        redirect('/admin/gallery');
    }
    if ($id > 0) {
        db()->prepare(
            'UPDATE gallery_items SET type=?, title=?, media_path=?, thumb_path=?, category=?, sort_order=?, is_active=? WHERE id=?'
        )->execute([$type, $title ?: null, $media, $thumb, $category, $sort, $active, $id]);
        audit('cms.gallery_update', 'gallery_items', $id);
    } else {
        db()->prepare(
            'INSERT INTO gallery_items (type, title, media_path, thumb_path, category, sort_order, is_active) VALUES (?,?,?,?,?,?,?)'
        )->execute([$type, $title ?: null, $media, $thumb, $category, $sort, $active]);
        audit('cms.gallery_create', 'gallery_items', (int) db()->lastInsertId());
    }
    flash('success', 'آیتم گالری ذخیره شد.');
    redirect('/admin/gallery');
});

$router->post('/admin/gallery/delete', static function (): void {
    Auth::requireAdmin('cms.gallery');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM gallery_items WHERE id=?')->execute([$id]);
        audit('cms.gallery_delete', 'gallery_items', $id);
        flash('success', 'آیتم گالری حذف شد.');
    }
    redirect('/admin/gallery');
});

$router->post('/admin/appointments/create', static function (): void {
    Auth::requireAdmin('appointments.manage');
    Csrf::assertValid();

    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $date = trim((string) ($_POST['date'] ?? ''));
    $time = trim((string) ($_POST['time'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $paymentStatus = (string) ($_POST['payment_status'] ?? 'unpaid');
    $returnPatientId = (int) ($_POST['return_patient_id'] ?? 0);
    $returnTo = (string) ($_POST['return_to'] ?? '');

    // patient_id is who is being booked. It must not become the list filter.
    // Only an explicit return_to or return_patient_id keeps a narrower screen.
    if ($returnTo !== '' && preg_match('#^/admin/patients/\d+(\?[\w=&%-]*)?$#', $returnTo)) {
        $redirectTo = $returnTo;
    } elseif ($returnPatientId > 0) {
        $redirectTo = '/admin/appointments?patient_id=' . $returnPatientId;
    } else {
        $redirectTo = '/admin/appointments';
    }

    if (
        $patientId <= 0
        || $doctorId <= 0
        || $serviceId <= 0
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !preg_match('/^\d{2}:\d{2}$/', $time)
    ) {
        flash('error', 'لطفاً بیمار، پزشک، خدمت، تاریخ و ساعت را کامل وارد کنید.');
        redirect($redirectTo);
    }

    $startsAt = $date . ' ' . $time . ':00';
    $adminId = (int) Auth::adminId();
    $result = (new AppointmentService(db()))->createConfirmedByAdmin(
        $patientId,
        $doctorId,
        $serviceId,
        $startsAt,
        $adminId,
        $notes !== '' ? $notes : null,
        $paymentStatus
    );

    if (!($result['ok'] ?? false)) {
        flash('error', $result['message'] ?? 'ثبت نوبت ناموفق بود.');
        redirect($redirectTo);
    }

    audit('appointment.create', 'appointments', (int) ($result['appointment_id'] ?? 0), [
        'patient_id' => $patientId,
        'doctor_id' => $doctorId,
        'service_id' => $serviceId,
        'starts_at' => $startsAt,
        'source' => 'admin',
    ]);
    if (!empty($result['sms_sent'])) {
        flash('success', 'نوبت با وضعیت تأییدشده ثبت شد و پیامک تأیید ارسال شد.');
    } elseif (!empty($result['sms_duplicate'])) {
        flash('success', 'نوبت ثبت شد. پیامک تأیید قبلاً ارسال شده است.');
    } else {
        flash('success', 'نوبت با موفقیت ثبت شد، اما ارسال پیامک تأیید با خطا مواجه شد.');
    }
    redirect($redirectTo);
});

$router->post('/admin/appointments/status', static function (): void {
    Auth::requireAdmin('appointments.manage');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['confirmed', 'completed', 'cancelled', 'no_show'];
    if (!in_array($status, $allowed, true)) {
        flash('error', 'وضعیت نامعتبر');
        redirect('/admin/appointments');
    }

    // Dedicated cancel path with SMS + idempotency when status=cancelled
    if ($status === 'cancelled') {
        $result = (new AppointmentService(db()))->cancelAppointment(
            $id,
            (int) Auth::adminId(),
            null,
            true
        );
        audit('appointment.cancel', 'appointments', $id, [
            'via' => 'status_dropdown',
            'sms_sent' => $result['sms_sent'] ?? false,
            'sms_status' => $result['sms_status'] ?? null,
        ]);
        if (!($result['ok'] ?? false) && ($result['status'] ?? '') !== 'already_cancelled') {
            flash('error', $result['message'] ?? 'لغو نوبت ناموفق بود.');
        } else {
            flash('success', appointment_cancel_result_message($result, true));
        }
        redirect('/admin/appointments');
    }

    $cur = db()->prepare('SELECT status FROM appointments WHERE id=? AND deleted_at IS NULL');
    $cur->execute([$id]);
    $from = $cur->fetchColumn();
    if ($from === false) {
        flash('error', 'نوبت یافت نشد.');
        redirect('/admin/appointments');
    }
    db()->prepare('UPDATE appointments SET status=? WHERE id=? AND deleted_at IS NULL')->execute([$status, $id]);
    db()->prepare('INSERT INTO appointment_status_history (appointment_id, from_status, to_status, changed_by_admin) VALUES (?,?,?,?)')
        ->execute([$id, $from, $status, Auth::adminId()]);
    if (in_array($status, ['cancelled', 'no_show', 'expired'], true)) {
        (new \Sarva\Services\SmsService(db()))->cancelPendingForAppointment($id);
    }
    audit('appointment.status', 'appointments', $id, ['to' => $status]);
    flash('success', 'وضعیت نوبت به‌روز شد.');
    redirect('/admin/appointments');
});

$router->post('/admin/appointments/cancel', static function (): void {
    Auth::requireAdmin('appointments.manage');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $sendSms = isset($_POST['send_sms']) && (string) $_POST['send_sms'] !== '0';
    $redirectTo = appointments_cancel_redirect();

    $result = (new AppointmentService(db()))->cancelAppointment(
        $id,
        (int) Auth::adminId(),
        $reason !== '' ? $reason : null,
        $sendSms
    );

    audit('appointment.cancel', 'appointments', $id, [
        'status' => $result['status'] ?? null,
        'send_sms' => $sendSms,
        'sms_sent' => $result['sms_sent'] ?? false,
        'sms_status' => $result['sms_status'] ?? null,
    ]);

    if (!($result['ok'] ?? false) && ($result['status'] ?? '') !== 'already_cancelled') {
        flash('error', $result['message'] ?? 'لغو نوبت ناموفق بود.');
        redirect($redirectTo);
    }

    flash('success', appointment_cancel_result_message($result, $sendSms));
    redirect($redirectTo);
});

$router->post('/admin/appointments/cancel-bulk', static function (): void {
    Auth::requireAdmin('appointments.manage');
    Csrf::assertValid();
    $idsRaw = $_POST['appointment_ids'] ?? [];
    if (!is_array($idsRaw)) {
        $idsRaw = [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $idsRaw), static fn (int $v): bool => $v > 0)));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $sendSms = isset($_POST['send_sms']) && (string) $_POST['send_sms'] !== '0';
    $redirectTo = appointments_cancel_redirect();

    if ($ids === []) {
        flash('error', 'هیچ نوبتی انتخاب نشده است.');
        redirect($redirectTo);
    }
    if (count($ids) > 500) {
        flash('error', 'حداکثر ۵۰۰ نوبت در هر درخواست قابل لغو است.');
        redirect($redirectTo);
    }

    $summary = (new AppointmentService(db()))->cancelAppointments(
        $ids,
        (int) Auth::adminId(),
        $reason !== '' ? $reason : null,
        $sendSms
    );

    audit('appointment.cancel_bulk', 'appointments', null, [
        'examined' => $summary['examined'],
        'cancelled' => $summary['cancelled'],
        'sms_sent' => $summary['sms_sent'],
        'sms_failed' => $summary['sms_failed'],
        'sms_duplicate' => $summary['sms_duplicate'],
        'send_sms' => $sendSms,
    ]);

    flash('success', appointments_cancel_summary_message($summary, $sendSms));
    redirect($redirectTo);
});

$router->post('/admin/appointments/cancel-day', static function (): void {
    Auth::requireAdmin('appointments.manage');
    Csrf::assertValid();
    $date = trim((string) ($_POST['date'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $sendSms = isset($_POST['send_sms']) && (string) $_POST['send_sms'] !== '0';
    $redirectTo = appointments_cancel_redirect($date);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        flash('error', 'تاریخ نامعتبر است.');
        redirect($redirectTo);
    }

    $summary = (new AppointmentService(db()))->cancelAppointmentsForDay(
        $date,
        (int) Auth::adminId(),
        $reason !== '' ? $reason : null,
        $sendSms
    );

    audit('appointment.cancel_day', 'appointments', null, [
        'date' => $date,
        'examined' => $summary['examined'] ?? 0,
        'cancelled' => $summary['cancelled'] ?? 0,
        'sms_sent' => $summary['sms_sent'] ?? 0,
        'sms_failed' => $summary['sms_failed'] ?? 0,
        'sms_duplicate' => $summary['sms_duplicate'] ?? 0,
        'send_sms' => $sendSms,
    ]);

    if (($summary['examined'] ?? 0) === 0) {
        flash('error', 'نوبت قابل لغوی برای این روز یافت نشد.');
        redirect($redirectTo);
    }

    flash('success', appointments_cancel_summary_message($summary, $sendSms));
    redirect($redirectTo);
});

$router->post('/admin/appointments/delete', static function (): void {
    Auth::requireAdmin('appointments.manage');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $returnTo = (string) ($_POST['return_to'] ?? '');
    if ($returnTo !== '' && preg_match('#^/admin/patients/\d+(\?[\w=&%-]*)?$#', $returnTo)) {
        $redirectTo = $returnTo;
    } elseif ($patientId > 0) {
        $redirectTo = '/admin/appointments?patient_id=' . $patientId;
    } else {
        $redirectTo = '/admin/appointments';
    }

    if ($id <= 0) {
        flash('error', 'نوبت نامعتبر است.');
        redirect($redirectTo);
    }

    $st = db()->prepare('SELECT status FROM appointments WHERE id=? AND deleted_at IS NULL');
    $st->execute([$id]);
    $from = $st->fetchColumn();
    if ($from === false) {
        flash('error', 'نوبت یافت نشد یا قبلاً حذف شده است.');
        redirect($redirectTo);
    }

    $toStatus = in_array((string) $from, ['cancelled', 'expired', 'no_show'], true)
        ? (string) $from
        : 'cancelled';

    db()->prepare(
        'UPDATE appointments SET status=?, deleted_at=NOW() WHERE id=? AND deleted_at IS NULL'
    )->execute([$toStatus, $id]);

    if ($toStatus !== (string) $from) {
        db()->prepare(
            'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, changed_by_admin, note) VALUES (?,?,?,?,?)'
        )->execute([$id, $from, $toStatus, Auth::adminId(), 'حذف نوبت از پنل مدیریت']);
    }

    (new \Sarva\Services\SmsService(db()))->cancelPendingForAppointment($id);
    audit('appointment.delete', 'appointments', $id, ['from' => $from, 'to' => $toStatus]);
    flash('success', 'نوبت حذف شد.');
    redirect($redirectTo);
});

// Excel import upload (preview-ready; full mapping when file provided)
$router->post('/admin/imports/upload', static function (): void {
    Auth::requireAdmin('patients.import');
    Csrf::assertValid();
    if (empty($_FILES['excel']['tmp_name'])) {
        flash('error', 'فایل انتخاب نشده است.');
        redirect('/admin/imports');
    }
    $file = $_FILES['excel'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        flash('error', 'بارگذاری فایل ناموفق بود.');
        redirect('/admin/imports');
    }
    if ((int) ($file['size'] ?? 0) > 15 * 1024 * 1024) {
        flash('error', 'حجم فایل نباید بیشتر از ۱۵ مگابایت باشد.');
        redirect('/admin/imports');
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        flash('error', 'فقط فایل اکسل یا CSV مجاز است.');
        redirect('/admin/imports');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $allowedMime = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/octet-stream',
        'text/csv',
        'text/plain',
        'application/csv',
    ];
    if (!in_array($mime, $allowedMime, true)) {
        flash('error', 'نوع فایل مجاز نیست.');
        redirect('/admin/imports');
    }
    $dir = dirname(__DIR__) . '/storage/imports';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $hash = hash_file('sha256', $file['tmp_name']) ?: null;
    $safe = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . '/' . $safe);
    try {
        db()->prepare(
            'INSERT INTO excel_imports (filename, original_name, file_hash, uploaded_by, status, import_mode) VALUES (?,?,?,?,?,?)'
        )->execute([$safe, (string) $file['name'], $hash, Auth::adminId(), 'uploaded', 'add_new']);
    } catch (Throwable) {
        db()->prepare('INSERT INTO excel_imports (filename, uploaded_by, status) VALUES (?,?,?)')
            ->execute([$safe, Auth::adminId(), 'uploaded']);
    }
    $importId = (int) db()->lastInsertId();
    audit('patients.import_upload', 'excel_imports', $importId, ['original' => $file['name'], 'hash' => $hash]);
    flash('success', 'فایل بارگذاری شد. واردسازی یک‌باره را شروع کنید؛ بیماران بعدی را دستی ثبت کنید.');
    redirect('/admin/imports/' . $importId);
});

$router->get('/admin/imports/{id}', static function (string $id): void {
    Auth::requireAdmin('patients.import');
    @ini_set('max_execution_time', '90');
    @ini_set('memory_limit', '256M');

    $import = db()->prepare('SELECT * FROM excel_imports WHERE id=?');
    $import->execute([(int) $id]);
    $import = $import->fetch();
    if (!$import) {
        flash('error', 'ورود یافت نشد.');
        redirect('/admin/imports');
    }
    $path = dirname(__DIR__) . '/storage/imports/' . $import['filename'];
    $status = (string) ($import['status'] ?? '');
    $forceExcel = isset($_GET['remap']) || isset($_GET['reopen']);
    $completed = in_array($status, ['completed', 'failed', 'processing'], true);

    $inspect = ['ok' => true, 'sheets' => [], 'previous_imports' => [], 'skipped_excel' => false];
    // Completed / failed / mid-run: show saved report instantly — don't re-parse the whole Excel.
    if ($completed && !$forceExcel) {
        $inspect['skipped_excel'] = true;
        if (!empty($import['file_hash'])) {
            try {
                $st = db()->prepare(
                    'SELECT id, original_name, filename, status, created_at, imported_rows, duplicate_rows, invalid_rows, conflict_rows, total_rows
                     FROM excel_imports WHERE file_hash=? ORDER BY id DESC LIMIT 5'
                );
                $st->execute([(string) $import['file_hash']]);
                $inspect['previous_imports'] = $st->fetchAll() ?: [];
            } catch (Throwable) {
            }
        }
    } elseif (is_file($path) && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
        $inspect = (new \Sarva\Services\PatientImportService(db()))->inspect($path);
    } elseif (!is_file($path)) {
        $inspect = ['ok' => false, 'sheets' => [], 'message' => 'فایل اکسل روی سرور یافت نشد.'];
    }

    $conflicts = [];
    try {
        $st = db()->prepare('SELECT * FROM excel_import_conflicts WHERE import_id=? ORDER BY id DESC LIMIT 100');
        $st->execute([(int) $id]);
        $conflicts = $st->fetchAll() ?: [];
    } catch (Throwable) {
    }
    view('admin/import-preview', [
        'title' => $completed ? 'نتیجه ورود اکسل' : 'پیش‌نمایش ورود اکسل',
        'import' => $import,
        'inspect' => $inspect,
        'conflicts' => $conflicts,
        'csrf' => csrf_token(),
    ]);
});

/** Prepare Excel → NDJSON cache (short request). */
$router->post('/admin/imports/{id}/prepare', static function (string $id): void {
    Auth::requireAdmin('patients.import');
    Csrf::assertValid();
    header('Accept: application/json');

    $import = db()->prepare('SELECT * FROM excel_imports WHERE id=?');
    $import->execute([(int) $id]);
    $import = $import->fetch();
    if (!$import) {
        json_response(['ok' => false, 'message' => 'ورود یافت نشد.'], 404);
    }
    $path = dirname(__DIR__) . '/storage/imports/' . $import['filename'];
    if (!is_file($path)) {
        json_response(['ok' => false, 'message' => 'فایل اکسل روی سرور یافت نشد.'], 400);
    }

    $svc = new \Sarva\Services\PatientImportService(db());
    $mapping = $svc->sarvaParvandehMapping();
    if (empty($_POST['use_sarva_mapping'])) {
        $mapping = [];
        foreach (['mobile', 'first_name', 'last_name', 'father_name', 'file_number', 'national_id', 'email', 'address', 'landline', 'referrer', 'birth_year_jalali', 'birth_date', 'gender'] as $field) {
            $v = trim((string) ($_POST['map_' . $field] ?? ''));
            if ($v !== '') {
                $mapping[$field] = $v;
            }
        }
        if ($mapping === []) {
            $mapping = $svc->sarvaParvandehMapping();
        }
    }
    $sheetIndex = (int) ($_POST['sheet_index'] ?? 0);

    $result = $svc->prepareBulk((int) $id, $path, $sheetIndex, $mapping);
    json_response($result, !empty($result['ok']) ? 200 : 500);
});

/** Process next chunk of prepared rows (short request). */
$router->post('/admin/imports/{id}/chunk', static function (string $id): void {
    Auth::requireAdmin('patients.import');
    Csrf::assertValid();
    header('Accept: application/json');

    $chunkSize = (int) ($_POST['chunk_size'] ?? 200);
    $svc = new \Sarva\Services\PatientImportService(db());
    $result = $svc->processChunk((int) $id, $chunkSize);
    json_response($result, !empty($result['ok']) ? 200 : 500);
});

$router->post('/admin/imports/{id}/run', static function (string $id): void {
    Auth::requireAdmin('patients.import');
    Csrf::assertValid();
    // Legacy full-run kept only for preview_only; real import uses chunked endpoints.
    $import = db()->prepare('SELECT * FROM excel_imports WHERE id=?');
    $import->execute([(int) $id]);
    $import = $import->fetch();
    if (!$import) {
        flash('error', 'ورود یافت نشد.');
        redirect('/admin/imports');
    }
    $path = dirname(__DIR__) . '/storage/imports/' . $import['filename'];
    if (!is_file($path)) {
        flash('error', 'فایل اکسل روی سرور یافت نشد.');
        redirect('/admin/imports/' . $id);
    }
    $svc = new \Sarva\Services\PatientImportService(db());
    $mapping = [];
    foreach (['mobile', 'first_name', 'last_name', 'father_name', 'file_number', 'national_id', 'email', 'address', 'landline', 'referrer', 'birth_year_jalali', 'birth_date', 'gender'] as $field) {
        $v = trim((string) ($_POST['map_' . $field] ?? ''));
        if ($v !== '') {
            $mapping[$field] = $v;
        }
    }
    $sheetIndex = (int) ($_POST['sheet_index'] ?? 0);
    if (isset($_POST['use_sarva_mapping']) || $mapping === []) {
        $mapping = $svc->sarvaParvandehMapping();
    }
    if (isset($_POST['preview_only'])) {
        @set_time_limit(120);
        $result = $svc->preview($path, $sheetIndex, $mapping, 'add_new');
        $_SESSION['_import_preview'] = $result['report'] ?? [];
        flash('success', 'پیش‌نمایش آماده است — هنوز چیزی در پایگاه ذخیره نشده.');
        redirect('/admin/imports/' . $id);
    }
    flash('success', 'واردسازی از طریق پردازش تکه‌ای انجام می‌شود. از دکمه «شروع واردسازی یک‌باره» استفاده کنید.');
    redirect('/admin/imports/' . $id);
});

// Media upload
$router->post('/admin/media/upload', static function (): void {
    Auth::requireAdmin('cms.pages');
    Csrf::assertValid();
    $path = store_uploaded_image('file', true, 5 * 1024 * 1024);
    if ($path === null) {
        flash('error', 'فایل تصویر نامعتبر یا بزرگ‌تر از ۵ مگابایت است.');
        redirect('/admin/media');
    }
    flash('success', 'فایل بارگذاری و به WebP تبدیل شد.');
    redirect('/admin/media');
});

$router->get('/admin/maintenance/history', static function (): void {
    Auth::requireAdmin('admins.manage');
    $range = (string) ($_GET['older_than'] ?? '90_days');
    if (!array_key_exists($range, \Sarva\Services\HistoryCleanupService::RANGES)) {
        $range = '90_days';
    }
    $svc = new \Sarva\Services\HistoryCleanupService(db());
    $cutoff = \Sarva\Services\HistoryCleanupService::cutoffForRange($range, \Sarva\Services\SmsAutomationService::appNow());
    $stats = $svc->stats($cutoff);
    $result = $_SESSION['_history_cleanup_result'] ?? null;
    unset($_SESSION['_history_cleanup_result']);
    $preview = $_SESSION['_history_cleanup_preview'] ?? null;
    unset($_SESSION['_history_cleanup_preview']);
    $selected = is_array($preview['categories'] ?? null)
        ? $preview['categories']
        : array_values(array_filter(
            array_keys(\Sarva\Services\HistoryCleanupService::categories()),
            static fn (string $key): bool => $key !== 'page_views'
        ));
    view('admin/history-cleanup', [
        'title' => 'پاک‌سازی تاریخچه‌ها',
        'categories' => \Sarva\Services\HistoryCleanupService::categories(),
        'stats' => $stats,
        'result' => is_array($result) ? $result : null,
        'preview' => is_array($preview) ? $preview : null,
        'selected' => $selected,
        'range' => $range,
    ]);
});

$router->post('/admin/maintenance/history/cleanup', static function (): void {
    Auth::requireAdmin('admins.manage');
    Csrf::assertValid();
    $range = (string) ($_POST['older_than'] ?? '');
    if (!array_key_exists($range, \Sarva\Services\HistoryCleanupService::RANGES)) {
        flash('error', 'بازه زمانی نامعتبر است.');
        redirect('/admin/maintenance/history');
    }
    $raw = $_POST['categories'] ?? [];
    if (!is_array($raw)) {
        flash('error', 'درخواست نامعتبر است.');
        redirect('/admin/maintenance/history?older_than=' . rawurlencode($range));
    }
    $categories = \Sarva\Services\HistoryCleanupService::normalizeCategories(array_map('strval', $raw));
    if ($categories === []) {
        flash('error', 'هیچ تاریخچه معتبری انتخاب نشده است.');
        redirect('/admin/maintenance/history?older_than=' . rawurlencode($range));
    }
    $svc = new \Sarva\Services\HistoryCleanupService(db());
    $cutoff = \Sarva\Services\HistoryCleanupService::cutoffForRange($range, \Sarva\Services\SmsAutomationService::appNow());
    $back = '/admin/maintenance/history?older_than=' . rawurlencode($range);
    if ((string) ($_POST['action'] ?? '') === 'preview') {
        $counts = [];
        foreach ($categories as $category) {
            $counts[$category] = $svc->countEligible($category, $cutoff);
        }
        $_SESSION['_history_cleanup_preview'] = [
            'categories' => $categories,
            'counts' => $counts,
            'range' => $range,
        ];
        redirect($back);
    }
    if (
        \Sarva\Services\HistoryCleanupService::requiresPhrase($range)
        && !\Sarva\Services\HistoryCleanupService::phraseMatches((string) ($_POST['confirmation'] ?? ''))
    ) {
        flash('error', 'برای حذف همه باید عبارت «حذف همه تاریخچه‌ها» را دقیقاً وارد کنید.');
        redirect($back);
    }
    $deleted = $svc->cleanupSelected($categories, $cutoff);
    audit('maintenance.history_cleanup', 'maintenance', null, [
        'admin_id' => $_SESSION['admin_id'] ?? null,
        'categories' => $categories,
        'deleted_counts' => $deleted,
        'older_than' => $range,
    ]);
    $_SESSION['_history_cleanup_result'] = $deleted;
    flash('success', 'پاک‌سازی انجام شد.');
    redirect($back);
});
