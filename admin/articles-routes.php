<?php
// hello
declare(strict_types=1);

/**
 * Admin routes for Articles / Categories.
 * Included from admin/routes.php
 */

use Sarva\Core\Auth;
use Sarva\Core\Csrf;
use Sarva\Services\ArticleService;
use Sarva\Services\HtmlSanitizer;

/** @var \Sarva\Core\Router $router */

$router->get('/admin/blog', static function (): void {
    redirect('/admin/articles');
});

$router->get('/admin/articles', static function (): void {
    Auth::requireAdmin('cms.blog');
    $svc = new ArticleService(db());
    $svc->promoteScheduled();

    $q = trim((string) ($_GET['q'] ?? ''));
    $status = (string) ($_GET['status'] ?? '');
    $categoryId = (int) ($_GET['category_id'] ?? 0);
    $authorId = (int) ($_GET['author_id'] ?? 0);
    $publishedFrom = trim((string) ($_GET['published_from'] ?? ''));
    $publishedTo = trim((string) ($_GET['published_to'] ?? ''));

    $sql = "SELECT p.*, c.name AS category_name, CONCAT(a.first_name,' ',a.last_name) AS author_name
            FROM blog_posts p
            LEFT JOIN blog_categories c ON c.id = p.category_id
            LEFT JOIN admin_users a ON a.id = p.author_id
            WHERE p.deleted_at IS NULL";
    $params = [];
    if ($q !== '') {
        $sql .= ' AND p.title LIKE :q';
        $params['q'] = '%' . $q . '%';
    }
    if (in_array($status, ['draft', 'published', 'scheduled', 'archived'], true)) {
        $sql .= ' AND p.status = :status';
        $params['status'] = $status;
    }
    if ($categoryId > 0) {
        $sql .= ' AND p.category_id = :cid';
        $params['cid'] = $categoryId;
    }
    if ($authorId > 0) {
        $sql .= ' AND p.author_id = :aid';
        $params['aid'] = $authorId;
    }
    if ($publishedFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedFrom)) {
        $sql .= ' AND DATE(p.published_at) >= :pfrom';
        $params['pfrom'] = $publishedFrom;
    }
    if ($publishedTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedTo)) {
        $sql .= ' AND DATE(p.published_at) <= :pto';
        $params['pto'] = $publishedTo;
    }
    $sql .= ' ORDER BY p.updated_at DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    view('admin/articles', [
        'title' => 'مقالات',
        'items' => $items,
        'categories' => db()->query('SELECT id, name FROM blog_categories WHERE deleted_at IS NULL ORDER BY sort_order, name')->fetchAll(),
        'authors' => db()->query('SELECT id, first_name, last_name FROM admin_users WHERE deleted_at IS NULL AND is_active=1')->fetchAll(),
        'filters' => compact('q', 'status', 'categoryId', 'authorId', 'publishedFrom', 'publishedTo'),
    ]);
});

$router->get('/admin/articles/create', static function (): void {
    Auth::requireAdmin('cms.blog');
    view('admin/article-edit', [
        'title' => 'افزودن مقاله',
        'article' => null,
        'categories' => db()->query('SELECT * FROM blog_categories WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_order')->fetchAll(),
        'authors' => db()->query('SELECT id, first_name, last_name FROM admin_users WHERE deleted_at IS NULL AND is_active=1')->fetchAll(),
        'media' => db()->query('SELECT * FROM media ORDER BY id DESC LIMIT 40')->fetchAll(),
    ]);
});

$router->get('/admin/articles/preview/{id}', static function (string $id): void {
    Auth::requireAdmin('cms.blog');
    $svc = new ArticleService(db());
    $article = $svc->findById((int) $id);
    if (!$article) {
        http_response_code(404);
        echo 'مقاله یافت نشد';
        return;
    }
    $related = $svc->related($article, 3);
    view('pages/article-single', [
        'title' => $article['title'],
        'article' => $article,
        'related' => $related,
        'isPreview' => true,
        'metaDescription' => $article['seo_description'] ?? $article['excerpt'],
    ]);
});

