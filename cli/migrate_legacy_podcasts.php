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
 * This script migrates legacy podcasts (pocast activity 1.9x) to the
 * new pcast module for 2.x.
 *
 */

define('CLI_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php'); // cli only functions

// now get cli options
list($options, $unrecognized) = cli_get_params(
    array(
        'help'=>false
    ),
    array(
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// General info
$info =
"Legacy Podcast migration tool

This is a interactive script will look for old 1.9x legacy podcast tables,
files and attempt to migrate them to the new 2.x pcast activity module.
remove legacy podcast

";
$optionhelp = "
Options:
-h, --help            Print out this help

Example:
\$sudo -u apache /usr/bin/php migrate_legacy_podcasts.php
";

// make sure PHP errors are displayed - helps with diagnosing of problems
@error_reporting(E_ALL);
@ini_set('display_errors', '1');


if ($options['help']) {
    echo $info;
    echo $optionhelp;
    exit(0);
}

// Show info and prompt user for script actions
echo $info;

$prompt = "Do you want to migrate legacy podcasts?
";

$input = cli_input($prompt.get_string('cliyesnoprompt', 'admin'), '', array(get_string('clianswerno', 'admin'), get_string('cliansweryes', 'admin')));
if ($input == get_string('cliansweryes', 'admin')) {
    pcast_migrate_legacy_podcasts();
}

$prompt = "Do you want to remove legacy podcast modules associated data?
***Warning*** Make sure you have backups!!!
";
$input = cli_input($prompt.get_string('cliyesnoprompt', 'admin'), '', array(get_string('clianswerno', 'admin'), get_string('cliansweryes', 'admin')));
if ($input == get_string('cliansweryes', 'admin')) {
    pcast_remove_legacy_podcasts();
}

/// Migration
function pcast_migrate_legacy_podcasts() {
    global $CFG, $DB;
    // See if we have legacy podcast module
    echo 'Checking for legacy podcast module...';
    $legacymodule = $DB->get_record('modules', array('name'=>'podcast'));
    echo ($legacymodule) ? "OK!\n" : "NOT OK!\n";
    // See if we have new podcast module
    echo 'Checking for new pcast module... ';
    $newmodule = $DB->get_record('modules', array('name'=>'pcast'));
    echo ($newmodule) ? "OK!\n" : "NOT OK!\n";
    if ($legacymodule and $newmodule) {
        $legacypodcasts = $DB->get_records('podcast', array(), 'course');
        if ($legacypodcasts) {
            echo count($legacypodcasts)." podcast record(s) found\n";
            // Get all required info for migration process
            foreach ($legacypodcasts as &$legacypodcast) {
                $cm = get_coursemodule_from_instance('podcast', $legacypodcast->id, $legacypodcast->course);
                $legacypodcast->cm = $cm;
                $legacypodcast->structure = array();
                $podcaststructure = $DB->get_records('podcast_structure', array('id_podcast'=>$legacypodcast->id));
                foreach ($podcaststructure as $item) {
                    $item->filename = $item->lien;
                    $item->filepath = $CFG->dataroot.'/'.$legacypodcast->course.
                                      '/moddata/podcast/'.$legacypodcast->id.
                                      '/'.$item->userid.'/'.$item->lien;
                    $legacypodcast->structure[] = $item;
                }
            }

            /// Load required libraries
            require_once($CFG->dirroot.'/mod/pcast/lib.php');
            require_once($CFG->libdir.'/filelib.php');
            require_once($CFG->dirroot.'/course/lib.php');

            $fs = get_file_storage();
            $admin = get_admin();

            while ($legacypodcasts) {
                $legacypodcast = array_shift($legacypodcasts);
                try {
                    echo 'Working on podcast - '.$legacypodcast->name."\n";
                    // Check course exists
                    if (!$DB->record_exists('course', array('id'=>$legacypodcast->course))) {
                        echo " course doesn't exist, skipping! \n";
                        continue;
                    }
                    $pcast = new stdClass();
                    $pcast->course = $legacypodcast->course;
                    $pcast->userid = ($legacypodcast->userid) ? $legacypodcast->userid : $admin->id;
                    $pcast->name = $legacypodcast->name;
                    $pcast->intro = $legacypodcast->intro;
                    $pcast->introformat = FORMAT_HTML;
                    $pcast->keywords = $legacypodcast->category;
                    $pcast->timemodified = $legacypodcast->timemodified;
                    $pcast->timecreated = $legacypodcast->timemodified;
                    // Create pcast activity based on podcast data
                    $pcast->id = $DB->insert_record('pcast', $pcast);
                    // Setup course module and load into section
                    $mod = new stdClass();
                    // Newly created values
                    $mod->course = $pcast->course;
                    $mod->module = $newmodule->id;
                    $mod->instance = $pcast->id;
                    // Settings from legacy course_module
                    $mod->indent = $legacypodcast->cm->indent;
                    $mod->visible = 0;     // Hide
                    $mod->visibleold = 0;  // Hide
                    $section = $DB->get_field("course_sections", "section", array("id"=>$legacypodcast->cm->section, "course"=>$legacypodcast->cm->course));
                    $mod->section = ($section) ? $section : 0;
                    $mod->groupmode = $legacypodcast->cm->groupmode;
                    $mod->groupingid = $legacypodcast->cm->groupingid;
                    $mod->groupmembersonly = $legacypodcast->cm->groupmembersonly;
                    $mod->id = add_course_module($mod);
                    $mod->coursemodule = $mod->id;
                    $sectionid = add_mod_to_section($mod, $legacypodcast->cm);
                    $DB->set_field("course_modules", "section", $sectionid, array("id"=>$mod->coursemodule));
                    $context = get_context_instance(CONTEXT_MODULE, $mod->coursemodule); // newly created course module context for FS
                    foreach ($legacypodcast->structure as $item) {
                        $episode = new stdClass();
                        $episode->pcastid = $pcast->id;
                        $episode->course = $pcast->course;
                        $episode->userid = ($item->userid) ? $item->userid : $admin->id;
                        $episode->name = stripslashes($item->title);
                        $episode->summary = stripslashes($item->intro);
                        $episode->approved = 1;
                        $episode->mediafile = file_exists($item->filepath) ? 1 : 0;
                        $episode->timemodified = strtotime($item->date_html); // used date_html convert to epoch
                        $episode->timecreated = strtotime($item->date_html); // used date_html convert to epoch
                        $episode->id = $DB->insert_record('pcast_episodes', $episode);
                        if ($episode->mediafile) {
                            $fr = new stdClass();
                            $fr->contextid = $context->id;
                            $fr->component = 'mod_pcast';
                            $fr->filearea = 'episode';
                            $fr->itemid = $episode->id;
                            $fr->filepath = '/';
                            $fr->filename = $item->filename;
                            $fr->userid  = ($item->userid) ? $item->userid : $admin->id;
                            $fr->timecreated = filectime($item->filepath);
                            $fr->timemodified = filemtime($item->filepath);
                            $fs->create_file_from_pathname($fr, $item->filepath);
                            echo " moved mediafile - {$item->filename} to filepool\n";
                        }
                    }
                } catch (moodle_exception $e) {
                    debugging('Error: '.$e->getMessage(), DEBUG_ALL);
                }
            }
            echo "finished migration process\n";
        } else {
            echo "no podcast record(s) found\n";
        }
    } else {
        echo "Missing table(s), can't migrate podcasts.\n";
    }
    //echo "\n";
    return true;
}/// EOFunction.

/// Remove old legacy shiz
function pcast_remove_legacy_podcasts() {
    global $CFG, $DB;
    // See if we have legacy podcast module
    echo 'Checking for legacy podcast module...';
    $legacymodule = $DB->get_record('modules', array('name'=>'podcast'));
    echo ($legacymodule) ? "OK!\n" : "NOT OK!\n";

    if ($legacymodule) {
        // Setup name vars
        $component = 'mod_' . $legacymodule->name;
        $pluginname = $legacymodule->name;
        if (get_string_manager()->string_exists('modulename', $component)) {
            $strpluginname = get_string('modulename', $component);
        } else {
            $strpluginname = $component;
        }

        // delete all the relevant instances from all course sections
        if ($coursemods = $DB->get_records('course_modules', array('module' => $legacymodule->id))) {
            foreach ($coursemods as $coursemod) {
                if (!delete_mod_from_section($coursemod->id, $coursemod->section)) {
                    echo $OUTPUT->notification("Could not delete the $strpluginname with id = $coursemod->id from section $coursemod->section");
                }
            }
        }
        // clear course.modinfo for courses that used this module
        $sql = "UPDATE {course}
                   SET modinfo=''
                 WHERE id IN (SELECT DISTINCT course
                                FROM {course_modules}
                               WHERE module=?)";
        $DB->execute($sql, array($legacymodule->id));

        // delete all the course module records
        $DB->delete_records('course_modules', array('module' => $legacymodule->id));

        // delete module contexts
        if ($coursemods) {
            foreach ($coursemods as $coursemod) {
                if (!delete_context(CONTEXT_MODULE, $coursemod->id)) {
                    echo $OUTPUT->notification("Could not delete the context for $strpluginname with id = $coursemod->id");
                }
            }
        }

        // cleanup the gradebook
        require_once($CFG->libdir.'/gradelib.php');
        grade_uninstalled_module($legacymodule->name);

        // delete calendar events
        $DB->delete_records('event', array('modulename' => $pluginname));

        // delete all the logs
        $DB->delete_records('log', array('module' => $pluginname));

        // delete log_display information
        $DB->delete_records('log_display', array('component' => $component));

        // delete the module configuration records
        unset_all_config_for_plugin($pluginname);

        // remove legacy course area files
        $sql = "SELECT DISTINCT p.course FROM {podcast} p";
        $coursedirs = $DB->get_records_sql($sql);
        //print_object($coursedirs);exit;
        foreach ($coursedirs as $coursedir) {
            $dir = $CFG->dataroot.'/'.$coursedir->course.'/moddata/podcast';
            remove_dir($dir);
        }
        echo "legacy directories removed\n";
        
        // delete the plugin tables
        $tables = array('podcast', 'podcast_structure');
        /// Iterate over, fixing id fields as necessary
        foreach ($tables as $table) {
            // found orphan table --> delete it
            if ($DB->get_manager()->table_exists($table)) {
                $xmldb_table = new xmldb_table($table);
                $DB->get_manager()->drop_table($xmldb_table);
                echo $table." table dropped\n";
            }
        }

        // delete the module entry itself
        $DB->delete_records('modules', array('name' => $legacymodule->name));

        // delete the capabilities that were defined by this module
        capabilities_cleanup($component);

        // remove event handlers and dequeue pending events
        events_uninstall($component);

        echo "finished migration process\n";
    }

    return true;
}/// EOFunction.
exit(0);
