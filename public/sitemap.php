<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((string) config('app.url'), '/');
$urls = [
    '/', '/about', '/services', '/case-studies', '/gallery',
    '/gallery/videos', '/testimonials', '/faqs', '/articles', '/contact', '/appointment',
];

try {
    if (\Sarva\Core\Database::connected()) {
        foreach (db()->query('SELECT slug FROM services WHERE is_active=1 AND deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $urls[] = '/services/' . $slug;
        }
        foreach (db()->query('SELECT slug FROM doctors WHERE is_active=1 AND deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $urls[] = '/team/' . $slug;
        }
        // Promote due scheduled posts, then list published only
        try {
            (new \Sarva\Services\ArticleService(db()))->promoteScheduled();
        } catch (Throwable) {
        }
        $articleSql = "SELECT slug FROM blog_posts
                       WHERE deleted_at IS NULL
                         AND status='published'
                         AND (published_at IS NULL OR published_at <= NOW())";
        foreach (db()->query($articleSql)->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $urls[] = '/articles/' . $slug;
        }
    }
} catch (Throwable) {
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $path): ?>
  <url>
    <loc><?= htmlspecialchars($base . $path, ENT_XML1) ?></loc>
    <changefreq>weekly</changefreq>
  </url>
<?php endforeach; ?>
</urlset>
