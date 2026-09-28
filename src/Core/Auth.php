<?php

declare(strict_types=1);

namespace Sarva\Core;

use PDO;

final class Auth
{
    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function patientId(): ?int
    {
        return isset($_SESSION['patient_id']) ? (int) $_SESSION['patient_id'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::adminId() !== null;
    }

    public static function isPatient(): bool
    {
        return self::patientId() !== null;
    }

    public static function loginAdmin(int $id): void
    {
        Session::regenerate();
        $_SESSION['admin_id'] = $id;
        unset($_SESSION['patient_id']);
    }

    public static function loginPatient(int $id): void
    {
        Session::regenerate();
        $_SESSION['patient_id'] = $id;
        unset($_SESSION['admin_id']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function adminHasPermission(string $permission): bool
    {
        $adminId = self::adminId();
        if ($adminId === null) {
            return false;
        }

        static $cache = [];
        if (isset($cache[$adminId])) {
            return in_array($permission, $cache[$adminId], true) || in_array('*', $cache[$adminId], true);
        }

        $sql = 'SELECT p.slug
                FROM admin_users au
                JOIN roles r ON r.id = au.role_id
                LEFT JOIN role_permissions rp ON rp.role_id = r.id
                LEFT JOIN permissions p ON p.id = rp.permission_id
                WHERE au.id = :id AND au.is_active = 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $adminId]);
        $perms = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            if ($slug) {
                $perms[] = $slug;
            }
        }

        // Super admin role slug
        $roleStmt = Database::connection()->prepare(
            'SELECT r.slug FROM admin_users au JOIN roles r ON r.id = au.role_id WHERE au.id = :id'
        );
        $roleStmt->execute(['id' => $adminId]);
        if ($roleStmt->fetchColumn() === 'super_admin') {
            $perms[] = '*';
        }

        $cache[$adminId] = $perms;
        return in_array($permission, $perms, true) || in_array('*', $perms, true);
    }

    public static function requireAdmin(?string $permission = null): void
    {
        if (!self::isAdmin()) {
            redirect('/admin/login');
        }
        if ($permission !== null && !self::adminHasPermission($permission)) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            exit;
        }
    }

    /** @param string[] $permissions */
    public static function requireAdminAny(array $permissions): void
    {
        if (!self::isAdmin()) {
            redirect('/admin/login');
        }
        foreach ($permissions as $permission) {
            if (self::adminHasPermission($permission)) {
                return;
            }
        }
        http_response_code(403);
        echo 'دسترسی غیرمجاز';
        exit;
    }

    public static function requirePatient(): void
    {
        if (!self::isPatient()) {
            redirect('/auth');
        }
    }
}