$router->get('/admin/articles/{id}', static function (string $id): void {
    Auth::requireAdmin('cms.blog');
    $svc = new ArticleService(db());
    $article = $svc->findById((int) $id);
    if (!$article) {
        flash('error', 'مقاله یافت نشد.');
        redirect('/admin/articles');
    }
    view('admin/article-edit', [
        'title' => 'ویرایش مقاله',
        'article' => $article,
        'categories' => db()->query('SELECT * FROM blog_categories WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll(),
        'authors' => db()->query('SELECT id, first_name, last_name FROM admin_users WHERE deleted_at IS NULL AND is_active=1')->fetchAll(),
        'media' => db()->query('SELECT * FROM media ORDER BY id DESC LIMIT 40')->fetchAll(),
    ]);
});

$router->post('/admin/articles/save', static function (): void {
    Auth::requireAdmin('cms.blog');
    Csrf::assertValid();
    $svc = new ArticleService(db());

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = HtmlSanitizer::clean((string) ($_POST['content'] ?? ''));
    $cover = trim((string) ($_POST['cover'] ?? '')) ?: null;
    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
    $authorId = (int) ($_POST['author_id'] ?? 0) ?: Auth::adminId();
    $status = (string) ($_POST['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published', 'scheduled', 'archived'], true)) {
        $status = 'draft';
    }
    $normalizeDt = static function (?string $raw): ?string {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            $raw .= ':00';
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    };
    $publishedAt = $normalizeDt($_POST['published_at'] ?? null);
    $scheduledAt = $normalizeDt($_POST['scheduled_at'] ?? null);
    $medicalReviewer = trim((string) ($_POST['medical_reviewer'] ?? '')) ?: null;

    $seoTitle = trim((string) ($_POST['seo_title'] ?? '')) ?: null;
    $seoDescription = trim((string) ($_POST['seo_description'] ?? '')) ?: null;
    $canonical = trim((string) ($_POST['canonical_url'] ?? '')) ?: null;
    $ogTitle = trim((string) ($_POST['og_title'] ?? '')) ?: null;
    $ogDescription = trim((string) ($_POST['og_description'] ?? '')) ?: null;
    $ogImage = trim((string) ($_POST['og_image'] ?? '')) ?: null;
    $robotsIndex = isset($_POST['robots_index']) ? 1 : 0;
    $robotsFollow = isset($_POST['robots_follow']) ? 1 : 0;

    if ($title === '') {
        flash('error', 'عنوان مقاله الزامی است.');
        redirect($id ? '/admin/articles/' . $id : '/admin/articles/create');
    }

    // Preserve existing slug unless admin changed it
    $existing = $id ? $svc->findById($id) : null;
    if ($existing && $slugInput === (string) $existing['slug']) {
        $slug = (string) $existing['slug'];
    } elseif ($slugInput !== '') {
        $slug = $svc->uniqueSlug($slugInput, $id ?: null);
    } else {
        $slug = $svc->uniqueSlug($title, $id ?: null);
    }

    if ($status === 'published' && !$publishedAt) {
        $publishedAt = date('Y-m-d H:i:s');
    }
    if ($status === 'scheduled' && !$scheduledAt) {
        flash('error', 'برای انتشار زمان‌بندی‌شده، تاریخ زمان‌بندی را مشخص کنید.');
        redirect($id ? '/admin/articles/' . $id : '/admin/articles/create');
    }

    $reading = ArticleService::estimateReadingTime($content);

    $fields = [
        'category_id' => $categoryId,
        'author_id' => $authorId,
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
        'cover' => $cover,
        'status' => $status,
        'published_at' => $publishedAt,
        'scheduled_at' => $scheduledAt,
        'reading_time' => $reading,
        'seo_title' => $seoTitle,
        'seo_description' => $seoDescription,
        'canonical_url' => $canonical,
        'og_title' => $ogTitle,
        'og_description' => $ogDescription,
        'og_image' => $ogImage,
        'robots_index' => $robotsIndex,
        'robots_follow' => $robotsFollow,
        'medical_reviewer' => $medicalReviewer,
        'meta_title' => $seoTitle,
        'meta_description' => $seoDescription,
    ];

    if ($id > 0) {
        $sets = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = :$k";
        }
        $fields['id'] = $id;
        db()->prepare('UPDATE blog_posts SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($fields);
        audit('article.update', 'blog_posts', $id);
        flash('success', 'مقاله ذخیره شد.');
        redirect('/admin/articles/' . $id);
    }

    $cols = array_keys($fields);
    $sql = 'INSERT INTO blog_posts (`' . implode('`,`', $cols) . '`) VALUES (:' . implode(',:', $cols) . ')';
    db()->prepare($sql)->execute($fields);
    $newId = (int) db()->lastInsertId();
    audit('article.create', 'blog_posts', $newId);
    flash('success', 'مقاله ایجاد شد.');
    redirect('/admin/articles/' . $newId);
});

