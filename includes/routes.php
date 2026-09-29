<?php

declare(strict_types=1);

use Sarva\Core\Auth;
use Sarva\Core\Csrf;
use Sarva\Core\Database;
use Sarva\Core\Router;
use Sarva\Payment\PaymentManager;
use Sarva\Services\AppointmentService;
use Sarva\Services\OtpService;

/** @var Router $router */

$router->get('/', static function (): void {
    track_page_view('/');
    $sections = [];
    $services = $doctors = $testimonials = $faqs = $latestArticles = $transformations = [];
    if (Database::connected()) {
        try {
            $page = db()->query("SELECT * FROM pages WHERE slug='home' LIMIT 1")->fetch();
            if ($page) {
                $stmt = db()->prepare('SELECT * FROM page_sections WHERE page_id = ? AND is_visible = 1 ORDER BY sort_order');
                $stmt->execute([(int) $page['id']]);
                foreach ($stmt->fetchAll() as $row) {
                    $sections[$row['section_key']] = $row;
                }
            }
        } catch (Throwable) {
            // keep empty sections
        }
        try {
            $services = db()->query('SELECT * FROM services WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order LIMIT 6')->fetchAll();
        } catch (Throwable) {
            $services = [];
        }
        try {
            $doctors = db()->query('SELECT * FROM doctors WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order, id')->fetchAll();
        } catch (Throwable) {
            $doctors = [];
        }
        try {
            $testimonials = db()->query('SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order LIMIT 20')->fetchAll();
        } catch (Throwable) {
            $testimonials = [];
        }
        try {
            $faqs = db()->query('SELECT * FROM faqs WHERE is_active=1 ORDER BY sort_order LIMIT 5')->fetchAll();
        } catch (Throwable) {
            $faqs = [];
        }
        try {
            $latestArticles = (new \Sarva\Services\ArticleService(db()))->listPublished(3);
        } catch (Throwable) {
            $latestArticles = [];
        }
        try {
            $transformations = db()->query(
                "SELECT title, before_image AS `before`, after_image AS `after`
                 FROM case_studies
                 WHERE status='published'
                   AND before_image IS NOT NULL AND before_image <> ''
                   AND after_image IS NOT NULL AND after_image <> ''
                 ORDER BY sort_order, id
                 LIMIT 3"
            )->fetchAll();
        } catch (Throwable) {
            $transformations = [];
        }
    }
    view('pages/home', compact('sections', 'services', 'doctors', 'testimonials', 'faqs', 'latestArticles', 'transformations') + [
        'title' => setting('clinic_name', config('app.name')),
        'htmlTitle' => 'Sarva Dental Clinic | صفحه اصلی',
    ]);
});

$publicPages = [
    '/about' => ['about', 'درباره ما'],
    '/services' => ['services', 'خدمات'],
    '/team' => ['team', 'تیم پزشکی'],
    '/case-studies' => ['case-studies', 'نمونه کارها'],
    '/gallery' => ['gallery', 'گالری تصاویر'],
    '/gallery/videos' => ['gallery-videos', 'گالری ویدیو'],
    '/testimonials' => ['testimonials', 'نظرات بیماران'],
    '/faqs' => ['faqs', 'سؤالات متداول'],
    '/contact' => ['contact', 'تماس با ما'],
];

