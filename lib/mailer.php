<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class Mailer
{
    /**
     * Отправка письма. Возвращает true при успехе.
     * Если SMTP не настроен (пустой SMTP_USER) — пишет в data/mail.log
     * и тоже возвращает true.
     */
    public static function send(string $to, string $subject, string $bodyText): bool
    {
        $ok = (SMTP_USER === '') ? self::logToFile($to, $subject, $bodyText) : self::sendSmtp($to, $subject, $bodyText);
        try {
            DB::exec("INSERT INTO notification_log (recipient, subject) VALUES (:r, :s)", ['r' => $to, 's' => $subject]);
        } catch (Throwable $e) { error_log('[notification_log] ' . $e->getMessage()); }
        return $ok;
    }

    private static function sendSmtp(string $to, string $subject, string $bodyText): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $bodyText;
            $mail->isHTML(false);

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        }
    }

    private static function logToFile(string $to, string $subject, string $bodyText): bool
    {
        $logPath = __DIR__ . '/../data/mail.log';
        $entry  = '=== ' . date('Y-m-d H:i:s') . ' ===' . PHP_EOL;
        $entry .= "TO: $to" . PHP_EOL;
        $entry .= "SUBJECT: $subject" . PHP_EOL;
        $entry .= '---' . PHP_EOL;
        $entry .= $bodyText . PHP_EOL . PHP_EOL;
        return file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
