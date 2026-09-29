<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'پنل بیمار') ?> | سروا دنتال</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/rtl.css') ?>" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: Vazirmatn, sans-serif; margin: 0; background: #f4f6fa; }
        .patient-shell { min-height: 100vh; }
        .patient-nav {
            background: #031D4F;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .patient-nav .brand {
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            font-size: 1.1rem;
        }
        .patient-nav .brand span { color: #05B18B; }
        .patient-nav-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .patient-nav-links a,
        .patient-nav-links button {
            color: rgba(255,255,255,.9);
            text-decoration: none;
            background: transparent;
            border: 0;
            padding: 8px 14px;
            border-radius: 10px;
            font-family: inherit;
            cursor: pointer;
        }
        .patient-nav-links a:hover,
        .patient-nav-links a.active,
        .patient-nav-links button:hover {
            background: rgba(5, 177, 139, .25);
            color: #fff;
        }
        .patient-body { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        .admin-flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
        .admin-flash.success { background: #e8f8f3; color: #067a5f; }
        .admin-flash.error { background: #fdecec; color: #a33; }
    </style>
</head>
<body>
<?php $current = request_path(); ?>
<div class="patient-shell">
    <header class="patient-nav">
        <a class="brand" href="<?= url('/patient') ?>">سروا <span>دنتال</span></a>
        <div class="patient-nav-links">
            <a href="<?= url('/patient') ?>" class="<?= str_starts_with($current, '/patient') ? 'active' : '' ?>">داشبورد</a>
            <a href="<?= url('/appointment') ?>">رزرو نوبت</a>
            <form method="post" action="<?= url('/auth/logout') ?>" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit">خروج</button>
            </form>
        </div>
    </header>
    <main class="patient-body">
        <?php if ($msg = flash('success')): ?>
            <div class="admin-flash success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="admin-flash error"><?= e($msg) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
</div>
<script src="<?= asset('js/jalali-datepicker.js') ?>"></script>
</body>
</html>
