<?php
$total = (int) ($total ?? 0);
$today = (int) ($today ?? 0);
$week = (int) ($week ?? 0);
$month = (int) ($month ?? 0);
$top = $top ?? [];
$daily = $daily ?? [];
?>
<div class="stat-grid">
    <div class="stat-card">
        <h3><?= $total ?></h3>
        <p>کل بازدیدها</p>
    </div>
    <div class="stat-card">
        <h3><?= $today ?></h3>
        <p>امروز</p>
    </div>
    <div class="stat-card">
        <h3><?= $week ?></h3>
        <p>۷ روز اخیر</p>
    </div>
    <div class="stat-card">
        <h3><?= $month ?></h3>
        <p>۳۰ روز اخیر</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">پربازدیدترین صفحات</h2>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>مسیر</th>
                            <th>بازدید</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top)): ?>
                            <tr><td colspan="2">داده‌ای نیست.</td></tr>
                        <?php else: foreach ($top as $row): ?>
                            <tr>
                                <td><code><?= e($row['path'] ?? '') ?></code></td>
                                <td><?= (int) ($row['c'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card">
            <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">بازدید روزانه (۱۴ روز)</h2>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>بازدید</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily)): ?>
                            <tr><td colspan="2">داده‌ای نیست.</td></tr>
                        <?php else: foreach ($daily as $row): ?>
                            <tr>
                                <td><?= e($row['d'] ?? '') ?></td>
                                <td><?= (int) ($row['c'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
