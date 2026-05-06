<?php
/**
 * SEB-Vorbereitung in Modulkursen einrichten
 */
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/sebprep/index.php'));
$PAGE->set_title(get_string('pluginname', 'tool_sebprep'));
$PAGE->set_heading(get_string('pluginname', 'tool_sebprep'));
$PAGE->set_pagelayout('admin');

$action           = optional_param('action', '', PARAM_ALPHA);
$step             = optional_param('step', 1, PARAM_INT);

// Session leeren falls angefordert
if (optional_param('clearsession', 0, PARAM_INT)) {
    unset($SESSION->sebprep_courseurls);
    unset($SESSION->sebprep_templatecourseid);
    unset($SESSION->sebprep_semestertag);
    unset($SESSION->sebprep_frist_probe);
    unset($SESSION->sebprep_frist_ersatz);
    unset($SESSION->sebprep_language);
    unset($SESSION->sebprep_warnings);
    redirect(new moodle_url('/admin/tool/sebprep/index.php'));
}

// Werte aus POST, dann Session, dann Default
$rawurls          = optional_param('courseurls',       '', PARAM_RAW);
$templatecourseid = optional_param('templatecourseid', 0,  PARAM_INT);
$semestertag      = optional_param('semestertag',      '', PARAM_TEXT);
$fristprobe       = optional_param('frist_probe',      '', PARAM_TEXT);
$fristersatz      = optional_param('frist_ersatz',     '', PARAM_TEXT);
$language         = optional_param('language',         '', PARAM_ALPHA);

if ($rawurls)          $SESSION->sebprep_courseurls       = $rawurls;
if ($templatecourseid) $SESSION->sebprep_templatecourseid = $templatecourseid;
if ($semestertag)      $SESSION->sebprep_semestertag      = $semestertag;
if ($fristprobe)       $SESSION->sebprep_frist_probe      = $fristprobe;
if ($fristersatz)      $SESSION->sebprep_frist_ersatz     = $fristersatz;
if ($language)         $SESSION->sebprep_language         = $language;

if (!$rawurls)          $rawurls          = $SESSION->sebprep_courseurls       ?? '';
if (!$templatecourseid) $templatecourseid = $SESSION->sebprep_templatecourseid ?? 0;
if (!$semestertag)      $semestertag      = $SESSION->sebprep_semestertag      ?? 'FS26';
if (!$fristprobe)       $fristprobe       = $SESSION->sebprep_frist_probe      ?? '';
if (!$fristersatz)      $fristersatz      = $SESSION->sebprep_frist_ersatz     ?? '';
if (!$language)         $language         = $SESSION->sebprep_language         ?? 'de';

