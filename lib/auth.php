<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

final class Auth
{
    public static function isAuthenticated(): bool
    {
        return Session::userId() !== null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        return Session::user();
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u === null ? null : (string) $u['role_code'];
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, $roles, true);
    }

    /** Перенаправляет на /login.php если не аутентифицирован, и на главную если роль не подходит. */
    public static function requireRole(string ...$roles): void
    {
        if (!self::isAuthenticated()) {
            header('Location: /login.php');
            exit;
        }
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            header('Location: /');
            exit;
        }
    }
}
