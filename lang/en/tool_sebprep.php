<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']               = 'SEB Preparation Setup for Module Courses';
$string['pluginname_desc']          = 'Inserts the SEB preparation section into module courses and sets up the progress bar block.';

$string['step1_title']              = 'Insert course section';
$string['step1_desc']               = 'Copies section 1 from the template course into all target courses. Enables completion tracking and sets a unique idnumber on the subcourse link.';
$string['step2_title']              = 'Add progress bar';
$string['step2_desc']               = 'Finds the subcourse link in each target course by its unique idnumber and adds the «SEB Vorbereitung» completion progress block to the right sidebar.';
$string['step2_idnumber_hint']      = 'Searching for subcourse with idnumber:';

$string['plugin_intro']             = 'This tool prepares lecturers\' module courses for SEB exams: it copies the SEB preparation section from a template course into all target courses (Step 1) and adds the corresponding progress bar (Step 2).';
$string['field_courseurls']         = 'Course URLs (target courses, one per line)';
$string['field_language']           = 'Sprache / Language';
$string['field_language_help']      = 'Deutsch: copies section 1 from template course. English: copies section 2.';
$string['field_frist_probe']        = 'Mock exam deadline';
$string['field_frist_probe_help']   = 'Replaces {{FRIST_PROBE}} in the subcourse text. e.g. 22.05.2026';
$string['field_frist_ersatz']       = 'Replacement device deadline';
$string['field_frist_ersatz_help']  = 'Replaces {{FRIST_ERSATZ}} in the subcourse text. e.g. 29.05.2026';
$string['field_courseurls_help']    = 'One Moodle course URL per line, e.g. https://moodle.fhgr.ch/course/view.php?id=1234';
$string['field_semestertag']        = 'Semester tag';
$string['field_semestertag_help']   = 'e.g. FS26 or HS26. Appended to the section title and used as part of the idnumber (SEB-PREP-FS26).';
$string['field_templatecourseid']   = 'Template course ID';
$string['field_templatecourseid_help'] = 'Section 1 of this course will be copied into all target courses.';

$string['btn_clear']                = 'Clear fields';
$string['confirm_clear']            = 'Reset all fields?';
$string['btn_execute']              = 'Execute now';
$string['btn_back']                 = '← Back';

$string['preview_title']            = 'Preview';
$string['col_courseid']             = 'Course ID';
$string['col_coursename']           = 'Course name';
$string['col_url']                  = 'URL';
$string['col_status']               = 'Status';
$string['col_warning']              = 'Note';

$string['status_ready']             = '✓ Ready';
$string['status_invalid']           = '✗ Invalid URL / course not found';
$string['status_done']              = '✅ Done';
$string['status_skipped']           = '⏭ Skipped';
$string['status_error']             = '❌ Error';

$string['warn_existing_subcourse']  = '⚠️ Existing subcourse link found (refcourse={$a}) – please check manually';
$string['warn_subcourse_exists']    = '⚠️ Subcourse link for {$a} already exists in course – skipped';
$string['warn_section_exists']      = '⚠️ Section «{$a}» already exists – skipped';
$string['warn_idnumber_exists']     = '⚠️ Subcourse link with ID «{$a}» already exists – please clear the ID number on the subcourse link manually and run again';
$string['warn_block_exists']        = '⚠️ Progress bar «{$a}» already exists – skipped';
$string['warn_block_updated']       = 'ℹ️ Existing progress bar was updated with the correct activity';
$string['error_subcourse_not_found'] = '✗ No subcourse found with idnumber «{$a}» – please run Step 1 first';

$string['result_title']             = 'Processing log';
$string['result_warnings_title']    = 'Courses with notes';
$string['result_warnings_desc']     = 'These courses have notes. Please check manually:';
$string['download_warnings']        = 'Download notes as CSV';
$string['no_warnings']              = '✅ No notes – all courses processed cleanly.';
$string['processed']                = 'Processed: {$a->done} of {$a->total} courses successfully.';
$string['confirm_execute']          = 'Start processing now? This action cannot be undone.';
