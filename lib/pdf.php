<?php
declare(strict_types=1);

require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** Обёртка чистого HTML-документа для PDF (кириллица через DejaVu Sans). */
function pdf_document(string $bodyHtml, string $title = ''): string
{
    $t = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<html><head><meta charset="utf-8"><title>' . $t . '</title><style>'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:12px;color:#222;}'
        . 'h1{font-size:18px;margin:0 0 10px;} h2{font-size:14px;margin:14px 0 6px;}'
        . 'table{width:100%;border-collapse:collapse;margin:8px 0;}'
        . 'td,th{padding:5px 7px;border-bottom:1px solid #ddd;text-align:left;}'
        . '.muted{color:#666;} .right{text-align:right;} .tot{font-weight:bold;}'
        . '.row{margin:3px 0;} .lbl{display:inline-block;width:140px;color:#666;}'
        . '</style></head><body>' . $bodyHtml . '</body></html>';
}

/** Отрендерить HTML в PDF и отдать инлайн (Content-Type: application/pdf), затем exit. */
function pdf_render_inline(string $html, string $filename): void
{
    $opt = new Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($opt);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => false]);
    exit;
}
