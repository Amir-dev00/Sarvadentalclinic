<?php
/** @var array<string, array{title:string,description:string,table:string,date_column:string}> $categories */
/** @var array<string, array{total:int,oldest:?string,newest:?string,eligible:int,available:bool}> $stats */
/** @var array<string, int>|null $result */
/** @var array{categories:list<string>,counts:array<string,int>,range:string}|null $preview */
/** @var list<string> $selected */
/** @var string $range */
$categories = $categories ?? [];
$stats = $stats ?? [];
$result = $result ?? null;
$preview = $preview ?? null;
$selected = $selected ?? [];
$range = $range ?? '90_days';
$phrase = \Sarva\Services\HistoryCleanupService::CONFIRM_PHRASE;
$ranges = [
    '30_days' => '۳۰ روز',
    '90_days' => '۹۰ روز',
    '180_days' => '۱۸۰ روز',
    '365_days' => '۱ سال',
    'all' => 'همه (با محدودیت‌های ایمنی)',
];

$fmtDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $shown = function_exists('to_jalali') ? to_jalali($value, 'Y/m/d') : $value;
    return $shown !== '' ? $shown : $value;
};
?>
<style>
.hist-page { max-width: 980px; }
.hist-lead { color: #6b7280; line-height: 1.8; margin: 0 0 18px; }
.hist-grid { display: grid; gap: 12px; }
.hist-card {
    border: 1px solid #e8ecf4;
    border-radius: 16px;
    padding: 14px 16px;
    background: #fff;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 12px;
    align-items: start;
}
.hist-card h3 { margin: 0 0 4px; font-size: 1rem; color: #031D4F; }
.hist-card p { margin: 0; color: #6b7280; font-size: .88rem; line-height: 1.7; }
.hist-meta { margin-top: 8px; color: #354A72; font-size: .86rem; }
.hist-danger {
    margin-top: 18px;
    border: 1px solid #fecaca;
    background: #fff7f7;
    border-radius: 16px;
    padding: 16px;
}
.hist-danger h2 { margin: 0 0 8px; color: #991b1b; font-size: 1.05rem; }
.hist-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-top: 14px; }
.hist-modal {
    position: fixed; inset: 0; background: rgba(3, 29, 79, .45);
    display: none; align-items: center; justify-content: center; z-index: 80; padding: 16px;
}
.hist-modal.is-open { display: flex; }
.hist-modal__box {
    background: #fff; border-radius: 16px; max-width: 520px; width: 100%;
    padding: 18px; box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.hist-result { margin-bottom: 14px; }
</style>

<div class="admin-card hist-page">
    <h2 style="margin:0 0 6px;font-size:1.15rem;color:#031D4F;">پاک‌سازی تاریخچه‌ها</h2>
    <p class="hist-lead">برای آزادسازی فضای دیتابیس می‌توانید تاریخچه‌ها و لاگ‌های قدیمی را حذف کنید. اطلاعات اصلی بیماران و نوبت‌ها حذف نمی‌شوند.</p>

    <?php if (is_array($preview) && isset($preview['counts']) && is_array($preview['counts'])): ?>
        <div class="hist-result" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:12px;padding:12px 14px;">
            <strong style="color:#92400e;">پیش‌نمایش حذف</strong>
            <ul style="margin:8px 0 0;padding-right:1.1rem;color:#78350f;">
                <?php $psum = 0; foreach ($preview['counts'] as $key => $count): $psum += (int) $count; ?>
                    <li><?= e($categories[$key]['title'] ?? $key) ?>: <?= number_format((int) $count) ?> رکورد حذف خواهد شد.</li>
                <?php endforeach; ?>
            </ul>
            <p style="margin:8px 0 0;color:#92400e;">مجموع قابل حذف: <?= number_format($psum) ?> رکورد. هنوز چیزی حذف نشده است.</p>
        </div>
    <?php endif; ?>

    <?php if (is_array($result)): ?>
        <div class="hist-result" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:12px 14px;">
            <strong style="color:#065f46;">پاک‌سازی انجام شد.</strong>
            <ul style="margin:8px 0 0;padding-right:1.1rem;color:#064e3b;">
                <?php $sum = 0; foreach ($result as $key => $count): $sum += (int) $count; ?>
                    <li><?= e($categories[$key]['title'] ?? $key) ?>: <?= number_format((int) $count) ?> رکورد</li>
                <?php endforeach; ?>
            </ul>
            <p style="margin:8px 0 0;color:#065f46;">مجموع: <?= number_format($sum) ?> رکورد حذف شد.</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/maintenance/history/cleanup') ?>" id="histForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="histAction" value="cleanup">

        <div class="hist-grid">
            <?php foreach ($categories as $key => $meta):
                $st = $stats[$key] ?? ['total' => 0, 'oldest' => null, 'newest' => null, 'eligible' => 0, 'available' => false];
                ?>
                <label class="hist-card">
                    <input type="checkbox" name="categories[]" value="<?= e($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>>
                    <div>
                        <h3><?= e($meta['title']) ?></h3>
                        <p><?= e($meta['description']) ?></p>
                        <div class="hist-meta">
                            <?= number_format((int) $st['total']) ?> رکورد
                            <?php if (empty($st['available'])): ?>
                                · جدول هنوز ساخته نشده
                            <?php else: ?>
                                · قدیمی‌ترین: <?= e($fmtDate($st['oldest'])) ?>
                                · جدیدترین: <?= e($fmtDate($st['newest'])) ?>
                                · در بازه انتخاب‌شده: <?= number_format((int) $st['eligible']) ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger hist-one" data-category="<?= e($key) ?>" style="margin-top:8px;">پاک کردن <?= e($meta['title']) ?></button>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="hist-danger">
            <h2>حذف انتخاب‌شده</h2>
            <p style="margin:0 0 12px;color:#7f1d1d;line-height:1.7;">این عملیات دائمی است. بیماران، نوبت‌ها، پرداخت‌ها، پزشکان، خدمات و پرونده‌های بالینی حذف نمی‌شوند.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">پاک کردن قدیمی‌تر از</label>
                    <select name="older_than" id="histRange" class="form-select">
                        <?php foreach ($ranges as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $range === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="histPhraseWrap">
                    <label class="form-label">برای حذف همه، این عبارت را بنویسید</label>
                    <input type="text" name="confirmation" id="histPhrase" class="form-control" autocomplete="off" placeholder="<?= e($phrase) ?>">
                </div>
            </div>
            <p id="histWarn" style="display:none;margin:12px 0 0;color:#991b1b;">حذف اجرای امروز اتوماسیون انجام نمی‌شود. اجرای امروز برای جلوگیری از ارسال دوباره یادآوری نگه داشته می‌شود.</p>
            <div class="hist-actions">
                <button type="submit" class="btn btn-outline-primary" id="histPreviewBtn">پیش‌نمایش تعداد حذف</button>
                <button type="submit" class="btn btn-danger" id="histDeleteBtn">پاک کردن موارد انتخاب‌شده</button>
            </div>
        </div>
    </form>
</div>

<div class="hist-modal" id="histModal" role="dialog" aria-modal="true">
    <div class="hist-modal__box">
        <h3 style="margin:0 0 8px;color:#991b1b;">هشدار</h3>
        <p style="line-height:1.8;color:#374151;">این عملیات تمام تاریخچه‌های انتخاب‌شده در بازه «همه» را به‌صورت دائمی حذف می‌کند و قابل بازگشت نیست. اطلاعات اصلی بیماران، نوبت‌ها و پرداخت‌ها حذف نمی‌شوند.</p>
        <p style="color:#991b1b;">برای ادامه باید عبارت «<?= e($phrase) ?>» را در کادر نوشته باشید.</p>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn btn-outline-secondary" id="histModalCancel">انصراف</button>
            <button type="button" class="btn btn-danger" id="histModalOk" disabled>حذف دائمی</button>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('histForm');
    var range = document.getElementById('histRange');
    var phrase = document.getElementById('histPhrase');
    var phraseWrap = document.getElementById('histPhraseWrap');
    var del = document.getElementById('histDeleteBtn');
    var modal = document.getElementById('histModal');
    var modalOk = document.getElementById('histModalOk');
    var expected = <?= json_encode($phrase, JSON_UNESCAPED_UNICODE) ?>;
    var pendingDelete = false;

    function syncPhrase() {
        var all = range.value === 'all';
        phraseWrap.style.display = all ? '' : 'none';
        document.getElementById('histWarn').style.display = all ? '' : 'none';
        modalOk.disabled = phrase.value.trim() !== expected;
    }
    range.addEventListener('change', function () {
        syncPhrase();
        var url = new URL(window.location.href);
        url.searchParams.set('older_than', range.value);
        window.location = url.toString();
    });
    phrase.addEventListener('input', syncPhrase);
    syncPhrase();

    form.addEventListener('submit', function (e) {
        var submitter = e.submitter;
        if (submitter && submitter.id === 'histPreviewBtn') {
            document.getElementById('histAction').value = 'preview';
            return;
        }
        document.getElementById('histAction').value = 'cleanup';
        var checked = form.querySelectorAll('input[name="categories[]"]:checked');
        if (!checked.length) {
            e.preventDefault();
            alert('حداقل یک تاریخچه را انتخاب کنید.');
            return;
        }
        if (range.value === 'all' && !pendingDelete) {
            e.preventDefault();
            modal.classList.add('is-open');
            modalOk.disabled = phrase.value.trim() !== expected;
            return;
        }
        del.disabled = true;
        del.textContent = 'در حال پاک‌سازی...';
    });
    document.getElementById('histModalCancel').addEventListener('click', function () {
        modal.classList.remove('is-open');
        pendingDelete = false;
    });
    modalOk.addEventListener('click', function () {
        if (phrase.value.trim() !== expected) return;
        pendingDelete = true;
        document.getElementById('histAction').value = 'cleanup';
        modal.classList.remove('is-open');
        del.disabled = true;
        del.textContent = 'در حال پاک‌سازی...';
        form.submit();
    });

    form.querySelectorAll('.hist-one').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            form.querySelectorAll('input[name="categories[]"]').forEach(function (box) {
                box.checked = box.value === btn.getAttribute('data-category');
            });
            document.getElementById('histAction').value = 'cleanup';
            if (range.value === 'all') {
                modal.classList.add('is-open');
                modalOk.disabled = phrase.value.trim() !== expected;
                return;
            }
            del.disabled = true;
            del.textContent = 'در حال پاک‌سازی...';
            form.requestSubmit(del);
        });
    });
})();
</script>
