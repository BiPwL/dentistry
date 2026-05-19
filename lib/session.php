<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = self::userId();
        if ($id === null) return null;
        $user = DB::one(
            'SELECT u.*, r.code AS role_code, r.name AS role_name
               FROM user u JOIN role r ON r.id = u.role_id
              WHERE u.id = :id',
            ['id' => $id]
        );
        return $user;
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
