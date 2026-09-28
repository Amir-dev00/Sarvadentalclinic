<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'مدیریت') ?> | سروا دنتال</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/rtl.css') ?>" rel="stylesheet">
    <?= $adminHead ?? '' ?>
    <style>
        body { font-family: Vazirmatn, sans-serif; margin: 0; }
        .admin-sidebar .brand {
            display: block;
            padding: 8px 14px 20px;
            font-weight: 800;
            font-size: 1.15rem;
            color: #fff;
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,.12);
            margin-bottom: 16px;
        }
        .admin-sidebar .brand span { color: #05B18B; }
        .admin-flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
        .admin-flash.success { background: #e8f8f3; color: #067a5f; }
        .admin-flash.error { background: #fdecec; color: #a33; }
        .btn-logout {
            background: transparent;
            border: 1px solid #e8ecf4;
            border-radius: 10px;
            padding: 8px 14px;
            font-family: inherit;
            cursor: pointer;
            color: #031D4F;
        }
        .btn-logout:hover { background: #f4f6fa; }
        .admin-nav-group { margin: 4px 0 10px; }
        .admin-nav-group-title {
            display: block;
            padding: 8px 14px 4px;
            font-size: .78rem;
            font-weight: 700;
            color: rgba(255,255,255,.55);
            letter-spacing: .02em;
        }
        .admin-nav-group a { padding-inline-start: 22px !important; }
    </style>
</head>
<body>
<?php
$canPatients = \Sarva\Core\Auth::adminHasPermission('patients.view') || \Sarva\Core\Auth::adminHasPermission('patients.manage');
$canPatientsCreate = \Sarva\Core\Auth::adminHasPermission('patients.create') || \Sarva\Core\Auth::adminHasPermission('patients.manage');
$canSms = \Sarva\Core\Auth::adminHasPermission('sms.view');
$canSmsSettings = \Sarva\Core\Auth::adminHasPermission('sms.settings.manage');
$canImport = \Sarva\Core\Auth::adminHasPermission('patients.import') || \Sarva\Core\Auth::adminHasPermission('patients.manage');
$nav = [
    '/admin' => 'داشبورد',
    '/admin/pages' => 'صفحات',
    '/admin/services' => 'خدمات',
    '/admin/doctors' => 'تیم پزشکی',
    '/admin/case-studies' => 'قبل و بعد',
];
if ($canPatients) {
    $patientItems = [
        '/admin/patients' => 'فهرست بیماران',
    ];
    if ($canPatientsCreate) {
        $patientItems['/admin/patients/create'] = 'ایجاد پرونده';
    }
    $nav['patients'] = ['label' => 'بیماران', 'items' => $patientItems];
}
$nav['/admin/appointments'] = 'نوبت‌ها';
$nav['/admin/payments'] = 'پرداخت‌ها';
if ($canSms) {
    $smsItems = [
        '/admin/sms' => 'مرکز پیامک',
        '/admin/sms/send' => 'ارسال پیامک',
        '/admin/sms/batches' => 'ارسال‌های گروهی',
        '/admin/sms/templates' => 'پیام‌های آماده',
        '/admin/sms/queue' => 'زمان‌بندی‌شده',
        '/admin/sms/automation' => 'پیام‌های خودکار',
        '/admin/sms/logs' => 'تاریخچه ارسال',
    ];
    if ($canSmsSettings) {
        $smsItems['/admin/sms/settings'] = 'تنظیمات پیامک';
    }
    $nav['sms'] = ['label' => 'پیامک', 'items' => $smsItems];
}
$nav['articles'] = [
    'label' => 'مقالات',
    'items' => [
        '/admin/articles' => 'همه مقالات',
        '/admin/articles/create' => 'افزودن مقاله',
        '/admin/article-categories' => 'دسته‌بندی‌ها',
    ],
];
$nav['/admin/faqs'] = 'FAQ';
$nav['/admin/testimonials'] = 'نظرات';
$nav['/admin/gallery'] = 'گالری';
$nav['/admin/media'] = 'رسانه';
$nav['/admin/analytics'] = 'آمار';
if ($canImport) {
    $nav['/admin/imports'] = 'ورود اکسل';
}
$nav['/admin/settings'] = 'تنظیمات';
$nav['/admin/account'] = 'تغییر رمز عبور';
$nav['/admin/audit'] = 'گزارش فعالیت';
$current = request_path();
?>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="brand" href="<?= url('/admin') ?>">سروا <span>دنتال</span></a>
        <nav>
            <?php foreach ($nav as $path => $label): ?>
                <?php if (is_array($label)): ?>
                    <?php
                    $groupActive = str_starts_with($current, '/admin/articles')
                        || str_starts_with($current, '/admin/article-categories')
                        || str_starts_with($current, '/admin/blog')
                        || str_starts_with($current, '/admin/sms')
                        || str_starts_with($current, '/admin/patients');
                    ?>
                    <div class="admin-nav-group">
                        <span class="admin-nav-group-title"><?= e($label['label']) ?></span>
                        <?php foreach ($label['items'] as $subPath => $subLabel): ?>
                            <?php
                            if ($subPath === '/admin/articles') {
                                $active = ($current === '/admin/articles' || preg_match('#^/admin/articles/\d+#', $current));
                            } elseif ($subPath === '/admin/sms') {
                                $active = ($current === '/admin/sms' || $current === '/admin/sms/');
                            } elseif ($subPath === '/admin/patients') {
                                $active = ($current === '/admin/patients' || $current === '/admin/patients/');
                            } elseif ($subPath === '/admin/patients/create') {
                                $active = ($current === '/admin/patients/create');
                            } else {
                                $active = str_starts_with($current, $subPath);
                            }
                            ?>
                            <a href="<?= url($subPath) ?>" class="<?= $active || ($groupActive && $subPath === '/admin/articles' && str_starts_with($current, '/admin/articles/preview')) ? 'active' : '' ?>"><?= e($subLabel) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php
                    $active = ($path === '/admin')
                        ? ($current === '/admin' || $current === '/admin/')
                        : str_starts_with($current, $path);
                    ?>
                    <a href="<?= url($path) ?>" class="<?= $active ? 'active' : '' ?>"><?= e($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="admin-main">
        <div class="admin-topbar">
            <h1 style="margin:0;font-size:1.25rem;color:#031D4F;"><?= e($title ?? 'مدیریت') ?></h1>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <?php if ($canPatients): ?>
                <div class="admin-quick-search" id="adminQuickSearch" data-api="<?= e(url('/admin/patients/api/quick-search')) ?>">
                    <input type="search" class="form-control form-control-sm" placeholder="جستجوی سریع بیمار..." autocomplete="off" style="min-width:220px;">
                    <div class="admin-quick-search__results" hidden></div>
                </div>
                <?php endif; ?>
                <a href="<?= url('/admin/account') ?>" class="btn-logout" style="text-decoration:none;">تغییر رمز</a>
                <form method="post" action="<?= url('/admin/logout') ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> خروج</button>
                </form>
            </div>
        </div>

        <?php if ($msg = flash('success')): ?>
            <div class="admin-flash success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="admin-flash error"><?= e($msg) ?></div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>
</div>
<script src="<?= asset('js/jalali-datepicker.js') ?>"></script>
<script>
(function () {
  var box = document.getElementById('adminQuickSearch');
  if (!box) return;
  var input = box.querySelector('input');
  var out = box.querySelector('.admin-quick-search__results');
  var t = null;
  var base = <?= json_encode(url('/admin/patients/'), JSON_UNESCAPED_UNICODE) ?>;
  function render(items) {
    if (!items.length) {
      out.innerHTML = '<div class="qs-empty">نتیجه‌ای نیست</div>';
      out.hidden = false;
      return;
    }
    out.innerHTML = items.map(function (p) {
      return '<a href="' + base + p.id + '"><strong>' + (p.name || '—') + '</strong><span>' + (p.file_number || '') + ' · ' + (p.mobile || '') + '</span></a>';
    }).join('');
    out.hidden = false;
  }
  input.addEventListener('input', function () {
    clearTimeout(t);
    var q = input.value.trim();
    if (q.length < 1) { out.hidden = true; return; }
    t = setTimeout(function () {
      fetch(box.dataset.api + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { render((j && j.items) || []); })
        .catch(function () { out.hidden = true; });
    }, 280);
  });
  document.addEventListener('click', function (e) {
    if (!box.contains(e.target)) out.hidden = true;
  });
})();
</script>
</body>
</html>
