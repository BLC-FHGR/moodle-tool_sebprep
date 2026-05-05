<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * SEB Preparation Setup for Module Courses
 *
 * @package    tool_sebprep
 * @copyright  2026 FHGR Ramon Heeb
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_sebprep';       // Full name of the plugin (used for diagnostics).
$plugin->version   = 2026050501;            // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2026021600;            // Requires this Moodle version (5.0.6).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';              // User-friendly version number.
