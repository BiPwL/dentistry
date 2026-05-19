<?php
require_once __DIR__ . '/../lib/upload.php';

// Валидный 1x1 PNG
$png = tempnam(sys_get_temp_dir(), 'img');
file_put_contents($png, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
));
$ok = ['error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'tmp_name' => $png];
assert(image_upload_validate($ok) === null, 'valid PNG must pass');
assert(image_ext_for($ok) === 'png', 'PNG ext must be png');

// Текстовый файл — отклонить (MIME не image/*)
$txt = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($txt, 'this is not an image, just text');
$txtFile = ['error' => UPLOAD_ERR_OK, 'size' => filesize($txt), 'tmp_name' => $txt];
assert(image_upload_validate($txtFile) !== null, 'text file must be rejected');

// Превышение размера
$big = ['error' => UPLOAD_ERR_OK, 'size' => 5000000, 'tmp_name' => $png];
assert(image_upload_validate($big, 2097152) !== null, 'oversize must be rejected');

// Файл не выбран
$none = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => ''];
assert(image_upload_validate($none) !== null, 'no-file must be rejected');

unlink($png);
unlink($txt);
echo "Upload smoke: OK\n";
