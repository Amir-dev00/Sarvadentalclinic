<?php

declare(strict_types=1);

/**
 * Global helpers for Sarva Dental Clinic.
 */

use Sarva\Core\App;
use Sarva\Core\Csrf;
use Sarva\Core\Database;

function app(): App
{
    return App::getInstance();
}

function config(string $key, mixed $default = null): mixed
{
    return app()->config($key, $default);
}

function db(): PDO
{
    return Database::connection();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url_path_prefix(): string
{
    static $prefix = null;
    if ($prefix !== null) {
        return $prefix;
    }

    // Prefer path segment from APP_URL (e.g. http://localhost/sarva → /sarva)
    $configured = (string) config('app.url', '');
    $configuredPath = rtrim((string) (parse_url($configured, PHP_URL_PATH) ?: ''), '/');

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '/' || $dir === '.' || $dir === '') {
        $dir = '';
    }

    // When APP_URL includes a subdirectory and request path matches, use it.
    if ($configuredPath !== '' && ($dir === '' || str_starts_with($dir, $configuredPath) || str_starts_with($configuredPath, $dir))) {
        $prefix = $configuredPath;
    } else {
        $prefix = $dir;
    }

    return $prefix;
}

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = rtrim((string) config('app.url', ''), '/');
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host !== '' && PHP_SAPI !== 'cli') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
        $scheme = $https ? 'https' : 'http';
        $base = $scheme . '://' . $host . url_path_prefix();
        return $base;
    }

    $base = $configured !== '' ? $configured : 'http://127.0.0.1:8080';
    return $base;
}

function url(string $path = ''): string
{
    $base = base_url();
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        return $base . '/';
    }
    return $base . $path;
}

/**
 * Browser URL for static assets under /assets.
 * Prefer root-relative paths so they work on any host/port.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    if (str_starts_with($path, 'assets/')) {
        $path = substr($path, 7);
    }
    return url_path_prefix() . '/assets/' . $path;
}

/**
 * Public URL for media stored as relative paths (uploads/..., images/...).
 * If an uploads path is missing on disk, try sibling extensions (orphaned .webp
 * rows) so a surviving original format can still be linked.
 */
function media_url(?string $path): string
{
    if ($path === null || $path === '') {
        return asset('images/about-us-image.jpg');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if (str_starts_with($path, 'assets/')) {
        $path = substr($path, 7);
    }

    if (str_starts_with($path, 'uploads/') && !asset_filesystem_path($path)) {
        $alt = media_alternate_existing_path($path);
        if ($alt !== null) {
            return asset($alt);
        }
    }

    return asset($path);
}

/**
 * When a stored uploads path is missing, look for the same basename with another
 * extension (e.g. DB has .webp but only .jpg was mirrored).
 */
function media_alternate_existing_path(string $relativePath): ?string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $dot = strrpos($relativePath, '.');
    if ($dot === false) {
        return null;
    }
    $base = substr($relativePath, 0, $dot);
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
        $candidate = $base . '.' . $ext;
        if ($candidate === $relativePath) {
            continue;
        }
        if (asset_filesystem_path($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/**
 * Absolute asset roots that must receive public files.
 * Writes to both project /assets and /public/assets so uploads work whether
 * cPanel document root is the project root or the public/ folder.
 *
 * @return list<string>
 */
function asset_storage_roots(): array
{
    static $roots = null;
    if ($roots !== null) {
        return $roots;
    }

    $projectRoot = dirname(__DIR__);
    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . 'assets',
        $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets',
    ];

    // Prefer the assets directory that sits under the active document root when known.
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath((string) $_SERVER['DOCUMENT_ROOT'])
        : false;
    if ($docRoot) {
        $docAssets = $docRoot . DIRECTORY_SEPARATOR . 'assets';
        array_unshift($candidates, $docAssets);
    }

    $resolved = [];
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $real = realpath($dir) ?: $dir;
        $resolved[$real] = $real;
    }

    $roots = array_values($resolved);
    return $roots;
}

/**
 * Absolute path to a published asset relative path, searching all storage roots.
 */
function asset_filesystem_path(string $relativePath): ?string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($relativePath === '') {
        return null;
    }
    if (str_starts_with($relativePath, 'assets/')) {
        $relativePath = substr($relativePath, 7);
    }

    foreach (asset_storage_roots() as $root) {
        $full = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($full)) {
            return $full;
        }
    }
    return null;
}

