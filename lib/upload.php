<?php
declare(strict_types=1);

const IMAGE_MAX_BYTES = 2097152; // 2 МБ

/** Разрешённые MIME → расширение. */
function image_allowed_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

/**
 * Валидация загруженного изображения.
 * @param array<string,mixed> $file элемент из $_FILES
 * @return string|null  текст ошибки или null если всё ок
 */
function image_upload_validate(array $file, int $maxBytes = IMAGE_MAX_BYTES): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return 'Некорректная загрузка файла.';
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Файл не выбран.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Ошибка загрузки файла.';
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return 'Файл слишком большой (максимум 2 МБ).';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset(image_allowed_types()[$mime])) {
        return 'Допустимы только изображения JPEG, PNG или WebP.';
    }
    return null;
}

/** Расширение по реальному MIME файла (после успешной валидации). */
function image_ext_for(array $file): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    return image_allowed_types()[$mime] ?? 'bin';
}

/**
 * Сохранить загруженное изображение в каталог.
 * @return string|null публичный относительный путь или null при ошибке
 */
function image_save(array $file, string $destDirAbs, string $publicPrefix, string $basename): ?string
{
    if (!is_dir($destDirAbs) && !mkdir($destDirAbs, 0775, true) && !is_dir($destDirAbs)) {
        return null;
    }
    $ext   = image_ext_for($file);
    $fname = $basename . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest  = rtrim($destDirAbs, '/\\') . DIRECTORY_SEPARATOR . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return rtrim($publicPrefix, '/') . '/' . $fname;
}
