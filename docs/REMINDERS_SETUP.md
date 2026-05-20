# Email-напоминания за день до приёма

Скрипт `cli/send_reminders.php` находит записи на завтра (статус «Создана»/«Подтверждена»)
без отметки `reminder_sent_at`, отправляет пациенту письмо и ставит отметку (повторно не дублирует).

## Ручной запуск
```
"C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe" cli\send_reminders.php
```

## Автоматический запуск (Windows, раз в день)
Планировщик заданий → Создать задачу → ежедневно (например, 09:00) →
Действие: запуск программы
- Программа: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- Аргументы: `cli\send_reminders.php`
- Рабочая папка: `C:\all-stuff\soft\OpenServer\home\dentistry.local`

В dev (пустой SMTP в config.php) письма пишутся в `data/mail.log`.
Для реальной отправки заполнить `SMTP_USER`/`SMTP_PASS` в `config.php`.