/**
 * Copy/write a file into every asset storage root under the given relative path
 * (e.g. uploads/2026/09/file.jpg). Returns true only when every distinct root
 * received the file — partial publishes caused 404s when docroot was /public.
 */
function publish_asset_file(string $sourceAbsolute, string $relativePath): bool
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($relativePath === '' || !is_file($sourceAbsolute)) {
        return false;
    }

    $roots = asset_storage_roots();
    if ($roots === []) {
        return false;
    }

    $written = 0;
    foreach ($roots as $root) {
        $dest = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        if (!@copy($sourceAbsolute, $dest) || !is_file($dest)) {
            return false;
        }
        @chmod($dest, 0644);
        $written++;
    }

    return $written > 0;
}

/**
 * Store an uploaded image under assets/{relDir} (mirrored to public/assets)
 * and optionally register in media table. Keeps the original raster format when
 * WebP conversion or multi-root publish cannot be verified, so DB paths are
 * never left pointing at missing files.
 *
 * @param string|null $relDir Relative dir under assets, e.g. uploads/2026/09 or uploads/patients/1/2026/09
 */
function store_uploaded_image(
    string $field,
    bool $registerMedia = true,
    int $maxBytes = 5242880,
    ?string $relDir = null
): ?string {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return null;
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return null;
    }

    $base = bin2hex(random_bytes(12));
    $origExt = $allowed[$mime];
    $tmpDir = rtrim(sys_get_temp_dir(), '/\\');
    $tmpOriginal = $tmpDir . DIRECTORY_SEPARATOR . 'sarva_' . $base . '.' . $origExt;
    if (!move_uploaded_file($file['tmp_name'], $tmpOriginal) && !@rename($file['tmp_name'], $tmpOriginal)) {
        if (!@copy($file['tmp_name'], $tmpOriginal)) {
            return null;
        }
        @unlink($file['tmp_name']);
    }

    $resolvedDir = $relDir !== null && trim($relDir) !== ''
        ? trim(str_replace('\\', '/', $relDir), '/')
        : 'uploads/' . date('Y/m');

    $finalName = $base . '.' . $origExt;
    $finalMime = $mime;
    $finalSize = (int) (@filesize($tmpOriginal) ?: ($file['size'] ?? 0));
    $publishSource = $tmpOriginal;
    $tmpWebp = $tmpDir . DIRECTORY_SEPARATOR . 'sarva_' . $base . '.webp';
    $usedWebp = false;

    try {
        $converted = (new \Sarva\Services\ImageConverter())->toWebp($tmpOriginal, $tmpWebp, 82);
        if (!empty($converted['ok']) && is_file($tmpWebp) && (int) filesize($tmpWebp) > 0) {
            $webpPath = $resolvedDir . '/' . $base . '.webp';
            if (publish_asset_file($tmpWebp, $webpPath) && asset_filesystem_path($webpPath)) {
                $finalName = $base . '.webp';
                $finalMime = 'image/webp';
                $finalSize = (int) ($converted['size'] ?? filesize($tmpWebp));
                $publishSource = $tmpWebp;
                $usedWebp = true;
            }
        }
    } catch (Throwable) {
        // Keep original format if conversion is unavailable.
    }

    $path = $resolvedDir . '/' . $finalName;
    if (!$usedWebp && !publish_asset_file($publishSource, $path)) {
        @unlink($tmpOriginal);
        @unlink($tmpWebp);
        return null;
    }
    if ($usedWebp) {
        $path = $resolvedDir . '/' . $finalName;
    }

    @unlink($tmpOriginal);
    @unlink($tmpWebp);

    if ($registerMedia && function_exists('db')) {
        try {
            if (\Sarva\Core\Database::connected()) {
                db()->prepare(
                    'INSERT INTO media (filename, original_name, mime_type, size, path, uploaded_by) VALUES (?,?,?,?,?,?)'
                )->execute([
                    $finalName,
                    (string) ($file['name'] ?? $finalName),
                    $finalMime,
                    $finalSize,
                    $path,
                    \Sarva\Core\Auth::adminId(),
                ]);
            }
        } catch (Throwable) {
            // Non-fatal: file is already on disk
        }
    }
    return $path;
}

