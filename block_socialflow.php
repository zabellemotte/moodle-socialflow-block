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
 * Class block_socialflow
 *
 * @package    block_socialflow
 * @copyright  2024  Zabelle Motte (UCLouvain)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialflow extends block_base {
    /**
     * Socialflow block definition
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_socialflow');
    }

    /**
     * Define where this block can be added.
     *
     * @return array<string,bool> Associative array of page types and availability.
     */
    public function applicable_formats(): array {
        return ['my' => true];
    }

     /**
      * Social flow block has no config.
      *
      * @return bool
      */
    public function has_config() {
        return false;
    }


    /**
     * Define where this block can be added.
     *
     * @return void
     */
    public function get_required_javascript(): void {
        $this->page->requires->js_call_amd('block_socialflow/optionselection', 'init');
    }

    /**
     * Define the block content.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $USER;
        global $OUTPUT;
        global $CFG, $DB;
        $cuserid = $USER->id;

        /**************************************************
         *        GET FILTER OPTIONS INFORMATIONS         *
         **************************************************
          Get list of possible options and types
         */
        $options = [];
        $values = [];
        $options[0] = get_string('option1', 'block_socialflow');
        $options[1] = get_string('option2', 'block_socialflow');
        $options[2] = get_string('option3', 'block_socialflow');
        $options[3] = get_string('option4', 'block_socialflow');
        $values[0] = get_string('value1', 'block_socialflow');
        $values[1] = get_string('value2', 'block_socialflow');
        $values[2] = get_string('value3', 'block_socialflow');
        $values[3] = get_string('value4', 'block_socialflow');
        $types = [];
        $tvalues = [];
        $types[0] = get_string('type1', 'block_socialflow');
        $types[1] = get_string('type2', 'block_socialflow');
        $types[2] = get_string('type3', 'block_socialflow');
        $tvalues[0] = 'consult';
        $tvalues[1] = 'contrib';
        $tvalues[2] = 'both';
        $itemnums = [5, 10, 15, 20, 30, 50, 100];

        // Get list of choosen options.
        if (isset($_POST['socialflow_optionchoice'])) {
            $currentchoice = $_POST['socialflow_optionchoice'];
            set_user_preference('socialflow_optionchoice', $currentchoice);
        } else if (!is_null(get_user_preferences('socialflow_optionchoice'))) {
            $currentchoice = get_user_preferences('socialflow_optionchoice');
        } else {
            $currentchoice = 14;
        }

        // Get list of choosen type.
        if (isset($_POST['socialflow_typechoice'])) {
            $currenttype = $_POST['socialflow_typechoice'];
            set_user_preference('socialflow_typechoice', $currenttype);
        } else if (!is_null(get_user_preferences('socialflow_typechoice'))) {
            $currenttype = get_user_preferences('socialflow_typechoice');
        } else {
            $currenttype = 'both';
        }

        // Get items number choice.
        if (isset($_POST['socialflow_itemnumchoice'])) {
            $currentitemnum = $_POST['socialflow_itemnumchoice'];
            set_user_preference('socialflow_itemnumchoice', $currentitemnum);
        } else if (!is_null(get_user_preferences('socialflow_itemnumchoice'))) {
            $currentitemnum = get_user_preferences('socialflow_itemnumchoice');
        } else {
            $currentitemnum = '10';
        }

        // Build user courses lists with shortnames and numbrer of active enrolled students.
        // Get numbrer of active enrolled students needs a heavy computation.
        // Verifications : active user, active enrolment method, active enrolment, student role.
        // It is sufficient to compute it 1 time a day, so it is stored in a dedicated table.
        // This table is updated via the nbpa crontask.
        // If an event arises in a new couse, the number of participants is computed and stored.
        $courselistarray = [];
        $allcourses = [];
        $studentroles = "('" . get_config('logstore_socialflow', 'tracking_roles') . "')";
        if ($courses = enrol_get_my_courses()) {
            foreach ($courses as $course) {
                $courseid = $course->id;
                $courseshortname = $course->shortname;
                $courselistarray[$courseid] = $courseshortname;
                $now = time();
                $sql1 = "SELECT nbpa FROM {logstore_socialflow_nbpa} WHERE courseid=" . $courseid;
                $result1 = $DB->get_record_sql($sql1);
                // If number of participants is not stored in nbpa table, it is computed and stored.
                if (!$result1) {
                    $sql2 = "SELECT COUNT(DISTINCT(u.id)) AS nbpa
                                         FROM {user} u
                                         INNER JOIN {user_enrolments} ue ON ue.userid = u.id
                                         INNER JOIN {enrol} e ON e.id = ue.enrolid
                                         INNER JOIN {role_assignments} ra ON ra.userid = u.id
                                         INNER JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                                         INNER JOIN {course} c ON c.id = ct.instanceid AND e.courseid = c.id
                                         INNER JOIN {role} r ON r.id = ra.roleid AND r.shortname IN " . $studentroles . "
                                         WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                                         AND (ue.timeend = 0 OR ue.timeend > " . $now . ") 
                                         AND ue.status = 0 AND c.id =" . $courseid;
                    $result2 = $DB->get_record_sql($sql2);
                    if (!$result2) {
                        // If there are no student in the course, there should not have actions.
                        // But this ensures no error occures.
                        $nbpa = 1;
                    } else {
                        // When a student operates the first action in a course ...
                        // The number of participants is not stored in the nbpa table and need to be computed.
                        // The information is then stored in the nbpa table so that it is not more computed.
                        $nbpa = $result2->nbpa;
                        $data = new \stdClass();
                        $data->courseid = $courseid;
                        $data->nbpa = $nbpa;
                        $result4 = $DB->insert_record('logstore_socialflow_nbpa', $data);
                    }
                } else {
                    $nbpa = $result1->nbpa;
                    // To avoid a potential zero division.
                    if ($nbpa == 0) {
                        $nbpa = 1;
                    }
                }
                $coursenbpa[$courseid] = $nbpa;
                array_push($allcourses, $courseid);
            }
        } else {
            $this->content = new stdClass();
            $this->content->text = "<div class='socialflow_error'> " . get_string('nodata', 'block_socialflow') . '<div>';
            return $this->content;
        }

        // Get list of choosen courses or select all.
        if (isset($_POST['socialflow_courseschoice'])) {
            $currentcourses = $_POST['socialflow_courseschoice'];
            $currentcoursesstring = implode(',', $currentcourses);
            set_user_preference('socialflow_courseschoice', $currentcoursesstring);
        } else if (!is_null(get_user_preferences('socialflow_courseschoice'))) {
            $currentcoursesstring = get_user_preferences('socialflow_courseschoice');
            $allcurrentcourses = explode(',', $currentcoursesstring);
            $currentcourses = [];
            // Exclude hidden courses from current courses.
            foreach ($allcurrentcourses as $courseid) {
                $course = $DB->get_record('course', ['id' => $courseid], 'id, visible');
                if ($course && $course->visible) {
                    // Add the course to the list of visible courses.
                    $currentcourses[] = $courseid;
                }
            }
            if ($currentcourses == []) {
                $this->content = new stdClass();
                $this->content->text = "<div class='socialflow_error'> " . get_string('nodata', 'block_socialflow') . '<div>';
                return $this->content;
            }
        } else {
            $currentcourses = $allcourses;
            $currentcoursesstring = implode(',', $currentcourses);
        };

        /***********************************************************
         *          DISPLAY FILTER OPTIONS AND BUTTONS             *
         ***********************************************************
         */
        $this->content = new stdClass(); // Necessary to avoid PHP 8 error !
        $this->content->text = '';

        // Button to open option selection form.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_optionselectopener',
        'id' => 'socialflow_optionselectopener']);
        $this->content->text .= get_string('osotext', 'block_socialflow');
        $this->content->text .= "<span class='expanded-icon'><i class='icon fa fa-chevron-down fa-fw'></i></span>";
        $this->content->text .= html_writer::end_tag('div');

        // Button to open course selection form.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_courseselectopener',
        'id' => 'socialflow_courseselectopener']);
        $this->content->text .= get_string('csotext', 'block_socialflow');
        $this->content->text .= "<span class='expanded-icon'><i class='icon fa fa-chevron-down fa-fw'></i></span>";
        $this->content->text .= html_writer::end_tag('div');

        // Button to open type selection form.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_typeselectopener',
        'id' => 'socialflow_typeselectopener']);
        $this->content->text .= get_string('tsotext', 'block_socialflow');
        $this->content->text .= "<span class='expanded-icon'><i class='icon fa fa-chevron-down fa-fw'></i></span>";
        $this->content->text .= html_writer::end_tag('div');

        // Button to open itemnum selection form.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_itemnumselectopener',
        'id' => 'socialflow_itemnumselectopener']);
        $this->content->text .= get_string('insotext', 'block_socialflow');
        $this->content->text .= "<span class='expanded-icon'><i class='icon fa fa-chevron-down fa-fw'></i></span>";
        $this->content->text .= html_writer::end_tag('div');

        // Button to open helptext.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_helpopener',
        'id' => 'socialflow_helpopener']);
        $this->content->text .= get_string('helpbuttontext', 'block_socialflow');
        $this->content->text .= "<span class='expanded-icon'><i class='icon fa fa-chevron-down fa-fw'></i></span>";
        $this->content->text .= html_writer::end_tag('div');

        // OPTION SELECTION FORM.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_optionselectblock',
        'id' => 'socialflow_optionselectblock']);
        $this->content->text .= html_writer::start_tag('form', ['action' => '', 'method' => 'post',
        'class' => 'socialflow_optionselectform']);
        // Radio boxes with list of options.
        for ($i = 0; $i < count($options); $i++) {
            if ($values[$i] == $currentchoice) {
                $checked = 'checked';
            } else {
                $checked = '';
            }
            $this->content->text .= "<input type='radio' name='socialflow_optionchoice' value='"
            . $values[$i] . "' " . $checked . "/>";
            $this->content->text .= "<label>" . $options[$i] . "</label>";
        }
        // Submit button for option selection form.
        $this->content->text .= html_writer::start_tag('button', ['class' => 'socialflow_optionselectbutton',
        'onclick' => 'document.getElementById("socialflow_optionselectblock").submit()']);
        $this->content->text .= get_string("save", "block_socialflow");
        $this->content->text .= html_writer::end_tag('button');
        $this->content->text .= html_writer::end_tag('form');
        $this->content->text .= html_writer::end_tag('div');

        // COURSE SELECTION FORM.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_courseselectblock',
        'id' => 'socialflow_courseselectblock']);
        $this->content->text .= html_writer::start_tag('form', ['action' => '', 'method' => 'post',
        'class' => 'socialflow_courseselectform']);
        // Checkboxes with list of courses.
        foreach ($courses as $course) {
            $courseid = $course->id;
            if (in_array($courseid, $currentcourses)) {
                $checked = true;
            } else {
                $checked = false;
            }
            $courseshortname = $course->shortname;
            $this->content->text .= html_writer::checkbox('socialflow_courseschoice[]', $courseid, $checked, $courseshortname);
        }
        // Submit button for course selection form.
        $this->content->text .= html_writer::start_tag('button', ['class' => 'socialflow_courseselectbutton',
        'onclick' => 'document.getElementById("socialflow_courseselectblock").submit()']);
        $this->content->text .= get_string("save", "block_socialflow");
        $this->content->text .= html_writer::end_tag('button');
        $this->content->text .= html_writer::end_tag('form');
        $this->content->text .= html_writer::end_tag('div');

        // TYPE SELECTION FORM.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_typeselectblock',
        'id' => 'socialflow_typeselectblock']);
        $this->content->text .= html_writer::start_tag('form', ['action' => '', 'method' => 'post',
        'class' => 'socialflow_typeselectform']);
        // Radio boxes with list of options.
        for ($i = 0; $i < count($types); $i++) {
            if ($tvalues[$i] == $currenttype) {
                $checked = 'checked';
            } else {
                $checked = '';
            }
            $this->content->text .= "<input type='radio' name='socialflow_typechoice' value='"
            . $tvalues[$i] . "' " . $checked . "/>";
            $this->content->text .= "<label>" . $types[$i] . "</label>";
        }
        // Submit button for type selection form.
        $this->content->text .= html_writer::start_tag('button', ['class' => 'socialflow_typeselectbutton',
        'onclick' => 'document.getElementById("socialflow_typeselectblock").submit()']);
        $this->content->text .= get_string("save", "block_socialflow");
        $this->content->text .= html_writer::end_tag('button');
        $this->content->text .= html_writer::end_tag('form');
        $this->content->text .= html_writer::end_tag('div');

        // ITEM NUMBER SELECTION FORM.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_itemnumselectblock',
        'id' => 'socialflow_itemnumselectblock']);
        $this->content->text .= html_writer::start_tag('form', ['action' => '', 'method' => 'post',
        'class' => 'socialflow_itemnumselectform']);
        // Radio boxes with list of options.
        for ($i = 0; $i < count($itemnums); $i++) {
            if ($itemnums[$i] == $currentitemnum) {
                $checked = 'checked';
            } else {
                $checked = '';
            }
            $this->content->text .= "<input type='radio' name='socialflow_itemnumchoice' value='"
            . $itemnums[$i] . "' " . $checked . "/>";
            $this->content->text .= "<label>" . $itemnums[$i] . "</label>";
        }
        // Submit button for item number selection form.
        $this->content->text .= html_writer::start_tag('button', ['class' => 'socialflow_itemnumselectbutton',
        'onclick' => 'document.getElementById("socialflow_itemnumselectblock").submit()']);
        $this->content->text .= get_string("save", "block_socialflow");
        $this->content->text .= html_writer::end_tag('button');
        $this->content->text .= html_writer::end_tag('form');
        $this->content->text .= html_writer::end_tag('div');

        // HELP BLOCK.
        $this->content->text .= html_writer::start_tag('div', ['class' => 'socialflow_helpblock', 'id' => 'socialflow_helpblock']);
        $this->content->text .= get_string('helptext', 'block_socialflow');
        $surveylink = get_string('surveylink', 'block_socialflow');
        if ($surveylink != "") {
            $this->content->text .= get_string('surveytextintro', 'block_socialflow') . "<a href='"
            . $surveylink . "' target='_blank'> " . get_string('surveytextlink', 'block_socialflow')
            . "</a>" . get_string('surveytextend', 'block_socialflow');
        }
        $this->content->text .= html_writer::start_tag('form', ['action' => '', 'method' => 'post',
        'class' => 'socialflow_helpform']);
        // Close button for socialflow_helpform.
        $this->content->text .= html_writer::start_tag('button', ['class' => 'socialflow_closehelpbutton',
        'onclick' => 'document.getElementById("socialflow_helpblock").style.display = "none";']);
        $this->content->text .= get_string("close", "block_socialflow");
        $this->content->text .= html_writer::end_tag('button');
        $this->content->text .= html_writer::end_tag('form');
        $this->content->text .= html_writer::end_tag('div');

        /*********************************
         *     GET SOCIAL FLOW DATA      *
         *********************************
         */
        if (empty($currentchoice) || $currentchoice < 0) {
            $currentchoice = 14;
        }
        $loglifetime = time() - ($currentchoice * 3600 * 24); // Value in days.
        $now = time();

        // Social flow data is computed based on hits, chosen log period ...
        // Closing dates and chosen action type (contib or consult).
        // The imbricated query with first part that selects the pertinent hits line ...
        // Enables to ensure good performance (WITH event_hits AS (...)).
        // Closed activities are excluded from social flow when 48 hours passed after closing date to avoid student despondency.
        // This is done with this part of query (c.closingdate<(".$now."+172800)).
        // Frequences are computed based on a CASE clause to enable computation for all courses in 1 query.
        // Actions associated to hidden activities and resources are hidden from the social flow (cm.visible = 1).
        // Actions associated to activities or ressources in an hidden section are hidden from the social flow (cs.visible=1).
        // Query has been adapted to the different moodle supported database types based on chatgpt help.

        $dbtype = $CFG->dbtype;

        $sql = "WITH event_hits AS (
                         SELECT h.id, h.courseid, h.contextid, h.eventid, h.nbhits, h.userids
                         FROM {logstore_socialflow_hits} h
                         INNER JOIN {logstore_socialflow_closing} c ON h.id = c.hitid
                         WHERE h.courseid IN (" . $currentcoursesstring . ") AND h.lasttime > "
                         . $loglifetime . " AND c.closingdate > (" . $now . " + 172800)
                     )
                     SELECT ei.id AS hitid, ei.contextid, ei.eventid, ei.courseid, evts.actiontype, evts.moduletable,
                     evts.hasclosingdate, evts.haslatesubmit, evts.latedatefield, c.instanceid, cm.instance, m.name, ei.userids,
                     CASE ";

        foreach ($currentcourses as $id) {
            $nbpa = $coursenbpa[$id];
            $sql .= "WHEN ei.courseid = " . $id . " THEN ei.nbhits / " . $nbpa . " ";
        }
        $sql .= "END AS freq
                    FROM event_hits ei
                    INNER JOIN {logstore_socialflow_evts} evts ON ei.eventid = evts.id
                    INNER JOIN {context} c ON ei.contextid = c.id
                    INNER JOIN {course_modules} cm ON c.instanceid = cm.id
                    INNER JOIN {course_sections} cs ON cm.section = cs.id
                    INNER JOIN {modules} m ON cm.module = m.id
                    WHERE cm.visible = 1 AND cs.visible = 1 ";

        if ($currenttype != 'both') {
            $sql .= "AND evts.actiontype = '" . $currenttype . "' ";
        }

        // Hit query depend on the SGBD, thanks to Chatgpt for conversion !
        switch ($dbtype) {
            case 'mariadb':
                $sql .= "ORDER BY freq DESC LIMIT " . $currentitemnum . ";";
                break;
            case 'mysqli':
                $sql .= "ORDER BY freq DESC LIMIT " . $currentitemnum . ";";
                break;
            case 'pgsql':
                $sql .= "ORDER BY freq DESC LIMIT " . $currentitemnum . ";";
                break;
            case 'sqlsrv':
                $sql .= "ORDER BY freq DESC OFFSET 0 ROWS FETCH NEXT " . $currentitemnum . " ROWS ONLY;";
                break;
            case 'oci':
                $sql = "SELECT * FROM (
                                SELECT ei.id AS hitid, ei.contextid, ei.eventid, ei.courseid, evts.actiontype, evts.moduletable,
                                evts.hasclosingdate, evts.haslatesubmit, evts.latedatefield, c.instanceid, cm.instance, m.name,
                                ei.userids,
                                CASE ";
                foreach ($currentcourses as $id) {
                    $nbpa = $coursenbpa[$id];
                    $sql .= "WHEN ei.courseid = " . $id . " THEN ei.nbhits / " . $nbpa . " ";
                }
                $sql .= "END AS freq,
                                 ROW_NUMBER() OVER (ORDER BY freq DESC) AS rownum
                                 FROM event_hits ei
                                 INNER JOIN {logstore_socialflow_evts} evts ON ei.eventid = evts.id
                                 INNER JOIN {context} c ON ei.contextid = c.id
                                 INNER JOIN {course_modules} cm ON c.instanceid = cm.id
                                 INNER JOIN {course_sections} cs ON cm.section = cs.id
                                 INNER JOIN {modules} m ON cm.module = m.id
                                 WHERE cm.visible = 1 AND cs.visible = 1 ";
                if ($currenttype != 'both') {
                    $sql .= "AND evts.actiontype = '" . $currenttype . "' ";
                }
                $sql .= ") WHERE rownum <= " . $currentitemnum . ";";
                break;
            case 'sqlite':
                $sql .= "ORDER BY freq DESC LIMIT " . $currentitemnum . ";";
                break;
            default:
                throw new Exception("Unsupported SGBD: " . $dbtype);
        }
        // Display the $sql variable to discover the beautiful socialflow query !
        // Add this command on a new line : $this->content->text.="<div> $sql </div>";.
        $result = $DB->get_recordset_sql($sql); // See https://moodle.org/mod/forum/discuss.php?d=60818.

        /**************************************
         *   DISPLAY SOCIAL FLOW DATA         *
         **************************************
         */
        if (!$result) {
            $this->content->text .= "<div class='socialflow_error'> " . get_string('nodata', 'block_socialflow') . '<div>';
        } else {
            $this->content->text .= "<div id='socialflowdata'> <table id='socialflow_table' class='generaltable'>";
            foreach ($result as $row) {
                $hitid = $row->hitid;
                $contextid = $row->contextid;
                $eventid = $row->eventid;
                $courseid = $row->courseid;
                $shortname = $courselistarray[$courseid];
                $actiontype = $row->actiontype;
                if ($actiontype == 'contrib') {
                    $actiontypelang = get_string('contrib', 'block_socialflow');
                } else {
                    $actiontypelang = get_string('consult', 'block_socialflow');
                }
                $instanceid = $row->instanceid;
                // Verification that current user may access the module with respect to access restrictions.
                $modinfo = get_fast_modinfo($courseid);
                $cm = $modinfo->get_cm($instanceid);
                if ($cm->uservisible) {
                    $available = 1;
                } else {
                    $available = 0;
                }
                $instance = $row->instance;
                $modulename = $row->name;
                $freq = $row->freq;
                $moduletable = $row->moduletable;
                $modulenameinlang = get_string("modulename", "$moduletable");
                $moduleicon = $OUTPUT->pix_icon('icon', $modulenameinlang, $modulename, ['class' => 'iconlarge activityicon']);
                $moduleurl = new moodle_url('/mod/' . $modulename . "/view.php", ['id' => $instanceid]);
                // Get the activity or resource title.
                $sql5 = "SELECT name FROM {" . $modulename . "} WHERE id=$instance";
                $result5 = $DB->get_record_sql($sql5);
                if (!$result5) {
                    continue;
                }
                $title = $result5->name;
                // Express frequency in percent.
                $freqp = round(100 * $freq, 0, PHP_ROUND_HALF_UP);
                // Test role of the actual user on course where action occured ...
                // To know if done/todo informations have to be shown.
                $context = context_COURSE::instance($courseid);
                if ($available == 1) {
                    if (has_capability('moodle/site:config', $context, $cuserid, true)) { // User is an admin.
                        $done = "";
                    } else if (has_capability('mod/assign:submit', $context, $cuserid, true)) { // Faster than role verification.
                        // Hits table stores concatenated userids to make access to this information fast.
                        $useridsstring = $row->userids;
                        $userids = explode(',', $useridsstring);
                        $isdone = 0;
                        if (in_array($cuserid, $userids)) {
                            $isdone = 1;
                        } else {
                            // Verify if user operated action recently while searching in recent logs (since last hits cron run).
                            $sql52 = "SELECT lastruntime FROM {task_scheduled} WHERE component
                            LIKE 'logstore_socialflow' AND classname LIKE '%hits%' ";
                            $result52 = $DB->get_record_sql($sql52);
                            if ($result52) {
                                $lastruntimet = $result52->lastruntime;
                                $sql53 = "WITH recent_log AS (SELECT * FROM {logstore_socialflow_log}
                                               WHERE timecreated>$lastruntimet)
                                               SELECT COUNT(id) AS nbdone
                                               FROM recent_log
                                               WHERE courseid=$courseid
                                               AND contextid=$contextid
                                               AND eventid=$eventid
                                               AND userid=$cuserid";
                                $result53 = $DB->get_record_sql($sql53);
                                if ($result53) {
                                    $nbrecent = $result53->nbdone;
                                    if ($nbrecent > 0) {
                                        $isdone = 1;
                                    } else {
                                        $isdone = 0;
                                    }
                                } else {
                                    $isdone = 0;
                                }
                            }
                        }

                        // When action not done, a custom comment is added to done/to do information ...
                        // When a closing date or late date exists.
                        // Some activities (assign and forum) allow a late date, that does not close the activity ...
                        // But activities after the late date are noticed as late.
                        // Latedate or closingdate are integrated to the comment when not in the past.
                        // When latedate and closingdate are in the past, they are not shown and activity is noticed as closed.
                        // Closed activities are excluded from social flow when 48 hours passed after closing date.
                        // This is done to avoid student despondency, while viewing closed activities for 1 or 2 weeks.
                        if ($isdone == 0) {
                            $comment = "";
                            $hasclosingdate = $row->hasclosingdate;
                            $haslatesubmit = $row->haslatesubmit;
                            if ($hasclosingdate > 0) {
                                // Get the closing date information.
                                $sql6 = "SELECT closingdate FROM {logstore_socialflow_closing} WHERE hitid=" . $hitid;
                                $result6 = $DB->get_record_sql($sql6);
                                // Error on $result6 should not arrise has far as all hits have a closingdate completed.
                                // But let make sure it works whatever.
                                if ($result6) {
                                    $closingdatet = $result6->closingdate;
                                    // When closing date is undefined, the closingdate value is 9999999999.
                                    if ($closingdatet == 9999999999) {
                                        $closingdatet = null;
                                    } else {
                                        $closingdate = userdate($closingdatet, '%a %d %B %H:%M');
                                    }
                                }
                                if ($haslatesubmit > 0) {
                                    $latedatefield = $row->latedatefield;
                                    $sql7 = "SELECT " . $latedatefield . " FROM {" . $moduletable . "} WHERE id=" . $instance;
                                    // In core plugins, the default value for late date field is 0.
                                    // But in some additionnal plugins default value is null.
                                    $result7 = $DB->get_record_sql($sql7);
                                    if (($result7) && ($result7 !== null)) {
                                        $latedatet = $result7->$latedatefield;
                                        // In additionnal plugins, it may arise that undefined latedate correspond to value 0.
                                        if ($latedatet == 0) {
                                            $latedatet = null;
                                        } else {
                                            $latedate = userdate($latedatet, '%a %d %B %H:%M');
                                        }
                                    }
                                    // If no late date defined, $latedate and $latedatet are not defined.
                                }
                                if (isset($latedate)) {
                                    if ($now < $latedatet) {
                                        $comment = "<br/><span class='socialflow_comment'>" .
                                        get_string('limitdate', 'block_socialflow') .
                                        "<br/>" . $latedate . "</span>";
                                    } else if ($now < $closingdatet) {
                                        $comment = "<br/><span class='socialflow_comment socialflow_critical'>" .
                                        get_string('latedate', 'block_socialflow') . "<br/>" . $closingdate . "</span>";
                                    } else {
                                        $comment = "<br/><span class='socialflow_comment socialflow_critical'>" .
                                        get_string('closed', 'block_socialflow') . "</span>";
                                    }
                                } else if (isset($closingdate)) {
                                    if ($now < $closingdatet) {
                                        $comment = "<br/><span class='socialflow_comment'>" .
                                        get_string('limitdate', 'block_socialflow') .
                                        "<br/>" . $closingdate . "</span>";
                                    } else {
                                        $comment = "<br/><span class='socialflow_comment socialflow_critical'>" .
                                        get_string('closed', 'block_socialflow') . "</span>";
                                    }
                                } else {
                                    $comment = "";
                                }
                            }
                            $done = '<span class="socialflow_todo">' . get_string('todo', 'block_socialflow')
                            . '</span>' . $comment;
                        } else {
                            $done = '<span class="socialflow_done">' . get_string('done', 'block_socialflow') . '</span>';
                        }
                    } else {
                        // User is not student, so it is a teacher or admin that does not need to have done/todo informations.
                        $done = "";
                    }
                } else { // Here $available=0 and student may not acces to the activity or ressource due to restriction.
                    $done = '<span class="socialflow_restricted">' . get_string('restricted', 'block_socialflow') . '</span>';
                }
                if ($available == 1) {
                    $this->content->text .= "<tr><td>$shortname</td><td>$moduleicon</td><td>$modulenameinlang</td>
                    <td><a href='" . $moduleurl . "'>$title</a></td><td>$actiontypelang </td> <td>$freqp%<td> $done </td></tr>";
                } else {
                    // It is impossible to exclude restricted activities or ressources from social flow.
                    // So, they are show in gray with unactive link, this is explained in socialflow help tab.
                    $this->content->text .= "<tr class='no_access'><td>$shortname</td><td>$moduleicon</td><td>$modulenameinlang</td>
                    <td>$title</td><td>$actiontypelang</td><td>$freqp%<td> $done </td></tr>";
                }
            }
            $this->content->text .= "</table></div>";
        }
        // Indicate when social flow informations will be updated based on the crontask configuration.
        $sql8 = "SELECT nextruntime FROM {task_scheduled} WHERE component LIKE 'logstore_socialflow' AND classname LIKE '%hits%' ";
        $result8 = $DB->get_record_sql($sql8);
        if ($result8) {
            $nextruntimet = $result8->nextruntime;
            $nextruntime = userdate($nextruntimet, '%A %d %B %Y %H:%M');
            $this->content->text .= "<div class='block_socialflow_update'>" . get_string('nextupdate', 'block_socialflow') .
            $nextruntime . "</div>";
        }
        return $this->content;
    }
}