// ── AKTION: Ausführen ────────────────────────────────────────────────────────
if ($action === 'execute' && confirm_sesskey()) {

    $entries  = tool_sebprep_parse_urls($rawurls);
    $entries  = tool_sebprep_validate_courses($entries);
    $warnings = [];
    $results  = [];
    $done     = 0;

    foreach ($entries as $entry) {
        if (!$entry['valid']) {
            $results[] = [
                'url'      => $entry['url'],
                'courseid' => $entry['courseid'] ?? '–',
                'name'     => '–',
                'status'   => get_string('status_invalid', 'tool_sebprep'),
                'warning'  => '',
            ];
            continue;
        }

        try {
            if ($step === 2) {
                $r = tool_sebprep_process_step2($entry['courseid'], $semestertag, $language);
            } else {
                $r = tool_sebprep_process_step1($entry['courseid'], $templatecourseid, $semestertag, $fristprobe, $fristersatz, $language);
            }
            if ($r['success']) $done++;
            if ($r['warning']) {
                $warnings[] = [
                    'courseid' => $entry['courseid'],
                    'name'     => $entry['course']->fullname,
                    'url'      => $entry['url'],
                    'warning'  => $r['warning'],
                ];
            }
            $results[] = [
                'url'      => $entry['url'],
                'courseid' => $entry['courseid'],
                'name'     => $entry['course']->fullname,
                'status'   => $r['success']
                    ? get_string('status_done', 'tool_sebprep')
                    : get_string('status_skipped', 'tool_sebprep'),
                'warning'  => $r['warning'] ?? $r['message'] ?? '',
            ];
        } catch (Throwable $e) {
            $results[] = [
                'url'      => $entry['url'],
                'courseid' => $entry['courseid'],
                'name'     => $entry['course']->fullname ?? '–',
                'status'   => get_string('status_skipped', 'tool_sebprep'),
                'warning'  => $e->getMessage()
                    . ' | ' . basename($e->getFile()) . ':' . $e->getLine()
                    . ' | ' . str_replace("\n", ' ← ', substr($e->getTraceAsString(), 0, 400)),
            ];
        }
    }

    $SESSION->sebprep_warnings = $warnings;

    echo $OUTPUT->header();
    $steplabel = $step === 2
        ? get_string('step2_title', 'tool_sebprep')
        : get_string('step1_title', 'tool_sebprep');
    echo $OUTPUT->heading(get_string('result_title', 'tool_sebprep') . ' – ' . $steplabel);
    echo html_writer::tag('p', get_string('processed', 'tool_sebprep',
        (object)['done' => $done, 'total' => count($entries)]));

    $table = new html_table();
    $table->head = [
        get_string('col_courseid', 'tool_sebprep'),
        get_string('col_coursename', 'tool_sebprep'),
        get_string('col_status', 'tool_sebprep'),
        get_string('col_warning', 'tool_sebprep'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($results as $row) {
        $table->data[] = [
            html_writer::link($row['url'], $row['courseid'], ['target' => '_blank']),
            $row['name'],
            $row['status'],
            $row['warning'],
        ];
    }
    echo html_writer::table($table);

    if (!empty($warnings)) {
        echo $OUTPUT->heading(get_string('result_warnings_title', 'tool_sebprep'), 3);
        echo html_writer::tag('p', get_string('result_warnings_desc', 'tool_sebprep'));
        $wt = new html_table();
        $wt->head = ['Kurs-ID', 'Kursname', 'URL', 'Hinweis'];
        $wt->attributes['class'] = 'generaltable';
        foreach ($warnings as $w) {
            $wt->data[] = [$w['courseid'], $w['name'], $w['url'], $w['warning']];
        }
        echo html_writer::table($wt);
        $dlurl = new moodle_url('/admin/tool/sebprep/download_warnings.php');
        echo html_writer::link($dlurl, get_string('download_warnings', 'tool_sebprep'),
            ['class' => 'btn btn-secondary mt-2']);
    } else {
        echo html_writer::tag('p', get_string('no_warnings', 'tool_sebprep'),
            ['class' => 'alert alert-success']);
    }

    echo html_writer::tag('p',
        html_writer::link(new moodle_url('/admin/tool/sebprep/index.php'),
            get_string('btn_back', 'tool_sebprep'), ['class' => 'btn btn-primary mt-3']));
    echo $OUTPUT->footer();
    exit;
}

// ── AKTION: Vorschau ─────────────────────────────────────────────────────────
if ($action === 'preview' && $rawurls !== '') {

    $entries = tool_sebprep_parse_urls($rawurls);
    $entries = tool_sebprep_validate_courses($entries);

    $steplabel = $step === 2
        ? get_string('step2_title', 'tool_sebprep')
        : get_string('step1_title', 'tool_sebprep');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('preview_title', 'tool_sebprep') . ' – ' . $steplabel);

    $table = new html_table();
    $table->head = [
        get_string('col_courseid', 'tool_sebprep'),
        get_string('col_coursename', 'tool_sebprep'),
        get_string('col_url', 'tool_sebprep'),
        get_string('col_status', 'tool_sebprep'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($entries as $entry) {
        $status = $entry['valid']
            ? get_string('status_ready', 'tool_sebprep')
            : get_string('status_invalid', 'tool_sebprep');
        $table->data[] = [
            $entry['courseid'] ?? '–',
            $entry['course']->fullname ?? '–',
            html_writer::link($entry['url'], shorten_text($entry['url'], 60), ['target' => '_blank']),
            $status,
        ];
    }
    echo html_writer::table($table);

    // Konfigurations-Zusammenfassung
    echo html_writer::start_div('alert alert-info mt-3');
    echo html_writer::tag('strong', 'Konfiguration:') . html_writer::empty_tag('br');
    echo 'Semester-Tag: ' . htmlspecialchars($semestertag) . html_writer::empty_tag('br');
    if ($step === 1) {
        echo 'Templatekurs-ID: ' . (int)$templatecourseid . html_writer::empty_tag('br');
        echo 'Subcourse idnumber wird gesetzt auf: SEB-PREP-' . strtoupper($semestertag);
    } else {
        echo 'Sucht Subcourse mit idnumber: SEB-PREP-' . strtoupper($semestertag) . html_writer::empty_tag('br');
        echo 'Fügt Fortschrittsbalken «SEB Vorbereitung» hinzu';
    }
    echo html_writer::end_div();

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/admin/tool/sebprep/index.php'),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',          'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',           'value' => 'execute']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'step',             'value' => $step]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseurls',       'value' => $rawurls]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'templatecourseid', 'value' => $templatecourseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'semestertag',      'value' => $semestertag]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frist_probe',      'value' => $fristprobe]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frist_ersatz',     'value' => $fristersatz]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'language',         'value' => $language]);

    echo html_writer::tag('p', get_string('confirm_execute', 'tool_sebprep'),
        ['class' => 'alert alert-warning mt-3']);

    echo html_writer::tag('button', get_string('btn_execute', 'tool_sebprep'),
        ['type' => 'submit', 'class' => 'btn btn-danger mr-2',
         'onclick' => 'return confirm("' . get_string('confirm_execute', 'tool_sebprep') . '")']);
    echo html_writer::link(new moodle_url('/admin/tool/sebprep/index.php'),
        get_string('btn_back', 'tool_sebprep'), ['class' => 'btn btn-secondary']);

    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// ── STANDARD: Eingabeformular ────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_sebprep'));

// Gemeinsame Felder (Kurs-URLs, Semester-Tag)
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/admin/tool/sebprep/index.php'),
    'id'     => 'sebprep-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'preview', 'id' => 'form-action']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'step',    'value' => '1', 'id' => 'form-step']);

// Plugin-Erklärung
echo html_writer::tag('div',
    get_string('plugin_intro', 'tool_sebprep'),
    ['class' => 'alert alert-secondary mb-4']);

// Kurs-URLs
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label',
    get_string('field_courseurls', 'tool_sebprep'),
    ['for' => 'courseurls', 'class' => 'col-md-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-md-9');
echo html_writer::tag('textarea', htmlspecialchars($rawurls), [
    'id'          => 'courseurls',
    'name'        => 'courseurls',
    'class'       => 'form-control',
    'rows'        => 8,
    'placeholder' => "https://moodle.fhgr.ch/course/view.php?id=1234\nhttps://moodle.fhgr.ch/course/view.php?id=5678",
]);
echo html_writer::tag('small', get_string('field_courseurls_help', 'tool_sebprep'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// Semester-Tag
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label',
    get_string('field_semestertag', 'tool_sebprep'),
    ['for' => 'semestertag', 'class' => 'col-md-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', [
    'type'      => 'text',
    'id'        => 'semestertag',
    'name'      => 'semestertag',
    'class'     => 'form-control',
    'value'     => htmlspecialchars($semestertag),
    'maxlength' => 50,
]);
echo html_writer::tag('small', get_string('field_semestertag_help', 'tool_sebprep'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// Sprache
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label',
    get_string('field_language', 'tool_sebprep'),
    ['class' => 'col-md-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('d-flex gap-4 mt-2');
echo html_writer::tag('div',
    html_writer::empty_tag('input', [
        'type'    => 'radio', 'name' => 'language', 'id' => 'lang-de',
        'value'   => 'de', 'class' => 'mr-1',
        ($language === 'de' ? 'checked' : '') => '',
    ]) . html_writer::tag('label', '🇩🇪 Deutsch (Abschnitt 1)', ['for' => 'lang-de']),
    ['class' => 'form-check form-check-inline mr-4']
);
echo html_writer::tag('div',
    html_writer::empty_tag('input', [
        'type'    => 'radio', 'name' => 'language', 'id' => 'lang-en',
        'value'   => 'en', 'class' => 'mr-1',
        ($language === 'en' ? 'checked' : '') => '',
    ]) . html_writer::tag('label', '🇬🇧 English (Section 2)', ['for' => 'lang-en']),
    ['class' => 'form-check form-check-inline']
);
echo html_writer::end_div();
echo html_writer::tag('small', get_string('field_language_help', 'tool_sebprep'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// Frist Probeprüfung
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label',
    get_string('field_frist_probe', 'tool_sebprep'),
    ['for' => 'frist_probe', 'class' => 'col-md-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'id'          => 'frist_probe',
    'name'        => 'frist_probe',
    'class'       => 'form-control',
    'value'       => htmlspecialchars($fristprobe),
    'placeholder' => 'z.B. 22.05.2026',
]);
echo html_writer::tag('small', get_string('field_frist_probe_help', 'tool_sebprep'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// Frist Ersatzgeräte
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label',
    get_string('field_frist_ersatz', 'tool_sebprep'),
    ['for' => 'frist_ersatz', 'class' => 'col-md-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'id'          => 'frist_ersatz',
    'name'        => 'frist_ersatz',
    'class'       => 'form-control',
    'value'       => htmlspecialchars($fristersatz),
    'placeholder' => 'z.B. 29.05.2026',
]);
echo html_writer::tag('small', get_string('field_frist_ersatz_help', 'tool_sebprep'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// ── Schritt 1 ────────────────────────────────────────────────────────────────
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', '① ' . get_string('step1_title', 'tool_sebprep'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');

echo html_writer::tag('p', get_string('step1_desc', 'tool_sebprep'));

// Templatekurs-ID
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label',
    get_string('field_templatecourseid', 'tool_sebprep'),
    ['for' => 'templatecourseid', 'class' => 'col-md-4 col-form-label font-weight-bold']);
echo html_writer::start_div('col-md-4');
echo html_writer::empty_tag('input', [
    'type'        => 'number',
    'id'          => 'templatecourseid',
    'name'        => 'templatecourseid',
    'class'       => 'form-control',
    'value'       => $templatecourseid ?: '',
    'min'         => 1,
    'placeholder' => 'z.B. 23358',
]);
echo html_writer::tag('small', get_string('field_templatecourseid_help', 'tool_sebprep'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('button', '① ' . get_string('step1_title', 'tool_sebprep'),
    ['type'    => 'submit',
     'class'   => 'btn btn-primary',
     'onclick' => "document.getElementById('form-step').value='1'; return true;"]);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// ── Schritt 2 ────────────────────────────────────────────────────────────────
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', '② ' . get_string('step2_title', 'tool_sebprep'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');

echo html_writer::tag('p', get_string('step2_desc', 'tool_sebprep'));
$idnumberhint = ($language === 'en')
    ? 'SEB-PREP-EN-' . trim($semestertag)
    : 'SEB-PREP-' . trim($semestertag);
echo html_writer::tag('div',
    '🔍 ' . get_string('step2_idnumber_hint', 'tool_sebprep') . ' <code id="step2-idnumber">' . htmlspecialchars($idnumberhint) . '</code>',
    ['class' => 'alert alert-info']);

echo html_writer::tag('script', "
function updateIdnumber() {
    var tag = document.getElementById('semestertag').value.trim();
    var isEn = document.getElementById('lang-en') && document.getElementById('lang-en').checked;
    var prefix = isEn ? 'SEB-PREP-EN-' : 'SEB-PREP-';
    var el = document.getElementById('step2-idnumber');
    if (el) el.textContent = prefix + tag;
}
document.getElementById('semestertag').addEventListener('input', updateIdnumber);
document.querySelectorAll('input[name=\"language\"]').forEach(function(r) {
    r.addEventListener('change', updateIdnumber);
});
");

echo html_writer::tag('button', '② ' . get_string('step2_title', 'tool_sebprep'),
    ['type'    => 'submit',
     'class'   => 'btn btn-success',
     'onclick' => "document.getElementById('form-step').value='2'; return true;"]);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Felder leeren
echo html_writer::tag('p',
    html_writer::link(
        new moodle_url('/admin/tool/sebprep/index.php', ['clearsession' => 1]),
        '🗑 ' . get_string('btn_clear', 'tool_sebprep'),
        ['class' => 'btn btn-outline-secondary btn-sm',
         'onclick' => 'return confirm("' . get_string('confirm_clear', 'tool_sebprep') . '")']
    )
);

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
