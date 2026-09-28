<?php partial('page-header', ['pageTitle' => $title ?? 'خدمات']); ?>
<div class="page-services">
    <div class="container">
        <div class="row">
            <?php foreach (($services ?? []) as $i => $service): ?>
            <div class="col-xl-4 col-md-6">
                <?php partial('service-card', ['service' => $service, 'index' => (int) $i]); ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($services)): ?>
            <div class="col-12"><p class="text-center py-5">هنوز خدمتی ثبت نشده است.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
