<?php
/**
 * SEB Setup Tool – CSV-Download der Warnliste
 */
require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$warnings = $SESSION->sebprep_warnings ?? [];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="seb_setup_warnungen_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM für Excel
fputcsv($out, ['Kurs-ID', 'Kursname', 'URL', 'Hinweis'], ';');

foreach ($warnings as $w) {
    fputcsv($out, [
        $w['courseid'],
        $w['name'],
        $w['url'],
        $w['warning'],
    ], ';');
}
fclose($out);
exit;
