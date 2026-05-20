<?php
require_once __DIR__ . '/../lib/pdf.php';

$html = pdf_document('<h1>Протокол приёма</h1><p>Пациент: Иванов И.И. Сумма: 3 500,00 ₽.</p>', 'Тест');
$opt = new Dompdf\Options();
$opt->set('defaultFont', 'DejaVu Sans');
$d = new Dompdf\Dompdf($opt);
$d->loadHtml($html, 'UTF-8');
$d->setPaper('A4');
$d->render();
$out = $d->output();

assert(strlen($out) > 1000, 'PDF must be non-trivial');
assert(substr($out, 0, 5) === '%PDF-', 'Output must be a PDF');

echo "PDF smoke: OK\n";
