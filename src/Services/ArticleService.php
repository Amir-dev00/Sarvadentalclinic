<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

final class ArticleService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** Promote due scheduled posts to published. */
    public function promoteScheduled(): void
    {
        $this->db->exec(
            "UPDATE blog_posts
             SET status='published', published_at = COALESCE(published_at, scheduled_at, NOW())
             WHERE status='scheduled'
               AND deleted_at IS NULL
               AND scheduled_at IS NOT NULL
               AND scheduled_at <= NOW()"
        );
    }

    public function publicWhere(): string
    {
        return "p.deleted_at IS NULL
            AND (
              (p.status='published' AND (p.published_at IS NULL OR p.published_at <= NOW()))
              OR (p.status='scheduled' AND p.scheduled_at IS NOT NULL AND p.scheduled_at <= NOW())
            )";
    }

    /** @return list<array<string,mixed>> */
    public function listPublished(int $limit = 12, int $offset = 0, ?int $categoryId = null): array
    {
        $this->promoteScheduled();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                       CONCAT(a.first_name,' ',a.last_name) AS author_name
                FROM blog_posts p
                LEFT JOIN blog_categories c ON c.id = p.category_id
                LEFT JOIN admin_users a ON a.id = p.author_id
                WHERE {$this->publicWhere()}";
        $params = [];
        if ($categoryId) {
            $sql .= ' AND p.category_id = :cid';
            $params['cid'] = $categoryId;
        }
        $sql .= ' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT :lim OFFSET :off';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $this->promoteScheduled();
        $stmt = $this->db->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    CONCAT(a.first_name,' ',a.last_name) AS author_name
             FROM blog_posts p
             LEFT JOIN blog_categories c ON c.id = p.category_id
             LEFT JOIN admin_users a ON a.id = p.author_id
             WHERE p.slug = :slug AND {$this->publicWhere()}
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id, bool $includeDeleted = false): ?array
    {
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                       CONCAT(a.first_name,' ',a.last_name) AS author_name
                FROM blog_posts p
                LEFT JOIN blog_categories c ON c.id = p.category_id
                LEFT JOIN admin_users a ON a.id = p.author_id
                WHERE p.id = :id";
        if (!$includeDeleted) {
            $sql .= ' AND p.deleted_at IS NULL';
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function related(array $article, int $limit = 3): array
    {
        $params = ['id' => (int) $article['id'], 'lim' => $limit];
        $sql = "SELECT p.*, c.name AS category_name
                FROM blog_posts p
                LEFT JOIN blog_categories c ON c.id = p.category_id
                WHERE {$this->publicWhere()} AND p.id <> :id";
        if (!empty($article['category_id'])) {
            $sql .= ' AND p.category_id = :cid';
            $params['cid'] = (int) $article['category_id'];
        }
        $sql .= ' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT :lim';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('id', $params['id'], PDO::PARAM_INT);
        if (isset($params['cid'])) {
            $stmt->bindValue('cid', $params['cid'], PDO::PARAM_INT);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (count($rows) >= $limit || empty($article['category_id'])) {
            return $rows;
        }
        // Fill with other recent
        $have = array_column($rows, 'id');
        $have[] = (int) $article['id'];
        $placeholders = implode(',', array_fill(0, count($have), '?'));
        $fill = $this->db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM blog_posts p
             LEFT JOIN blog_categories c ON c.id = p.category_id
             WHERE {$this->publicWhere()} AND p.id NOT IN ($placeholders)
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT " . (int) ($limit - count($rows))
        );
        $fill->execute($have);
        return array_merge($rows, $fill->fetchAll());
    }

    public static function estimateReadingTime(?string $html): int
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return 1;
        }
        // Approximate Persian words
        $words = preg_split('/\s+/u', $text) ?: [];
        $minutes = (int) ceil(count($words) / 180);
        return max(1, $minutes);
    }

    public static function seoStatus(array $post): string
    {
        $score = 0;
        if (trim((string) ($post['seo_title'] ?? $post['meta_title'] ?? '')) !== '') {
            $score++;
        }
        if (trim((string) ($post['seo_description'] ?? $post['meta_description'] ?? '')) !== '') {
            $score++;
        }
        if (trim((string) ($post['slug'] ?? '')) !== '') {
            $score++;
        }
        if (trim((string) ($post['cover'] ?? $post['og_image'] ?? '')) !== '') {
            $score++;
        }
        return match (true) {
            $score >= 4 => 'good',
            $score >= 2 => 'needs_improvement',
            default => 'missing',
        };
    }

    public static function seoStatusLabel(string $status): string
    {
        return match ($status) {
            'good' => 'خوب',
            'needs_improvement' => 'نیاز به بهبود',
            default => 'ناقص',
        };
    }

    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = self::slugify($base);
        if ($slug === '') {
            $slug = 'article-' . bin2hex(random_bytes(3));
        }
        $candidate = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT id FROM blog_posts WHERE slug = ? AND deleted_at IS NULL';
            $params = [$candidate];
            if ($ignoreId) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            $stmt = $this->db->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i;
            $i++;
        }
    }

    public static function slugify(string $text): string
    {
        $text = trim(mb_strtolower($text, 'UTF-8'));
        // Keep Persian letters and latin/digits
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', '', $text) ?? '';
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? '';
        $text = trim($text, '-');
        // If mostly Persian, use a transliteration-ish fallback with timestamp for ASCII URLs preference
        if ($text === '' || preg_match('/\p{Arabic}/u', $text)) {
            $ascii = preg_replace('/[^a-z0-9\-]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: '') ?? '';
            $ascii = trim(strtolower($ascii), '-');
            if ($ascii !== '') {
                return $ascii;
            }
            // Keep unicode slug for Persian (modern browsers/SEO ok) or generate code
            if ($text !== '') {
                return $text;
            }
            return 'article';
        }
        return $text;
    }
}
