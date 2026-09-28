<?php $items = $items ?? []; ?>
<div class="admin-card">
    <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">افزودن / ویرایش سؤال متداول</h2>
    <form method="post" action="<?= url('/admin/faqs/save') ?>" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="faq_id" value="0">
        <div class="col-md-8">
            <label class="form-label">سؤال</label>
            <input type="text" name="question" id="faq_question" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">ترتیب</label>
            <input type="number" name="sort_order" id="faq_sort" class="form-control" value="0">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <label class="form-check mb-2">
                <input type="checkbox" name="is_active" id="faq_active" class="form-check-input" checked>
                فعال
            </label>
        </div>
        <div class="col-12">
            <label class="form-label">پاسخ</label>
            <textarea name="answer" id="faq_answer" class="form-control" rows="4" required></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetFaqForm()">جدید</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>سؤال</th>
                    <th>پاسخ</th>
                    <th>ترتیب</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5">سؤالی ثبت نشده است.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['question'] ?? '') ?></td>
                        <td style="max-width:360px;"><?= e(mb_substr((string) ($item['answer'] ?? ''), 0, 120)) ?></td>
                        <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                        <td><?= !empty($item['is_active']) ? 'فعال' : 'غیرفعال' ?></td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editFaq(<?= json_encode([
                                    'id' => (int) $item['id'],
                                    'question' => $item['question'] ?? '',
                                    'answer' => $item['answer'] ?? '',
                                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                                    'is_active' => (int) ($item['is_active'] ?? 0),
                                ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                            <form method="post" action="<?= url('/admin/faqs/delete') ?>" style="display:inline;" onsubmit="return confirm('حذف این سؤال؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function editFaq(f) {
    document.getElementById('faq_id').value = f.id;
    document.getElementById('faq_question').value = f.question || '';
    document.getElementById('faq_answer').value = f.answer || '';
    document.getElementById('faq_sort').value = f.sort_order || 0;
    document.getElementById('faq_active').checked = !!f.is_active;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetFaqForm() {
    document.getElementById('faq_id').value = 0;
    document.getElementById('faq_question').value = '';
    document.getElementById('faq_answer').value = '';
    document.getElementById('faq_sort').value = 0;
    document.getElementById('faq_active').checked = true;
}
</script>
