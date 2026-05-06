<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']               = 'SEB-Vorbereitung in Modulkursen einrichten';
$string['pluginname_desc']          = 'Fügt den SEB-Vorbereitungsabschnitt in Modulkurse ein und richtet den Fortschrittsbalken ein.';

$string['step1_title']              = 'Kursabschnitt einfügen';
$string['step1_desc']               = 'Kopiert Abschnitt 1 aus dem Templatekurs in alle Zielkurse. Schaltet die Abschlussverfolgung ein und setzt eine eindeutige ID (idnumber) auf den Subcourse-Link.';
$string['step2_title']              = 'Fortschrittsbalken hinzufügen';
$string['step2_desc']               = 'Sucht in jedem Zielkurs den Subcourse-Link anhand seiner eindeutigen ID und fügt den Fortschrittsbalken «SEB Vorbereitung» in die rechte Seitenleiste ein.';
$string['step2_idnumber_hint']      = 'Sucht Subcourse mit idnumber:';

$string['plugin_intro']             = 'Dieses Tool bereitet Modulkurse von Dozierenden für SEB-Prüfungen vor: Es kopiert den SEB-Vorbereitungsabschnitt aus einem Templatekurs in alle Zielkurse (Schritt 1) und fügt den zugehörigen Fortschrittsbalken ein (Schritt 2).';
$string['field_courseurls']         = 'Kurs-URLs (Zielkurse, eine pro Zeile)';
$string['field_courseurls_help']    = 'Eine Moodle-Kurs-URL pro Zeile, z.B. https://moodle.fhgr.ch/course/view.php?id=1234';
$string['field_language']           = 'Sprache / Language';
$string['field_language_help']      = 'Deutsch: kopiert Abschnitt 1 aus dem Templatekurs. English: kopiert Abschnitt 2.';
$string['field_frist_probe']        = 'Frist Probeprüfung';
$string['field_frist_probe_help']   = 'Ersetzt {{FRIST_PROBE}} im Subcourse-Text. z.B. 22.05.2026';
$string['field_frist_ersatz']       = 'Frist Ersatzgeräte';
$string['field_frist_ersatz_help']  = 'Ersetzt {{FRIST_ERSATZ}} im Subcourse-Text. z.B. 29.05.2026';
$string['field_semestertag_help']   = 'z.B. FS26 oder HS26. Wird an den Abschnittstitel angehängt und als Teil der idnumber verwendet (SEB-PREP-FS26).';
$string['field_templatecourseid']   = 'Kurs-ID des Templatekurses';
$string['field_templatecourseid_help'] = 'Abschnitt 1 dieses Kurses wird in alle Zielkurse kopiert.';

$string['btn_clear']                = 'Felder leeren';
$string['confirm_clear']            = 'Alle Felder zurücksetzen?';
$string['btn_execute']              = 'Jetzt ausführen';
$string['btn_back']                 = '← Zurück';

$string['preview_title']            = 'Vorschau';
$string['col_courseid']             = 'Kurs-ID';
$string['col_coursename']           = 'Kursname';
$string['col_url']                  = 'URL';
$string['col_status']               = 'Status';
$string['col_warning']              = 'Hinweis';

$string['status_ready']             = '✓ Bereit';
$string['status_invalid']           = '✗ URL ungültig / Kurs nicht gefunden';
$string['status_done']              = '✅ Erledigt';
$string['status_skipped']           = '⏭ Übersprungen';
$string['status_error']             = '❌ Fehler';

$string['warn_existing_subcourse']  = '⚠️ Bestehender Subcourse-Link gefunden (refcourse={$a}) – bitte manuell prüfen';
$string['warn_subcourse_exists']    = '⚠️ Subcourse-Link für {$a} existiert bereits im Kurs – übersprungen';
$string['warn_section_exists']      = '⚠️ Abschnitt «{$a}» existiert bereits – übersprungen';
$string['warn_idnumber_exists']     = '⚠️ Subcourse-Link mit ID «{$a}» existiert bereits – bitte ID-Nummer am Subcourse-Link manuell leeren und nochmals ausführen';
$string['warn_block_exists']        = '⚠️ Fortschrittsbalken «{$a}» existiert bereits – übersprungen';
$string['warn_block_updated']       = 'ℹ️ Bestehender Fortschrittsbalken wurde mit korrekter Aktivität aktualisiert';
$string['error_subcourse_not_found'] = '✗ Kein Subcourse mit idnumber «{$a}» gefunden – zuerst Schritt 1 ausführen';

$string['result_title']             = 'Verarbeitungsprotokoll';
$string['result_warnings_title']    = 'Kurse mit Hinweisen';
$string['result_warnings_desc']     = 'Diese Kurse haben Hinweise erhalten. Bitte manuell prüfen:';
$string['download_warnings']        = 'Hinweisliste als CSV herunterladen';
$string['no_warnings']              = '✅ Keine Hinweise – alle Kurse wurden sauber verarbeitet.';
$string['processed']                = 'Verarbeitet: {$a->done} von {$a->total} Kursen erfolgreich.';
$string['confirm_execute']          = 'Soll die Verarbeitung jetzt gestartet werden? Diese Aktion kann nicht rückgängig gemacht werden.';
