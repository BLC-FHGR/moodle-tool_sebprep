<?php
/**
 * SEB-Vorbereitung in Modulkursen einrichten – Kernlogik
 */
defined('MOODLE_INTERNAL') || die();

// ── URL-Parsing & Validierung ────────────────────────────────────────────────

function tool_sebprep_parse_urls(string $rawtext): array {
    $lines = preg_split('/[\r\n]+/', trim($rawtext));
    $result = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $courseid = null;
        if (preg_match('/[?&]id=(\d+)/', $line, $m)) {
            $courseid = (int)$m[1];
        }
        $result[] = ['url' => $line, 'courseid' => $courseid];
    }
    return $result;
}

function tool_sebprep_validate_courses(array $entries): array {
    global $DB;
    foreach ($entries as &$entry) {
        if ($entry['courseid']) {
            $course = $DB->get_record('course', ['id' => $entry['courseid']],
                'id, shortname, fullname', IGNORE_MISSING);
            $entry['course'] = $course ?: null;
            $entry['valid']  = (bool)$course;
        } else {
            $entry['course'] = null;
            $entry['valid']  = false;
        }
    }
    return $entries;
}

// ── Templatekurs lesen ───────────────────────────────────────────────────────

function tool_sebprep_read_template(int $templatecourseid, int $sectionnumber = 1): array {
    global $DB;

    $section = $DB->get_record('course_sections',
        ['course' => $templatecourseid, 'section' => $sectionnumber],
        'id, name, summary, summaryformat, sequence', MUST_EXIST);

    $modules = [];
    if (!empty(trim($section->sequence))) {
        $cmids = explode(',', trim($section->sequence));
        foreach ($cmids as $cmid) {
            $cmid = (int)trim($cmid);
            if (!$cmid) continue;

            $cm = $DB->get_record('course_modules', ['id' => $cmid],
                'id, module, instance, visible, visibleold, visibleoncoursepage,
                 completion, completionview, completionexpected, completionpassgrade,
                 indent, groupmode, showdescription, downloadcontent', IGNORE_MISSING);
            if (!$cm) continue;

            $modname = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
            $modules[] = [
                'modname'             => $modname,
                'cmid'                => $cmid,
                'instance'            => $cm->instance,
                'visible'             => $cm->visible,
                'visibleold'          => $cm->visibleold,
                'visibleoncoursepage' => $cm->visibleoncoursepage,
                'completion'          => $cm->completion,
                'completionview'      => $cm->completionview,
                'completionexpected'  => $cm->completionexpected,
                'completionpassgrade' => $cm->completionpassgrade,
                'indent'              => $cm->indent,
                'groupmode'           => $cm->groupmode,
                'showdescription'     => $cm->showdescription,
                'downloadcontent'     => $cm->downloadcontent ?? 1,
            ];
        }
    }

    return [
        'section_name'    => $section->name,
        'section_summary' => $section->summary,
        'summaryformat'   => $section->summaryformat,
        'modules'         => $modules,
    ];
}

// ── Bestehenden Subcourse prüfen ─────────────────────────────────────────────

function tool_sebprep_get_refcourse_from_template(int $templatecourseid): ?int {
    global $DB;
    $sql = "SELECT s.refcourse
              FROM {subcourse} s
              JOIN {course_modules} cm ON cm.instance = s.id
              JOIN {modules} mo ON mo.id = cm.module AND mo.name = 'subcourse'
              JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course = :courseid AND cs.section = 1
             LIMIT 1";
    $refcourse = $DB->get_field_sql($sql, ['courseid' => $templatecourseid]);
    return $refcourse ? (int)$refcourse : null;
}

function tool_sebprep_find_existing_subcourse(int $courseid, int $refcourseid): array {
    global $DB;
    $sql = "SELECT cm.id as cmid
              FROM {subcourse} s
              JOIN {course_modules} cm ON cm.instance = s.id
              JOIN {modules} mo ON mo.id = cm.module AND mo.name = 'subcourse'
             WHERE cm.course = :courseid AND s.refcourse = :refcourse";
    $records = $DB->get_records_sql($sql, [
        'courseid'  => $courseid,
        'refcourse' => $refcourseid,
    ]);
    return array_keys($records);
}

// ── SCHRITT 1: Kursabschnitt einfügen ────────────────────────────────────────

function tool_sebprep_process_step1(
    int $courseid,
    int $templatecourseid,
    string $semestertag,
    string $fristprobe = '',
    string $fristersatz = '',
    string $language = 'de'
): array {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');

    $warning = null;
    // DE = Abschnitt 1, EN = Abschnitt 2
    $sectionnumber = ($language === 'en') ? 2 : 1;

    // Datum automatisch formatieren für EN
    $fristprobeformatted  = tool_sebprep_format_date($fristprobe,  $language);
    $fristersatzformatted = tool_sebprep_format_date($fristersatz, $language);

    // 1. Abschlussverfolgung einschalten
    $DB->set_field('course', 'enablecompletion', 1, ['id' => $courseid]);

    // 2. Templatekurs lesen (DE: Abschnitt 1, EN: Abschnitt 2)
    $template = tool_sebprep_read_template($templatecourseid, $sectionnumber);

    // 3. refcourse-ID aus Templatekurs lesen (für Prüfung 2)
    $refcourseid = tool_sebprep_get_refcourse_from_template($templatecourseid);

    // 4. Abschnittstitel: Template-Name + Semester-Tag (nur wenn noch nicht vorhanden)
    $basetitle = trim($template['section_name']);
    $sectionname = (str_ends_with($basetitle, $semestertag))
        ? $basetitle
        : $basetitle . ' ' . $semestertag;

    // idnumber für diesen Eintrag
    $idnumber = ($language === 'en')
        ? 'SEB-PREP-EN-' . trim($semestertag)
        : 'SEB-PREP-' . trim($semestertag);

    // Prüfung 1: Abschnitt mit Semester-Tag im Titel bereits vorhanden?
    $existingsection = $DB->get_record_select(
        'course_sections',
        'course = ? AND ' . $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text('?'),
        [$courseid, $sectionname],
        'id',
        IGNORE_MISSING
    );
    if ($existingsection) {
        return [
            'success' => false,
            'message' => get_string('warn_section_exists', 'tool_sebprep', $sectionname),
            'warning' => null,
        ];
    }

    // Prüfung 2: Subcourse mit refcourse-ID im ganzen Kurs prüfen
    // → Name enthält Semester-Tag: überspringen (bereits korrekt eingefügt)
    // → Name enthält Semester-Tag nicht: einfügen + Warnung (alter Semester-Link vorhanden)
    if ($refcourseid) {
        $sql = "SELECT cm.id, s.name
                  FROM {subcourse} s
                  JOIN {course_modules} cm ON cm.instance = s.id
                  JOIN {modules} mo ON mo.id = cm.module AND mo.name = 'subcourse'
                 WHERE cm.course = :courseid AND s.refcourse = :refcourse";
        $existingsubcourses = $DB->get_records_sql($sql, [
            'courseid'  => $courseid,
            'refcourse' => $refcourseid,
        ]);
        foreach ($existingsubcourses as $sub) {
            if (str_contains($sub->name, $semestertag)) {
                // Bereits korrekt eingefügt → überspringen
                return [
                    'success' => false,
                    'message' => get_string('warn_subcourse_exists', 'tool_sebprep', $semestertag),
                    'warning' => null,
                ];
            }
        }
        // Alte Semester-Links vorhanden aber ohne dieses Tag → Warnung aber trotzdem einfügen
        if (!empty($existingsubcourses)) {
            $warning = get_string('warn_existing_subcourse', 'tool_sebprep', $refcourseid);
        }
    }

    // 5. Neuen Abschnitt auf Position 1 einfügen
    $newsectionid = tool_sebprep_insert_section(
        $courseid,
        $sectionname,
        $template['section_summary'],
        $template['summaryformat']
    );

    // 6. Alle Aktivitäten kopieren; Subcourse bekommt idnumber + Datumsersetzung
    foreach ($template['modules'] as $mod) {
        tool_sebprep_copy_module($courseid, $newsectionid, $templatecourseid, $mod, $idnumber, $fristprobeformatted, $fristersatzformatted);
    }

    rebuild_course_cache($courseid, true);

    return ['success' => true, 'message' => 'OK', 'warning' => $warning];
}

/**
 * Formatiert ein Datum für DE oder EN.
 * Eingabe: DD.MM.YYYY → DE: DD.MM.YYYY, EN: Month DD, YYYY
 */
function tool_sebprep_format_date(string $date, string $language): string {
    if (!$date || $language !== 'en') return $date;
    // Parsen: DD.MM.YYYY
    $parts = explode('.', $date);
    if (count($parts) !== 3) return $date;
    [$day, $month, $year] = $parts;
    $months = [
        '01' => 'January', '02' => 'February', '03' => 'March',
        '04' => 'April',   '05' => 'May',       '06' => 'June',
        '07' => 'July',    '08' => 'August',    '09' => 'September',
        '10' => 'October', '11' => 'November',  '12' => 'December',
    ];
    $monthname = $months[$month] ?? $month;
    return $monthname . ' ' . ltrim($day, '0') . ', ' . $year;
}

// ── SCHRITT 2: Fortschrittsbalken hinzufügen ─────────────────────────────────

function tool_sebprep_process_step2(
    int $courseid,
    string $semestertag,
    string $language = 'de'
): array {
    global $DB;

    $idnumber = ($language === 'en')
        ? 'SEB-PREP-EN-' . trim($semestertag)
        : 'SEB-PREP-' . trim($semestertag);

    // Subcourse mit dieser idnumber im Kurs finden
    $cm = $DB->get_record('course_modules', [
        'course'   => $courseid,
        'idnumber' => $idnumber,
    ], 'id, module, instance', IGNORE_MISSING);

    if (!$cm) {
        return [
            'success' => false,
            'message' => get_string('error_subcourse_not_found', 'tool_sebprep', $idnumber),
            'warning' => null,
        ];
    }

    // Block-Konfiguration zusammenbauen
    // Blocktitel sprachabhängig
    $blocktitle = ($language === 'en')
        ? 'SEB Preparation ' . trim($semestertag)
        : 'SEB Vorbereitung ' . trim($semestertag);
    $config = new stdClass();
    $config->orderby            = 'orderbytime';
    $config->longbars           = 'squeeze';
    $config->progressBarIcons   = '0';
    $config->showpercentage     = '0';
    $config->progressTitle      = $blocktitle;
    $config->activitiesincluded = 'selectedactivities';
    $config->selectactivities   = ['subcourse-' . $cm->instance];
    $configdata = base64_encode(serialize($config));

    $coursecontext = context_course::instance($courseid);

    // Prüfen ob bereits ein Block mit diesem Titel existiert (gleicher Semester-Tag)
    // Andere completion_progress Blöcke (z.B. für andere Aktivitäten) werden nie angetastet
    $existingblocks = $DB->get_records('block_instances', [
        'blockname'       => 'completion_progress',
        'parentcontextid' => $coursecontext->id,
    ], '', 'id, configdata');

    foreach ($existingblocks as $block) {
        $existingconfig = @unserialize(base64_decode($block->configdata));
        if ($existingconfig &&
            isset($existingconfig->progressTitle) &&
            $existingconfig->progressTitle === $blocktitle) {
            return [
                'success' => false,
                'message' => get_string('warn_block_exists', 'tool_sebprep', $blocktitle),
                'warning' => null,
            ];
        }
    }

    // Neuen Block erstellen – bestehende Blöcke werden nie verändert
    $blockinstance = new stdClass();
    $blockinstance->blockname         = 'completion_progress';
    $blockinstance->parentcontextid   = $coursecontext->id;
    $blockinstance->showinsubcontexts = 0;
    $blockinstance->pagetypepattern   = 'course-view-*';
    $blockinstance->subpagepattern    = null;
    $blockinstance->defaultregion     = 'side-pre';
    $blockinstance->defaultweight     = 1;
    $blockinstance->configdata        = $configdata;
    $blockinstance->timecreated       = time();
    $blockinstance->timemodified      = time();
    $blockid = $DB->insert_record('block_instances', $blockinstance);

    // Block-Position setzen
    $position = new stdClass();
    $position->blockinstanceid = $blockid;
    $position->contextid       = $coursecontext->id;
    $position->pagetype        = 'course-view-topics';
    $position->subpage         = '';
    $position->visible         = 1;
    $position->region          = 'side-pre';
    $position->weight          = -1;
    $DB->insert_record('block_positions', $position);

    return ['success' => true, 'message' => 'OK', 'warning' => null];
}

// ── Abschnitt einfügen ───────────────────────────────────────────────────────

function tool_sebprep_insert_section(
    int $courseid,
    string $name,
    string $summary,
    int $summaryformat
): int {
    global $DB;

    // Von höchster zu niedrigster Nummer verschieben (verhindert Duplicate Key)
    $sections = $DB->get_records_select(
        'course_sections',
        'course = ? AND section >= 1',
        [$courseid],
        'section DESC',
        'id, section'
    );
    foreach ($sections as $sec) {
        $DB->set_field('course_sections', 'section', $sec->section + 1, ['id' => $sec->id]);
    }

    $record = new stdClass();
    $record->course        = $courseid;
    $record->section       = 1;
    $record->name          = $name;
    $record->summary       = $summary;
    $record->summaryformat = $summaryformat;
    $record->sequence      = '';
    $record->visible       = 1;
    $record->timemodified  = time();
    return $DB->insert_record('course_sections', $record);
}

// ── Modul kopieren ───────────────────────────────────────────────────────────

function tool_sebprep_copy_module(
    int $targetcourseid,
    int $targetsectionid,
    int $templatecourseid,
    array $mod,
    string $idnumber = '',
    string $fristprobe = '',
    string $fristersatz = ''
): void {
    switch ($mod['modname']) {
        case 'resource':
            tool_sebprep_copy_resource($targetcourseid, $targetsectionid, $templatecourseid, $mod);
            break;
        case 'subcourse':
            tool_sebprep_copy_subcourse($targetcourseid, $targetsectionid, $mod, $idnumber, $fristprobe, $fristersatz);
            break;
        case 'assign':
            tool_sebprep_copy_assign($targetcourseid, $targetsectionid, $mod);
            break;
        default:
            debugging("tool_sebprep: Modultyp '{$mod['modname']}' wird übersprungen.", DEBUG_DEVELOPER);
    }
}

// ── Resource (PDF) kopieren ──────────────────────────────────────────────────

function tool_sebprep_copy_resource(
    int $targetcourseid,
    int $targetsectionid,
    int $templatecourseid,
    array $mod
): int {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/resource/lib.php');

    $srcresource = $DB->get_record('resource', ['id' => $mod['instance']], '*', MUST_EXIST);
    $moduleid    = $DB->get_field('modules', 'id', ['name' => 'resource'], MUST_EXIST);

    $cm   = tool_sebprep_build_cm($targetcourseid, $moduleid, $targetsectionid, $mod);
    $cmid = $DB->insert_record('course_modules', $cm);
    $modcontext = context_module::instance($cmid);

    $newresource = new stdClass();
    $newresource->course         = $targetcourseid;
    $newresource->name           = $srcresource->name;
    $newresource->intro          = $srcresource->intro;
    $newresource->introformat    = $srcresource->introformat;
    $newresource->display        = $srcresource->display;
    $newresource->displayoptions = $srcresource->displayoptions;
    $newresource->filterfiles    = $srcresource->filterfiles;
    $newresource->revision       = 1;
    $newresource->timemodified   = time();
    $newresourceid = $DB->insert_record('resource', $newresource);
    $DB->set_field('course_modules', 'instance', $newresourceid, ['id' => $cmid]);

    // Dateien kopieren
    $srccontext = context_module::instance($mod['cmid']);
    $fs = get_file_storage();
    $files = $fs->get_area_files($srccontext->id, 'mod_resource', 'content', 0, 'sortorder', false);
    foreach ($files as $file) {
        $newfileinfo = [
            'contextid' => $modcontext->id,
            'component' => 'mod_resource',
            'filearea'  => 'content',
            'itemid'    => 0,
            'filepath'  => $file->get_filepath(),
            'filename'  => $file->get_filename(),
        ];
        if (!$fs->file_exists($newfileinfo['contextid'], $newfileinfo['component'],
                $newfileinfo['filearea'], $newfileinfo['itemid'],
                $newfileinfo['filepath'], $newfileinfo['filename'])) {
            $fs->create_file_from_storedfile($newfileinfo, $file);
        }
    }

    tool_sebprep_append_to_section_sequence($targetsectionid, $cmid);
    return $cmid;
}

// ── Subcourse kopieren ───────────────────────────────────────────────────────

function tool_sebprep_copy_subcourse(
    int $targetcourseid,
    int $targetsectionid,
    array $mod,
    string $idnumber = '',
    string $fristprobe = '',
    string $fristersatz = ''
): int {
    global $DB;

    $srcsubcourse = $DB->get_record('subcourse', ['id' => $mod['instance']], '*', MUST_EXIST);
    $moduleid     = $DB->get_field('modules', 'id', ['name' => 'subcourse'], MUST_EXIST);

    $cm = tool_sebprep_build_cm($targetcourseid, $moduleid, $targetsectionid, $mod);
    $cm->idnumber = $idnumber;
    $cmid = $DB->insert_record('course_modules', $cm);

    // Intro: Platzhalter ersetzen falls Daten angegeben
    $intro = $srcsubcourse->intro;
    if ($fristprobe) {
        $intro = str_replace('{{FRIST_PROBE}}', htmlspecialchars($fristprobe), $intro);
    }
    if ($fristersatz) {
        $intro = str_replace('{{FRIST_ERSATZ}}', htmlspecialchars($fristersatz), $intro);
    }

    // Semester-Tag aus idnumber extrahieren: SEB-PREP-FS26 → FS26, SEB-PREP-EN-Spring 26 → Spring 26
    if (str_starts_with($idnumber, 'SEB-PREP-EN-')) {
        $semestertag = substr($idnumber, strlen('SEB-PREP-EN-'));
    } else {
        $semestertag = substr($idnumber, strlen('SEB-PREP-'));
    }
    $basename = trim($srcsubcourse->name);
    $newsubcourse = new stdClass();
    $newsubcourse->course                  = $targetcourseid;
    $newsubcourse->name                    = (str_ends_with($basename, $semestertag))
        ? $basename
        : $basename . ($semestertag ? ' ' . $semestertag : '');
    $newsubcourse->intro                   = $intro;
    $newsubcourse->introformat             = $srcsubcourse->introformat;
    $newsubcourse->timecreated             = time();
    $newsubcourse->timemodified            = time();
    $newsubcourse->refcourse               = $srcsubcourse->refcourse;
    $newsubcourse->instantredirect         = $srcsubcourse->instantredirect;
    $newsubcourse->completioncourse        = $srcsubcourse->completioncourse;
    $newsubcourse->blankwindow             = $srcsubcourse->blankwindow;
    $newsubcourse->fetchpercentage         = $srcsubcourse->fetchpercentage;
    $newsubcourse->coursepageprintgrade    = $srcsubcourse->coursepageprintgrade;
    $newsubcourse->coursepageprintprogress = $srcsubcourse->coursepageprintprogress;
    $newsubcourseid = $DB->insert_record('subcourse', $newsubcourse);
    $DB->set_field('course_modules', 'instance', $newsubcourseid, ['id' => $cmid]);

    tool_sebprep_append_to_section_sequence($targetsectionid, $cmid);
    return $cmid;
}

// ── Aufgabe (assign) kopieren ────────────────────────────────────────────────

function tool_sebprep_copy_assign(
    int $targetcourseid,
    int $targetsectionid,
    array $mod
): int {
    global $DB;

    $srcassign = $DB->get_record('assign', ['id' => $mod['instance']], '*', MUST_EXIST);
    $moduleid  = $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);

    $cm   = tool_sebprep_build_cm($targetcourseid, $moduleid, $targetsectionid, $mod);
    $cmid = $DB->insert_record('course_modules', $cm);

    // Nur existierende Spalten kopieren
    $assigncols = array_keys($DB->get_columns('assign'));
    $newassign  = new stdClass();
    foreach ($assigncols as $col) {
        if ($col === 'id') continue;
        if (isset($srcassign->$col)) {
            $newassign->$col = $srcassign->$col;
        }
    }
    $newassign->course       = $targetcourseid;
    $newassign->timemodified = time();
    $assignid = $DB->insert_record('assign', $newassign);
    $DB->set_field('course_modules', 'instance', $assignid, ['id' => $cmid]);

    $pluginconfigs = $DB->get_records('assign_plugin_config', ['assignment' => $mod['instance']]);
    foreach ($pluginconfigs as $cfg) {
        $newcfg = new stdClass();
        $newcfg->assignment = $assignid;
        $newcfg->plugin     = $cfg->plugin;
        $newcfg->subtype    = $cfg->subtype;
        $newcfg->name       = $cfg->name;
        $newcfg->value      = $cfg->value;
        $DB->insert_record('assign_plugin_config', $newcfg);
    }

    tool_sebprep_append_to_section_sequence($targetsectionid, $cmid);
    return $cmid;
}

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────