foreach ($publicPages as $path => [$template, $title]) {
    $router->get($path, static function () use ($path, $template, $title): void {
        track_page_view($path);
        $data = ['title' => $title];
        if (Database::connected()) {
            try {
                if ($template === 'about') {
                    $about = [];
                    try {
                        $st = db()->query(
                            "SELECT ps.* FROM page_sections ps
                             INNER JOIN pages p ON p.id = ps.page_id
                             WHERE p.slug IN ('about','home') AND ps.section_key='about' AND ps.is_visible=1
                             ORDER BY FIELD(p.slug,'about','home')
                             LIMIT 1"
                        );
                        $about = $st->fetch() ?: [];
                    } catch (Throwable) {
                        $about = [];
                    }
                    $data['about'] = $about;
                }
                if ($template === 'services') {
                    $data['services'] = db()->query('SELECT * FROM services WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order')->fetchAll();
                }
                if ($template === 'team') {
                    $data['doctors'] = db()->query('SELECT * FROM doctors WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order')->fetchAll();
                }
                if ($template === 'faqs') {
                    $data['faqs'] = db()->query('SELECT * FROM faqs WHERE is_active=1 ORDER BY sort_order')->fetchAll();
                }
                if ($template === 'testimonials') {
                    $data['testimonials'] = db()->query('SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order, id DESC')->fetchAll();
                    $data['defaultName'] = '';
                    if (Auth::isPatient()) {
                        $pst = db()->prepare('SELECT first_name, last_name FROM patients WHERE id=? LIMIT 1');
                        $pst->execute([Auth::patientId()]);
                        $prow = $pst->fetch() ?: null;
                        if ($prow) {
                            $data['defaultName'] = trim(($prow['first_name'] ?? '') . ' ' . ($prow['last_name'] ?? ''));
                        }
                    }
                }
                if ($template === 'case-studies') {
                    $data['cases'] = db()->query("SELECT * FROM case_studies WHERE status='published' ORDER BY sort_order")->fetchAll();
                }
                if ($template === 'gallery') {
                    $data['items'] = db()->query("SELECT * FROM gallery_items WHERE type='image' AND is_active=1 ORDER BY sort_order")->fetchAll();
                }
                if ($template === 'gallery-videos') {
                    $data['items'] = db()->query("SELECT * FROM gallery_items WHERE type='video' AND is_active=1 ORDER BY sort_order")->fetchAll();
                }
            } catch (Throwable) {
            }
        }
        view('pages/' . $template, $data);
    });
}

$router->post('/contact', static function (): void {
    Csrf::assertValid();
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($first === '' || $last === '' || $phone === '' || $message === '') {
        flash('error', 'لطفاً فیلدهای الزامی را تکمیل کنید.');
        redirect('/contact');
    }
    $line = sprintf(
        "[%s] %s %s | %s | %s | %s\n",
        date('c'),
        $first,
        $last,
        $phone,
        $email,
        str_replace(["\r", "\n"], ' ', $message)
    );
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logDir . '/contact.log', $line, FILE_APPEND | LOCK_EX);
    flash('success', 'پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس می‌گیریم.');
    redirect('/contact');
});

