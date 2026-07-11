<?php
/**
 * Shared export helpers — PDF (dompdf), Excel (PhpSpreadsheet), and CSV.
 * Every export in the app (reports, receipts) funnels through these three
 * functions so headers/streaming are handled consistently in one place.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

function export_pdf(string $html, string $filename): void
{
    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $dompdf->output();
    exit;
}

/**
 * @param string[] $headers
 * @param array<int, array<int, string|int|float|null>> $rows
 */
function export_csv(array $headers, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

/**
 * @param string[] $headers
 * @param array<int, array<int, string|int|float|null>> $rows
 */
function export_excel(array $headers, array $rows, string $filename, string $sheetTitle = 'Report'): void
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($sheetTitle, 0, 31));

    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')
        ->getFont()->setBold(true);

    $sheet->fromArray($rows, null, 'A2');

    foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
