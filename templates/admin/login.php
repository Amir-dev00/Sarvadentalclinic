<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'ورود مدیریت') ?> | سروا دنتال</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <style>
        :root {
            --primary: #031D4F;
            --accent: #05B18B;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Vazirmatn, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 15% 20%, rgba(5, 177, 139, 0.18), transparent 45%),
                radial-gradient(ellipse at 85% 80%, rgba(3, 29, 79, 0.22), transparent 50%),
                linear-gradient(160deg, #031D4F 0%, #0a2d6b 45%, #031D4F 100%);
            padding: 24px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 24px;
            padding: 40px 36px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.28);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            inset-inline: 0;
            top: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--primary));
        }
        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand h1 {
            margin: 0 0 6px;
            font-size: 1.6rem;
            color: var(--primary);
            font-weight: 800;
        }
        .login-brand h1 span { color: var(--accent); }
        .login-brand p {
            margin: 0;
            color: #6b7280;
            font-size: .95rem;
        }
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--primary);
            font-size: .92rem;
        }
        .form-control {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 1rem;
            margin-bottom: 16px;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(5, 177, 139, 0.2);
        }
        .btn-login {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 14px;
            font-family: inherit;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), #0a3a7a);
            cursor: pointer;
            margin-top: 8px;
            transition: transform .15s, box-shadow .15s;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(3, 29, 79, 0.35);
        }
        .auth-alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: .95rem;
            background: #fdecec;
            color: #a33;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <h1>سروا <span>دنتال</span></h1>
            <p>ورود به پنل مدیریت</p>
        </div>

        <?php if ($err = flash('error')): ?>
            <div class="auth-alert"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/admin/login') ?>" autocomplete="on">
            <?= csrf_field() ?>
            <label class="form-label" for="email">ایمیل</label>
            <input class="form-control" type="email" id="email" name="email" required autofocus placeholder="admin@sarvadental.ir" value="<?= old('email') ?>">

            <label class="form-label" for="password">رمز عبور</label>
            <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">

            <button type="submit" class="btn-login">ورود</button>
        </form>
    </div>
</body>
</html>