$router->post('/testimonials', static function (): void {
    Csrf::assertValid();
    // Honeypot — bots fill hidden "website" field
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        flash('success', 'نظر شما ثبت شد و پس از بررسی منتشر می‌شود.');
        redirect('/testimonials#leave-review');
    }
    $name = trim((string) ($_POST['patient_name'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $rating = (int) ($_POST['rating'] ?? 5);
    $_SESSION['_old'] = [
        'patient_name' => $name,
        'content' => $content,
        'rating' => (string) $rating,
    ];
    if ($name === '' || $content === '') {
        flash('error', 'لطفاً نام و متن نظر را وارد کنید.');
        redirect('/testimonials#leave-review');
    }
    if (mb_strlen($name) > 150) {
        flash('error', 'نام نباید بیشتر از ۱۵۰ کاراکتر باشد.');
        redirect('/testimonials#leave-review');
    }
    if (mb_strlen($content) < 10) {
        flash('error', 'متن نظر باید حداقل ۱۰ کاراکتر باشد.');
        redirect('/testimonials#leave-review');
    }
    if (mb_strlen($content) > 1000) {
        flash('error', 'متن نظر نباید بیشتر از ۱۰۰۰ کاراکتر باشد.');
        redirect('/testimonials#leave-review');
    }
    $rating = max(1, min(5, $rating));
    if (!Database::connected()) {
        flash('error', 'در حال حاضر امکان ثبت نظر وجود ندارد. لطفاً بعداً تلاش کنید.');
        redirect('/testimonials#leave-review');
    }
    try {
        $sort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM testimonials')->fetchColumn();
        db()->prepare(
            'INSERT INTO testimonials (patient_name, content, rating, is_active, sort_order) VALUES (?,?,?,0,?)'
        )->execute([$name, $content, $rating, $sort]);
        unset($_SESSION['_old']);
        flash('success', 'نظر شما ثبت شد و پس از بررسی منتشر می‌شود.');
    } catch (Throwable) {
        flash('error', 'ثبت نظر با خطا مواجه شد. لطفاً دوباره تلاش کنید.');
    }
    redirect('/testimonials#leave-review');
});

$router->get('/services/{slug}', static function (string $slug): void {
    track_page_view('/services/' . $slug);
    $service = null;
    if (Database::connected()) {
        $stmt = db()->prepare('SELECT * FROM services WHERE slug = ? AND is_active=1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$slug]);
        $service = $stmt->fetch() ?: null;
    }
    if (!$service) {
        http_response_code(404);
        view('pages/404', ['title' => 'خدمت یافت نشد']);
        return;
    }
    view('pages/service-single', ['title' => $service['name'], 'service' => $service]);
});

$router->get('/team/{slug}', static function (string $slug): void {
    track_page_view('/team/' . $slug);
    $doctor = null;
    if (Database::connected()) {
        $stmt = db()->prepare('SELECT * FROM doctors WHERE slug = ? AND is_active=1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$slug]);
        $doctor = $stmt->fetch() ?: null;
    }
    if (!$doctor) {
        http_response_code(404);
        view('pages/404', ['title' => 'پزشک یافت نشد']);
        return;
    }
    view('pages/team-single', ['title' => $doctor['first_name'] . ' ' . $doctor['last_name'], 'doctor' => $doctor]);
});

$router->get('/blog', static function (): void {
    redirect('/articles');
});

$router->get('/blog/{slug}', static function (string $slug): void {
    redirect('/articles/' . $slug);
});

$router->get('/articles', static function (): void {
    track_page_view('/articles');
    $posts = [];
    $categories = [];
    $category = null;
    if (Database::connected()) {
        $svc = new \Sarva\Services\ArticleService(db());
        $catSlug = trim((string) ($_GET['category'] ?? ''));
        $categoryId = null;
        if ($catSlug !== '') {
            $cstmt = db()->prepare('SELECT * FROM blog_categories WHERE slug=? AND is_active=1 AND deleted_at IS NULL LIMIT 1');
            $cstmt->execute([$catSlug]);
            $category = $cstmt->fetch() ?: null;
            $categoryId = $category ? (int) $category['id'] : null;
        }
        $posts = $svc->listPublished(24, 0, $categoryId);
        $categories = db()->query(
            'SELECT c.*, COUNT(p.id) AS post_count
             FROM blog_categories c
             LEFT JOIN blog_posts p ON p.category_id = c.id AND p.deleted_at IS NULL
               AND ((p.status="published" AND (p.published_at IS NULL OR p.published_at <= NOW()))
                 OR (p.status="scheduled" AND p.scheduled_at IS NOT NULL AND p.scheduled_at <= NOW()))
             WHERE c.deleted_at IS NULL AND c.is_active=1
             GROUP BY c.id
             ORDER BY c.sort_order, c.name'
        )->fetchAll();
    }
    view('pages/articles', [
        'title' => $category ? ('مقالات: ' . $category['name']) : 'مقالات',
        'posts' => $posts,
        'categories' => $categories,
        'category' => $category,
        'metaDescription' => 'مقالات تخصصی کلینیک دندانپزشکی سروا درباره سلامت دهان و دندان',
    ]);
});

$router->get('/articles/{slug}', static function (string $slug): void {
    track_page_view('/articles/' . $slug);
    if (!Database::connected()) {
        http_response_code(404);
        view('pages/404', ['title' => 'مقاله یافت نشد']);
        return;
    }
    $svc = new \Sarva\Services\ArticleService(db());
    $article = $svc->findPublishedBySlug($slug);
    if (!$article) {
        http_response_code(404);
        view('pages/404', ['title' => 'مقاله یافت نشد']);
        return;
    }
    $related = $svc->related($article, 3);
    $seoTitle = $article['seo_title'] ?: $article['title'];
    $seoDesc = $article['seo_description'] ?: ($article['excerpt'] ?? '');
    $robots = [];
    $robots[] = ((int) ($article['robots_index'] ?? 1) === 1) ? 'index' : 'noindex';
    $robots[] = ((int) ($article['robots_follow'] ?? 1) === 1) ? 'follow' : 'nofollow';
    view('pages/article-single', [
        'title' => $seoTitle,
        'metaDescription' => $seoDesc,
        'article' => $article,
        'related' => $related,
        'robotsMeta' => implode(',', $robots),
        'canonical' => $article['canonical_url'] ?: url('/articles/' . $article['slug']),
        'ogImage' => $article['og_image'] ?: $article['cover'],
        'ogTitle' => $article['og_title'] ?: $seoTitle,
        'ogDescription' => $article['og_description'] ?: $seoDesc,
    ]);
});

$router->get('/case-studies/{slug}', static function (string $slug): void {
    track_page_view('/case-studies/' . $slug);
    $case = null;
    if (Database::connected()) {
        $stmt = db()->prepare("SELECT * FROM case_studies WHERE slug = ? AND status='published' LIMIT 1");
        $stmt->execute([$slug]);
        $case = $stmt->fetch() ?: null;
    }
    if (!$case) {
        http_response_code(404);
        view('pages/404', ['title' => 'نمونه کار یافت نشد']);
        return;
    }
    view('pages/case-study-single', ['title' => $case['title'], 'case' => $case]);
});

// Auth
$router->get('/auth', static function (): void {
    unset($_SESSION['auth_redirect']);
    if (Auth::isPatient()) {
        redirect('/patient');
    }
    view('auth/index', ['title' => 'ورود به پنل بیمار']);
});

$router->post('/api/auth/otp/request', static function (): void {
    Csrf::assertValid();
    if (!Database::connected()) {
        json_response(['ok' => false, 'message' => 'پایگاه داده در دسترس نیست.'], 503);
    }
    $mobile = (string) ($_POST['mobile'] ?? '');
    $otp = new OtpService(db());
    json_response($otp->request($mobile, $_SERVER['REMOTE_ADDR'] ?? ''));
});

$router->post('/api/auth/otp/verify', static function (): void {
    Csrf::assertValid();
    if (!Database::connected()) {
        json_response(['ok' => false, 'message' => 'پایگاه داده در دسترس نیست.'], 503);
    }
    $mobile = (string) ($_POST['mobile'] ?? '');
    $code = (string) ($_POST['code'] ?? '');
    $otp = new OtpService(db());
    $result = $otp->verify($mobile, $code);
    if (!($result['ok'] ?? false)) {
        json_response($result, 422);
    }
    $normalized = $result['mobile'];
    $stmt = db()->prepare('SELECT * FROM patients WHERE mobile = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$normalized]);
    $patient = $stmt->fetch();
    if ($patient) {
        unset($_SESSION['otp_verified_mobile'], $_SESSION['auth_redirect']);
        Auth::loginPatient((int) $patient['id']);
        audit('patient.login', 'patient', (int) $patient['id']);
        json_response(['ok' => true, 'is_new' => false, 'redirect' => url('/patient')]);
    }
    unset($_SESSION['auth_redirect']);
    $_SESSION['otp_verified_mobile'] = $normalized;
    json_response(['ok' => true, 'is_new' => true, 'needs_profile' => true]);
});

$router->post('/api/auth/register', static function (): void {
    Csrf::assertValid();
    $mobile = $_SESSION['otp_verified_mobile'] ?? null;
    if (!is_string($mobile) || $mobile === '') {
        json_response(['ok' => false, 'message' => 'ابتدا شماره موبایل را تأیید کنید.'], 422);
    }
    $normalizeName = static function (string $value): ?string {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        if ($value === '' || mb_strlen($value) > 100) {
            return null;
        }
        if (!preg_match('/^[\p{L}\p{M}\s\-\x{200c}\']+$/u', $value)) {
            return null;
        }
        return $value;
    };
    $first = $normalizeName((string) ($_POST['first_name'] ?? ''));
    $last = $normalizeName((string) ($_POST['last_name'] ?? ''));
    if ($first === null || $last === null) {
        json_response(['ok' => false, 'message' => 'نام و نام خانوادگی الزامی است.'], 422);
    }
    $find = db()->prepare('SELECT id FROM patients WHERE mobile = ? AND deleted_at IS NULL LIMIT 1');
    $find->execute([$mobile]);
    $existingId = (int) $find->fetchColumn();
    if ($existingId > 0) {
        unset($_SESSION['otp_verified_mobile'], $_SESSION['auth_redirect']);
        Auth::loginPatient($existingId);
        audit('patient.login', 'patient', $existingId);
        json_response(['ok' => true, 'is_new' => false, 'redirect' => url('/patient')]);
    }
    $code = 'P' . strtoupper(bin2hex(random_bytes(4)));
    try {
        $stmt = db()->prepare(
            'INSERT INTO patients (public_code, first_name, last_name, mobile, profile_completed) VALUES (?,?,?,?,0)'
        );
        $stmt->execute([$code, $first, $last, $mobile]);
        $id = (int) db()->lastInsertId();
    } catch (\PDOException $e) {
        $find->execute([$mobile]);
        $existingId = (int) $find->fetchColumn();
        if ($existingId < 1) {
            json_response(['ok' => false, 'message' => 'ثبت‌نام انجام نشد. دوباره تلاش کنید.'], 422);
        }
        unset($_SESSION['otp_verified_mobile'], $_SESSION['auth_redirect']);
        Auth::loginPatient($existingId);
        audit('patient.login', 'patient', $existingId);
        json_response(['ok' => true, 'is_new' => false, 'redirect' => url('/patient')]);
    }
    unset($_SESSION['otp_verified_mobile'], $_SESSION['auth_redirect']);
    Auth::loginPatient($id);
    audit('patient.register', 'patient', $id);
    json_response(['ok' => true, 'redirect' => url('/patient')]);
});

$router->post('/auth/logout', static function (): void {
    Csrf::assertValid();
    Auth::logout();
    redirect('/');
});

// Patient panel
$router->get('/patient', static function (): void {
    Auth::requirePatient();
    $patient = db()->prepare('SELECT * FROM patients WHERE id = ?');
    $patient->execute([Auth::patientId()]);
    $patient = $patient->fetch();
    $upcoming = db()->prepare(
        "SELECT a.*, s.name AS service_name, CONCAT(d.first_name,' ',d.last_name) AS doctor_name
         FROM appointments a
         JOIN services s ON s.id = a.service_id
         JOIN doctors d ON d.id = a.doctor_id
         WHERE a.patient_id = ? AND a.starts_at >= NOW() AND a.status IN ('awaiting_payment','confirmed')
         ORDER BY a.starts_at ASC LIMIT 10"
    );
    $upcoming->execute([Auth::patientId()]);
    $past = db()->prepare(
        "SELECT a.*, s.name AS service_name, CONCAT(d.first_name,' ',d.last_name) AS doctor_name
         FROM appointments a
         JOIN services s ON s.id = a.service_id
         JOIN doctors d ON d.id = a.doctor_id
         WHERE a.patient_id = ? AND (a.starts_at < NOW() OR a.status IN ('completed','cancelled','no_show'))
         ORDER BY a.starts_at DESC LIMIT 20"
    );
    $past->execute([Auth::patientId()]);
    $payments = db()->prepare('SELECT * FROM payments WHERE patient_id = ? ORDER BY id DESC LIMIT 20');
    $payments->execute([Auth::patientId()]);
    view('patient/dashboard', [
        'title' => 'پنل بیمار',
        'patient' => $patient,
        'upcoming' => $upcoming->fetchAll(),
        'past' => $past->fetchAll(),
        'payments' => $payments->fetchAll(),
    ]);
});

$router->post('/patient/profile', static function (): void {
    Auth::requirePatient();
    Csrf::assertValid();
    $fields = ['first_name', 'last_name', 'national_id', 'birth_date', 'gender', 'email', 'address', 'emergency_contact_name', 'emergency_contact_mobile'];
    $data = [];
    foreach ($fields as $f) {
        $data[$f] = trim((string) ($_POST[$f] ?? '')) ?: null;
    }
    if (empty($data['first_name']) || empty($data['last_name'])) {
        flash('error', 'نام و نام خانوادگی الزامی است.');
        redirect('/patient');
    }
    $sql = 'UPDATE patients SET first_name=:first_name, last_name=:last_name, national_id=:national_id,
            birth_date=:birth_date, gender=:gender, email=:email, address=:address,
            emergency_contact_name=:emergency_contact_name, emergency_contact_mobile=:emergency_contact_mobile,
            profile_completed=1 WHERE id=:id';
    $data['id'] = Auth::patientId();
    db()->prepare($sql)->execute($data);
    audit('patient.profile_update', 'patient', Auth::patientId());
    flash('success', 'پروفایل با موفقیت به‌روزرسانی شد.');
    redirect('/patient');
});

// Appointment booking
$router->get('/appointment', static function (): void {
    if (!Auth::isPatient()) {
        unset($_SESSION['auth_redirect']);
        redirect('/auth');
    }
    track_page_view('/appointment');
    $services = Database::connected()
        ? db()->query('SELECT * FROM services WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order')->fetchAll()
        : [];
    $doctors = Database::connected()
        ? db()->query('SELECT * FROM doctors WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order')->fetchAll()
        : [];
    view('pages/appointment', [
        'title' => 'رزرو نوبت',
        'services' => $services,
        'doctors' => $doctors,
        'isPatient' => true,
    ]);
});

$router->get('/api/appointment/slots', static function (): void {
    if (!Database::connected()) {
        json_response(['ok' => false, 'slots' => []]);
    }
    $doctorId = (int) ($_GET['doctor_id'] ?? 0);
    $date = (string) ($_GET['date'] ?? '');
    $serviceId = (int) ($_GET['service_id'] ?? 0);
    if (!$doctorId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'پارامتر نامعتبر', 'slots' => []], 422);
    }
    $duration = (int) config('app.default_appointment_duration', 30);
    if ($serviceId) {
        $s = db()->prepare('SELECT duration_minutes FROM services WHERE id=?');
        $s->execute([$serviceId]);
        $duration = (int) ($s->fetchColumn() ?: $duration);
    }
    $svc = new AppointmentService(db());
    json_response(['ok' => true, 'slots' => $svc->availableSlots($doctorId, $date, $duration)]);
});

$router->post('/api/appointment/book', static function (): void {
    Csrf::assertValid();
    Auth::requirePatient();
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $date = (string) ($_POST['date'] ?? '');
    $time = (string) ($_POST['time'] ?? '');
    if (!$serviceId || !$doctorId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        json_response(['ok' => false, 'message' => 'اطلاعات نوبت ناقص است.'], 422);
    }
    $startsAt = $date . ' ' . $time . ':00';
    $svc = new AppointmentService(db());
    $result = $svc->createHold(Auth::patientId(), $doctorId, $serviceId, $startsAt, 0);
    if (!($result['ok'] ?? false)) {
        json_response($result, 409);
    }

    $gateway = PaymentManager::make();
    $pay = $gateway->createPayment((int) $result['fee'], 'هزینه ویزیت کلینیک سروا', [
        'appointment_id' => $result['appointment_id'],
        'patient_id' => Auth::patientId(),
    ]);
    if (!($pay['ok'] ?? false)) {
        json_response(['ok' => false, 'message' => $pay['message'] ?? 'خطا در ایجاد پرداخت'], 502);
    }

    $ins = db()->prepare(
        "INSERT INTO payments (appointment_id, patient_id, amount, provider, authority, status, raw_request)
         VALUES (?,?,?,?,?,'pending',?)"
    );
    $ins->execute([
        $result['appointment_id'],
        Auth::patientId(),
        $result['fee'],
        $_ENV['PAYMENT_DRIVER'] ?? 'sandbox',
        $pay['authority'],
        json_encode($pay['raw'] ?? [], JSON_UNESCAPED_UNICODE),
    ]);

    json_response([
        'ok' => true,
        'redirect' => $pay['redirect_url'],
        'appointment_id' => $result['appointment_id'],
    ]);
});

$router->get('/payment/sandbox', static function (): void {
    $authority = (string) ($_GET['authority'] ?? '');
    view('pages/payment-sandbox', ['title' => 'پرداخت آزمایشی', 'authority' => $authority]);
});

$router->any('/payment/callback', static function (): void {
    $authority = (string) ($_GET['authority'] ?? $_POST['authority'] ?? '');
    if ($authority === '') {
        view('pages/payment-result', ['title' => 'نتیجه پرداخت', 'ok' => false, 'message' => 'شناسه پرداخت یافت نشد.']);
        return;
    }

    $stmt = db()->prepare('SELECT * FROM payments WHERE authority = ? LIMIT 1');
    $stmt->execute([$authority]);
    $payment = $stmt->fetch();
    if (!$payment) {
        view('pages/payment-result', ['title' => 'نتیجه پرداخت', 'ok' => false, 'message' => 'تراکنش یافت نشد.']);
        return;
    }

    // Idempotent: already paid
    if ($payment['status'] === 'paid') {
        view('pages/payment-result', [
            'title' => 'نتیجه پرداخت',
            'ok' => true,
            'message' => 'پرداخت قبلاً تأیید شده است.',
            'ref_id' => $payment['ref_id'],
            'appointment_id' => $payment['appointment_id'],
        ]);
        return;
    }

    $gateway = PaymentManager::make();
    $verify = $gateway->verifyPayment($authority, (int) $payment['amount']);
    if (!($verify['ok'] ?? false)) {
        db()->prepare("UPDATE payments SET status='failed', raw_verify=? WHERE id=? AND status <> 'paid'")
            ->execute([json_encode($verify, JSON_UNESCAPED_UNICODE), $payment['id']]);
        view('pages/payment-result', ['title' => 'نتیجه پرداخت', 'ok' => false, 'message' => $verify['message'] ?? 'پرداخت ناموفق بود.']);
        return;
    }

    db()->beginTransaction();
    try {
        $upd = db()->prepare(
            "UPDATE payments SET status='paid', ref_id=?, verified_at=NOW(), raw_verify=?
             WHERE id=? AND status <> 'paid'"
        );
        $upd->execute([
            $verify['ref_id'] ?? null,
            json_encode($verify, JSON_UNESCAPED_UNICODE),
            $payment['id'],
        ]);
        if ($upd->rowCount() > 0) {
            (new AppointmentService(db()))->confirmPaid((int) $payment['appointment_id']);
            audit('payment.verified', 'payment', (int) $payment['id']);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    $fresh = db()->prepare('SELECT * FROM payments WHERE id=?');
    $fresh->execute([$payment['id']]);
    $payment = $fresh->fetch();

    view('pages/payment-result', [
        'title' => 'نتیجه پرداخت',
        'ok' => true,
        'message' => 'پرداخت با موفقیت انجام و نوبت شما تأیید شد.',
        'ref_id' => $payment['ref_id'],
        'appointment_id' => $payment['appointment_id'],
    ]);
});

// Admin routes loaded separately
require dirname(__DIR__) . '/admin/routes.php';
require dirname(__DIR__) . '/admin/articles-routes.php';
require dirname(__DIR__) . '/admin/crm-routes.php';
