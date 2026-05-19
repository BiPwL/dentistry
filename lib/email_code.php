<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class EmailCode
{
    /**
     * Выдать новый код. Удаляет предыдущие коды того же (email, purpose),
     * чтобы пользователь мог перезапросить код, и в системе оставался ровно один действующий.
     *
     * @param array<string,mixed>|null $payload  опциональный payload (для регистрации)
     * @return string  шестизначный код
     */
    public static function issue(string $email, string $purpose, ?array $payload = null): string
    {
        self::assertPurpose($purpose);
        DB::exec(
            'DELETE FROM email_code WHERE email = :e AND purpose = :p',
            ['e' => $email, 'p' => $purpose]
        );

        $code = str_pad((string) random_int(0, 999999), EMAIL_CODE_LENGTH, '0', STR_PAD_LEFT);
        DB::exec(
            'INSERT INTO email_code (email, code, purpose, payload_json, expires_at)
             VALUES (:e, :c, :p, :j, DATE_ADD(NOW(), INTERVAL :t MINUTE))',
            [
                'e' => $email,
                'c' => $code,
                'p' => $purpose,
                'j' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                't' => EMAIL_CODE_TTL_MIN,
            ]
        );
        return $code;
    }

    /**
     * Найти неистёкший код. Возвращает строку из БД (с payload_json) или null.
     *
     * @return array<string,mixed>|null
     */
    public static function find(string $email, string $purpose, string $code): ?array
    {
        self::assertPurpose($purpose);
        return DB::one(
            'SELECT * FROM email_code
              WHERE email = :e AND purpose = :p AND code = :c AND expires_at > NOW()',
            ['e' => $email, 'p' => $purpose, 'c' => $code]
        );
    }

    /**
     * Найти и удалить код. Возвращает payload (array) если код валиден, null иначе.
     * Гарантирует одноразовое использование.
     *
     * @return array<string,mixed>|null  payload (или пустой массив, если payload не был задан)
     */
    public static function consume(string $email, string $purpose, string $code): ?array
    {
        $row = self::find($email, $purpose, $code);
        if ($row === null) return null;

        DB::exec('DELETE FROM email_code WHERE id = :id', ['id' => $row['id']]);
        if (empty($row['payload_json'])) return [];
        $payload = json_decode($row['payload_json'], true);
        return is_array($payload) ? $payload : [];
    }

    /** Удалить просроченные коды (можно вызывать периодически). */
    public static function purgeExpired(): int
    {
        return DB::exec('DELETE FROM email_code WHERE expires_at <= NOW()');
    }

    private static function assertPurpose(string $purpose): void
    {
        if (!in_array($purpose, ['register', 'reset'], true)) {
            throw new InvalidArgumentException("Unknown purpose: $purpose");
        }
    }
}