function tool_sebprep_build_cm(
    int $courseid,
    int $moduleid,
    int $sectionid,
    array $mod
): stdClass {
    global $DB;

    $cm = new stdClass();
    $cm->course               = $courseid;
    $cm->module               = $moduleid;
    $cm->section              = $sectionid;
    $cm->added                = time();
    $cm->visible              = $mod['visible'];
    $cm->visibleold           = $mod['visibleold'];
    $cm->visibleoncoursepage  = $mod['visibleoncoursepage'];
    $cm->completion           = $mod['completion'];
    $cm->completionview       = $mod['completionview'];
    $cm->completionexpected   = $mod['completionexpected'];
    $cm->completionpassgrade  = $mod['completionpassgrade'];
    $cm->indent               = $mod['indent'];
    $cm->groupmode            = $mod['groupmode'];
    $cm->showdescription      = $mod['showdescription'];
    $cm->downloadcontent      = $mod['downloadcontent'];
    $cm->groupingid           = 0;
    $cm->score                = 0;
    $cm->instance             = 0;
    $cm->idnumber             = '';
    $cm->availability         = null;

    // Moodle 5.x: nur existierende Spalten setzen
    $columns = $DB->get_columns('course_modules');
    if (isset($columns['showavailability']))   $cm->showavailability   = 0;
    if (isset($columns['deletioninprogress'])) $cm->deletioninprogress = 0;
    if (isset($columns['purgecaches']))        $cm->purgecaches        = 0;

    return $cm;
}

function tool_sebprep_append_to_section_sequence(int $sectionid, int $cmid): void {
    global $DB;
    $section = $DB->get_record('course_sections', ['id' => $sectionid], 'id, sequence', MUST_EXIST);
    $seq = $section->sequence ? trim($section->sequence) . ',' . $cmid : (string)$cmid;
    $DB->set_field('course_sections', 'sequence', $seq, ['id' => $sectionid]);
}