$router->post('/admin/articles/action', static function (): void {
    Auth::requireAdmin('cms.blog');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $svc = new ArticleService(db());
    $article = $svc->findById($id);
    if (!$article) {
        flash('error', 'مقاله یافت نشد.');
        redirect('/admin/articles');
    }

    switch ($action) {
        case 'publish':
            db()->prepare("UPDATE blog_posts SET status='published', published_at=COALESCE(published_at, NOW()) WHERE id=?")->execute([$id]);
            flash('success', 'مقاله منتشر شد.');
            break;
        case 'unpublish':
            db()->prepare("UPDATE blog_posts SET status='draft' WHERE id=?")->execute([$id]);
            flash('success', 'مقاله به پیش‌نویس منتقل شد.');
            break;
        case 'archive':
            db()->prepare("UPDATE blog_posts SET status='archived' WHERE id=?")->execute([$id]);
            flash('success', 'مقاله بایگانی شد.');
            break;
        case 'delete':
            db()->prepare('UPDATE blog_posts SET deleted_at=NOW() WHERE id=?')->execute([$id]);
            flash('success', 'مقاله حذف شد.');
            break;
        case 'duplicate':
            $slug = $svc->uniqueSlug($article['slug'] . '-copy');
            db()->prepare(
                "INSERT INTO blog_posts
                 (category_id, author_id, title, slug, excerpt, content, cover, status, reading_time,
                  seo_title, seo_description, og_title, og_description, og_image, robots_index, robots_follow, medical_reviewer)
                 VALUES (?,?,?,?,?,?,?,'draft',?,?,?,?,?,?,?,?,?)"
            )->execute([
                $article['category_id'],
                Auth::adminId(),
                $article['title'] . ' (کپی)',
                $slug,
                $article['excerpt'],
                $article['content'],
                $article['cover'],
                $article['reading_time'],
                $article['seo_title'],
                $article['seo_description'],
                $article['og_title'],
                $article['og_description'],
                $article['og_image'],
                $article['robots_index'] ?? 1,
                $article['robots_follow'] ?? 1,
                $article['medical_reviewer'],
            ]);
            flash('success', 'نسخه کپی ایجاد شد.');
            redirect('/admin/articles/' . (int) db()->lastInsertId());
            break;
        default:
            flash('error', 'عملیات نامعتبر.');
    }
    audit('article.' . $action, 'blog_posts', $id);
    redirect('/admin/articles');
});

// Categories
$router->get('/admin/article-categories', static function (): void {
    Auth::requireAdmin('cms.blog');
    $items = db()->query(
        'SELECT * FROM blog_categories WHERE deleted_at IS NULL ORDER BY sort_order, name'
    )->fetchAll();
    view('admin/article-categories', ['title' => 'دسته‌بندی مقالات', 'items' => $items]);
});

$router->post('/admin/article-categories/save', static function (): void {
    Auth::requireAdmin('cms.blog');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '') {
        flash('error', 'نام دسته الزامی است.');
        redirect('/admin/article-categories');
    }
    if ($slug === '') {
        $slug = ArticleService::slugify($name);
    }
    if ($id > 0) {
        db()->prepare('UPDATE blog_categories SET name=?, slug=?, sort_order=?, is_active=? WHERE id=?')
            ->execute([$name, $slug, $sort, $active, $id]);
    } else {
        db()->prepare('INSERT INTO blog_categories (name, slug, sort_order, is_active) VALUES (?,?,?,?)')
            ->execute([$name, $slug, $sort, $active]);
    }
    flash('success', 'دسته ذخیره شد.');
    redirect('/admin/article-categories');
});

$router->post('/admin/article-categories/delete', static function (): void {
    Auth::requireAdmin('cms.blog');
    Csrf::assertValid();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('UPDATE blog_categories SET deleted_at=NOW(), is_active=0 WHERE id=?')->execute([$id]);
    flash('success', 'دسته حذف شد.');
    redirect('/admin/article-categories');
});
