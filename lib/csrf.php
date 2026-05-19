<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function check(?string $token): bool
    {
        Session::start();
        if (!is_string($token) || empty($_SESSION[self::KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::KEY], $token);
    }

    /** HTML-сниппет <input> для форм. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /** Прерывает выполнение с 403, если токен невалиден. Использовать в обработчиках POST. */
    public static function requireValid(): void
    {
        if (!self::check($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('CSRF token mismatch');
        }
    }
}
