<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'tool_sebprep',
        get_string('pluginname', 'tool_sebprep'),
        new moodle_url('/admin/tool/sebprep/index.php'),
        'moodle/site:config'
    ));
}