/** Prefer uploaded file path, otherwise trimmed text path. */
function resolve_image_path(string $uploadField, ?string $fallbackPath): ?string
{
    $uploaded = store_uploaded_image($uploadField);
    if ($uploaded !== null) {
        return $uploaded;
    }
    $fallbackPath = trim((string) $fallbackPath);
    return $fallbackPath !== '' ? $fallbackPath : null;
}

/**
 * Store branding/media uploads including SVG (SVG is not converted to WebP).
 */
function store_uploaded_brand_asset(string $field, int $maxBytes = 5242880): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return null;
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if ($mime === 'image/svg+xml' || $mime === 'text/plain' || $mime === 'application/xml') {
        // Extra guard for SVG (some hosts report text/plain/xml)
        $head = (string) @file_get_contents($file['tmp_name'], false, null, 0, 256);
        if ($mime !== 'image/svg+xml' && stripos($head, '<svg') === false) {
            return store_uploaded_image($field, false, $maxBytes);
        }
        $name = bin2hex(random_bytes(12)) . '.svg';
        $relDir = 'uploads/' . date('Y/m');
        $path = $relDir . '/' . $name;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sarva_' . $name;
        if (!move_uploaded_file($file['tmp_name'], $tmp) && !@copy($file['tmp_name'], $tmp)) {
            return null;
        }
        $ok = publish_asset_file($tmp, $path);
        @unlink($tmp);
        return $ok ? $path : null;
    }
    return store_uploaded_image($field, false, $maxBytes);
}

function admin_slug(string $text, string $fallback = 'item'): string
{
    $text = trim($text);
    $slug = preg_replace('/\s+/u', '-', $text) ?? '';
    $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '', $slug) ?? '';
    $slug = trim($slug, '-_');
    if ($slug === '') {
        return $fallback . '-' . bin2hex(random_bytes(3));
    }
    return mb_strtolower($slug);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Safe relative app path for post-login redirects (blocks open redirects). */
function safe_internal_path(?string $path, string $fallback = '/'): string
{
    $path = trim((string) $path);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '://')) {
        return $fallback;
    }
    return $path;
}

/** CTA target for online booking: patient panel if logged in, otherwise auth. */
function booking_url(): string
{
    if (\Sarva\Core\Auth::isPatient()) {
        return url('/patient');
    }
    return url('/auth?next=/appointment');
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function old(string $key, string $default = ''): string
{
    return e((string) ($_SESSION['_old'][$key] ?? $default));
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== '/' && $script !== '\\' && str_starts_with($uri, $script)) {
        $uri = substr($uri, strlen($script)) ?: '/';
    }
    return '/' . trim($uri, '/');
}

/**
 * Normalize Iranian mobile numbers to 09xxxxxxxxx.
 */
function normalize_mobile(string $mobile): ?string
{
    $digits = preg_replace('/\D+/', '', normalize_digits($mobile)) ?? '';
    if (str_starts_with($digits, '98') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    }
    if (str_starts_with($digits, '9') && strlen($digits) === 10) {
        $digits = '0' . $digits;
    }
    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }
    return null;
}

function is_valid_iran_mobile(string $mobile): bool
{
    return normalize_mobile($mobile) !== null;
}

