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
 * The main pcast configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_pcast
 * @copyright 2010 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_pcast_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE, $CFG, $DB;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
    /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('pcastname', 'pcast'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

    /// Adding the standard "intro" and "introformat" fields
        $this->add_intro_editor();
//-------------------------------------------------------------------------------
/// RSS Settings
//-------------------------------------------------------------------------------
        if ($CFG->enablerssfeeds && isset($CFG->pcast_enablerssfeeds) && $CFG->pcast_enablerssfeeds) {

            $mform->addElement('header', 'rss', get_string('rss'));

        /// RSS enabled
            $mform->addElement('selectyesno', 'enablerssfeed', get_string('enablerssfeed', 'pcast'));
            $mform->addHelpButton('enablerssfeed', 'enablerssfeed', 'pcast');

        /// RSS Entries per feed
            $choices = array();
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssepisodes', get_string('rssepisodes','pcast'), $choices);
            $mform->addHelpButton('rssepisodes', 'rssepisodes', 'pcast');
            $mform->disabledIf('rssepisodes', 'enablerssfeed', 'eq', 0);
            $mform->setDefault('rssepisodes', 10);


        /// RSS Sort Order
            $sortorder = array();
            $sortorder[0] = get_string('createasc','pcast');
            $sortorder[1] = get_string('createdesc','pcast');
            $mform->addElement('select', 'rsssortorder', get_string('rsssortorder','pcast'), $sortorder);
            $mform->addHelpButton('rsssortorder', 'rsssortorder', 'pcast');
            $mform->setDefault('rsssortorder', 2);
            $mform->disabledIf('rsssortorder', 'enablerssfeed', 'eq', 0);

        }

//-------------------------------------------------------------------------------
        if ($CFG->enablerssfeeds && isset($CFG->pcast_enablerssitunes) && $CFG->pcast_enablerssitunes) {

            /// Itunes Tags
            $mform->addElement('header', 'itunes', get_string('itunes', 'pcast'));

            /// Enable Itunes Tags
            $mform->addElement('selectyesno', 'enablerssitunes', get_string('enablerssitunes', 'pcast'));
            $mform->addHelpButton('enablerssitunes', 'enablerssitunes', 'pcast');
            $mform->setDefault('enablerssitunes', 0);

            /// Subtitle
            $mform->addElement('text', 'subtitle', get_string('subtitle', 'pcast'), array('size'=>'64'));
            $mform->setType('subtitle', PARAM_NOTAGS);
            $mform->addHelpButton('subtitle', 'subtitle', 'pcast');
            $mform->disabledIf('subtitle', 'enablerssitunes', 'eq', 0);
            $mform->addRule('subtitle', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

            // Owner
            $ownerlist = array();
            $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
            if($owners = get_users_by_capability($context, 'mod/pcast:manage', 'u.*', 'u.lastaccess')) {
                foreach ($owners as $owner) {
                    $ownerlist[$owner->id] = $owner->firstname . ' ' . $owner->lastname;
                }
            }
            $mform->addElement('select', 'userid', get_string('author', 'pcast'), $ownerlist);
            $mform->addHelpButton('userid', 'author', 'pcast');
            $mform->disabledIf('userid', 'enablerssitunes', 'eq', 0);


            /// Keywords
            $mform->addElement('text', 'keywords', get_string('keywords', 'pcast'), array('size'=>'64'));
            $mform->setType('keywords', PARAM_NOTAGS);
            $mform->addHelpButton('keywords', 'keywords', 'pcast');
            $mform->disabledIf('keywords', 'enablerssitunes', 'eq', 0);
            $mform->addRule('keywords', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');


            // Generate Top Categorys;
            $newoptions = array();
            if($topcategories = $DB->get_records("pcast_itunes_categories")) {
                foreach ($topcategories as $topcategory) {
                    $value = (int)$topcategory->id * 1000;
                    $newoptions[(int)$value] = $topcategory->name;
                }
            }

            // Generate Secondary Category
            if($nestedcategories = $DB->get_records("pcast_itunes_nested_cat")) {
                foreach ($nestedcategories as $nestedcategory) {
                    $value = (int)$nestedcategory->topcategoryid * 1000;
                    $value = $value + (int)$nestedcategory->id;
                    $newoptions[(int)$value] = '&nbsp;&nbsp;' .$nestedcategory->name;
                }
            }
            ksort($newoptions);

            // Category form element
            $mform->addElement('select', 'category', get_string('category', 'pcast'),
                    $newoptions, array('size' => '1'));
            $mform->addHelpButton('category', 'category', 'pcast');
            $mform->disabledIf('category', 'enablerssitunes', 'eq', 0);

            $this->init_javascript_enhancement('category', 'smartselect',
                    array('selectablecategories' => false, 'mode' => 'compact'));


            // Content
            $explicit=array();
            $explicit[0]  = get_string('yes');
            $explicit[1]  = get_string('no');
            $explicit[2]  = get_string('clean','pcast');
            $mform->addElement('select', 'explicit', get_string('explicit', 'pcast'),$explicit);
            $mform->addHelpButton('explicit', 'explicit', 'pcast');
            $mform->disabledIf('explicit', 'enablerssitunes', 'eq', 0);
            $mform->setDefault('explicit', 2);
        }

    /// General Podcast settings
    //-------------------------------------------------------------------------------
        $mform->addElement('header', 'posting', get_string('setupposting','pcast'));

    /// Allow comments
        if($CFG->usecomments) {
            $mform->addElement('selectyesno', 'userscancomment', get_string('userscancomment', 'pcast'));
            $mform->addHelpButton('userscancomment', 'userscancomment', 'pcast');
            $mform->setDefault('userscancomment', 0);
        }

    /// Allow users to post episodes
        $mform->addElement('selectyesno', 'userscanpost', get_string('userscanpost', 'pcast'));
        $mform->addHelpButton('userscanpost', 'userscanpost', 'pcast');
        $mform->setDefault('userscanpost', 1);

    /// Require approval for posts
        $mform->addElement('selectyesno', 'requireapproval', get_string('requireapproval', 'pcast'));
        $mform->addHelpButton('requireapproval', 'requireapproval', 'pcast');
        $mform->setDefault('requireapproval', 1);

    /// Allow Display authors names
        $mform->addElement('selectyesno', 'displayauthor', get_string('displayauthor', 'pcast'));
        $mform->addHelpButton('displayauthor', 'displayauthor', 'pcast');
        $mform->setDefault('displayauthor', 0);

    /// Allow Display of viewers names
        $mform->addElement('selectyesno', 'displayviews', get_string('displayviews', 'pcast'));
        $mform->addHelpButton('displayviews', 'displayviews', 'pcast');
        $mform->setDefault('displayviews', 0);

    /// Allow users to select categories
        $mform->addElement('selectyesno', 'userscancategorize', get_string('userscancategorize', 'pcast'));
        $mform->addHelpButton('userscancategorize', 'userscancategorize', 'pcast');
        $mform->setDefault('userscancategorize', 0);
        $mform->disabledIf('userscancategorize', 'enablerssitunes', 'eq', 0);

//-------------------------------------------------------------------------------

        /// Images
        $mform->addElement('header', 'images', get_string('image', 'pcast'));
        $mform->setAdvanced('images');

        $mform->addElement('filemanager', 'image', get_string('imagefile', 'pcast'), null,
            array('subdirs'=>0,
                'maxfiles'=>1,
                'filetypes' => array('jpeg','png'),
                'returnvalue'=>'ref_id'
            ));

        // Image Size
        $size=array();
        $size[0]  = get_string('noresize','pcast');
        $size[16] = "16";
        $size[32] = "32";
        $size[48] = "48";
        $size[64] = "64";
        $size[128] = "128";
        $size[144] = "144";
        $size[200] = "200";
        $size[400] = "400";

        $mform->addElement('select', 'imageheight', get_string('imageheight', 'pcast'),$size);
        $mform->addHelpButton('imageheight', 'imageheight', 'pcast');
        $mform->setDefault('imageheight', 144);

        $mform->addElement('select', 'imagewidth', get_string('imagewidth', 'pcast'),$size);
        $mform->addHelpButton('imagewidth', 'imagewidth', 'pcast');
        $mform->setDefault('imagewidth', 144);

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        if ($this->current->instance) {
            // editing existing instance - copy existing files into draft area
            $draftitemid = file_get_submitted_draft_itemid('id');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_pcast','logo', 0, array('subdirs'=>false));
            $default_values['image'] = $draftitemid;

            // convert topcategory and nested to a single category
            $default_values['category'] = (int)$default_values['topcategory'] *1000 + (int)$default_values['nestedcategory'];

        }
    }
}
