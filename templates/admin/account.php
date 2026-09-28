<?php
$admin = $admin ?? [];
?>
<div class="admin-card">
    <h2 style="margin:0 0 6px;font-size:1.1rem;color:#031D4F;">حساب کاربری</h2>
    <p style="color:#6b7280;margin:0 0 18px;">
        <?= e(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))) ?>
        · <span dir="ltr"><?= e($admin['email'] ?? '') ?></span>
    </p>
</div>

<div class="admin-card">
    <h2 style="margin:0 0 8px;font-size:1.1rem;color:#031D4F;">تغییر رمز عبور پنل مدیریت</h2>
    <p style="color:#6b7280;margin:0 0 16px;">رمز جدید بلافاصله در پایگاه داده ذخیره می‌شود و برای ورود بعدی لازم است.</p>
    <form method="post" action="<?= url('/admin/account/password') ?>" class="row g-3" autocomplete="off">
        <?= csrf_field() ?>
        <div class="col-md-6">
            <label class="form-label" for="current_password">رمز عبور فعلی</label>
            <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="col-md-6"></div>
        <div class="col-md-6">
            <label class="form-label" for="new_password">رمز عبور جدید</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8" autocomplete="new-password">
            <small style="color:#6b7280;">حداقل ۸ کاراکتر</small>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="new_password_confirm">تکرار رمز جدید</label>
            <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره رمز جدید</button>
        </div>
    </form>
</div>