/** Convert Persian/Arabic digits to English. */
function normalize_digits(string $value): string
{
    return strtr($value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

/** Normalize Persian text (ي→ی، ك→ک, collapse spaces). */
function normalize_persian_text(string $value): string
{
    $value = strtr($value, [
        'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ؤ' => 'و', 'إ' => 'ا', 'أ' => 'ا',
        "\u{200c}" => ' ', // ZWNJ → space for search consistency on import
    ]);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function view(string $template, array $data = []): void
{
    $file = dirname(__DIR__) . '/templates/' . str_replace('.', '/', $template) . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        echo config('app.debug') ? 'View not found: ' . e($template) : 'خطای سرور';
        exit;
    }

    extract($data, EXTR_SKIP);

    $layout = null;
    if (str_starts_with($template, 'pages/') || str_starts_with($template, 'auth/')) {
        $layout = dirname(__DIR__) . '/templates/layouts/main.php';
    } elseif (str_starts_with($template, 'patient/')) {
        $layout = dirname(__DIR__) . '/templates/layouts/patient.php';
    } elseif (str_starts_with($template, 'admin/') && $template !== 'admin/login') {
        $layout = dirname(__DIR__) . '/templates/layouts/admin.php';
    }

    if ($layout) {
        ob_start();
        require $file;
        $content = ob_get_clean();
        require $layout;
        return;
    }

    require $file;
}

function partial(string $name, array $data = []): void
{
    view('partials/' . $name, $data);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        try {
            $stmt = db()->query('SELECT `key`, `value` FROM site_settings');
            $cache = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function audit(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (admin_user_id, patient_id, action, entity_type, entity_id, meta_json, ip_address, user_agent, created_at)
             VALUES (:admin_id, :patient_id, :action, :entity_type, :entity_id, :meta, :ip, :ua, NOW())'
        );
        $stmt->execute([
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'patient_id' => $_SESSION['patient_id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable) {
        // Never break the request for audit failures.
    }
}

function track_page_view(string $path): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO page_views (path, viewed_at, session_hash) VALUES (:path, NOW(), :hash)'
        );
        $stmt->execute([
            'path' => substr($path, 0, 255),
            'hash' => hash('sha256', session_id() . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')),
        ]);
    } catch (Throwable) {
        // Analytics must never break pages.
    }
}

function clinic_timezone(): string
{
    return (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
}

/** @return array<string, string> */
function sms_type_labels(): array
{
    return [
        'otp' => 'کد یکبارمصرف',
        'appointment_reminder' => 'یادآوری نوبت',
        'appointment_confirmation' => 'تأیید نوبت',
        'appointment_cancellation' => 'لغو نوبت',
        'appointment_reschedule' => 'تغییر نوبت',
        'payment_confirmation' => 'تأیید پرداخت',
        'general' => 'عمومی',
        'birthday' => 'تبریک / کمپین',
        'custom' => 'سفارشی',
    ];
}

/** @return array<string, string> */
function sms_status_labels(): array
{
    return [
        'pending' => 'در انتظار',
        'processing' => 'در حال ارسال',
        'queued' => 'در صف',
        'sent' => 'ارسال شده',
        'failed' => 'ناموفق',
        'retrying' => 'تلاش مجدد',
        'cancelled' => 'لغو شده',
        'skipped' => 'رد شده',
    ];
}

function mask_secret(?string $value): string
{
    $value = (string) $value;
    if ($value === '') {
        return 'تنظیم نشده';
    }
    $len = strlen($value);
    if ($len <= 4) {
        return '••••';
    }
    return '••••••••' . substr($value, -4);
}

function pager_url(array $query, int $page): string
{
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

/** Preserve appointments list filters after cancel actions. */
function appointments_cancel_redirect(?string $forceDate = null): string
{
    $q = [];
    $patientId = (int) ($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
    if ($patientId > 0) {
        $q['patient_id'] = $patientId;
    }
    $date = $forceDate !== null ? $forceDate : trim((string) ($_POST['redirect_date'] ?? $_POST['date'] ?? $_GET['date'] ?? ''));
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $q['date'] = $date;
    }
    $path = '/admin/appointments';
    return $q === [] ? $path : $path . '?' . http_build_query($q);
}

/** @param array<string, mixed> $summary */
function appointments_cancel_summary_message(array $summary, bool $sendSms): string
{
    $lines = [];
    $lines[] = ((int) ($summary['examined'] ?? 0)) . ' نوبت بررسی شد';
    $lines[] = ((int) ($summary['cancelled'] ?? 0)) . ' نوبت لغو شد';
    if ($sendSms) {
        $lines[] = ((int) ($summary['sms_queued'] ?? 0)) . ' پیامک در صف ارسال قرار گرفت';
        if ((int) ($summary['sms_failed'] ?? 0) > 0) {
            $lines[] = ((int) $summary['sms_failed']) . ' پیامک ثبت نشد';
        }
    } else {
        $lines[] = 'پیامک ارسال نشد (غیرفعال توسط مدیر)';
    }
    if ((int) ($summary['already_cancelled'] ?? 0) > 0) {
        $lines[] = ((int) $summary['already_cancelled']) . ' نوبت قبلاً لغو شده بود';
    }
    if ((int) ($summary['not_cancellable'] ?? 0) > 0) {
        $lines[] = ((int) $summary['not_cancellable']) . ' نوبت قابل لغو نبود';
    }
    if ((int) ($summary['not_found'] ?? 0) > 0) {
        $lines[] = ((int) $summary['not_found']) . ' نوبت یافت نشد';
    }
    return implode(' · ', $lines);
}
