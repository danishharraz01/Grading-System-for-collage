<?php
/**
 * CSV export of results. Uses the same search / grade filter / sort as the results page.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$f        = student_filters($_GET);
$students = fetch_students($f);

/** Stop spreadsheet apps from treating a cell as a formula (CSV injection). */
function csv_safe($value): string
{
    $value = (string)$value;
    return ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) ? "'" . $value : $value;
}

$filename = 'alias-kolej-results-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly

fputcsv($out, ['No', 'Student Name', 'Matric No', 'Test 1 (/20)', 'Test 2 (/30)', 'Final Exam (/50)', 'Total (/100)', 'Grade'], ',', '"', '');
foreach ($students as $i => $s) {
    fputcsv($out, [
        $i + 1,
        csv_safe($s['student_name']),
        csv_safe($s['matric_no']),
        fmt_mark($s['test1']),
        fmt_mark($s['test2']),
        fmt_mark($s['final_exam']),
        fmt_mark($s['total']),
        $s['grade'],
    ], ',', '"', '');
}
fclose($out);
