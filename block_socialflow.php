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
        $currentchoice = optional_param('socialflow_optionchoice', null, PARAM_INT);
        if ($currentchoice !== null && confirm_sesskey()) {
            set_user_preference('socialflow_optionchoice', $currentchoice);
        } else if ($saved = get_user_preferences('socialflow_optionchoice', null)) {
            $currentchoice = $saved;
        } else {
            $currentchoice = 14;
        }

        // Get list of choosen type.
        $currenttype = optional_param('socialflow_typechoice', null, PARAM_ALPHA);
        if ($currenttype !== null) {
            set_user_preference('socialflow_typechoice', $currenttype);
        } else {
            $currenttype = get_user_preferences('socialflow_typechoice', 'both');
        }

        // Get items number choice.
        $currentitemnum = optional_param('socialflow_itemnumchoice', null, PARAM_INT);
        if ($currentitemnum !== null) {
            set_user_preference('socialflow_itemnumchoice', $currentitemnum);
        } else {
            $currentitemnum = get_user_preferences('socialflow_itemnumchoice', 10);
        }

        // Build user courses lists with shortnames and numbrer of active enrolled students.
        // Get numbrer of active enrolled students needs a heavy computation.
        // Verifications : active user, active enrolment method, active enrolment, student role.
        // It is sufficient to compute it 1 time a day, so it is stored in a dedicated table.
        // This table is updated via the nbpa crontask.
        // If an event arises in a new couse, the number of participants is computed and stored.
        $courselistarray = [];
        $allcourses = [];
        $studentroles = get_config('logstore_socialflow', 'tracking_roles');
        if ($courses = enrol_get_my_courses()) {
            foreach ($courses as $course) {
                $courseid = $course->id;
                $courseshortname = $course->shortname;
                $courselistarray[$courseid] = $courseshortname;
                $now = time();
                $sql1 = "SELECT nbpa FROM {logstore_socialflow_nbpa} WHERE courseid = :courseid";
                $result1 = $DB->get_record_sql($sql1, ['courseid' => $courseid]);
                // If number of participants is not stored in nbpa table, it is computed and stored.
                if (!$result1) {
                    $studentrolesarray = explode(',', $studentroles);
                    [$roleinsql, $roleparams] = $DB->get_in_or_equal($studentrolesarray, SQL_PARAMS_NAMED);
                    $sql2 = "SELECT COUNT(DISTINCT(u.id)) AS nbpa
                              FROM {user} u
                             INNER JOIN {user_enrolments} ue ON ue.userid = u.id
                             INNER JOIN {enrol} e ON e.id = ue.enrolid
                             INNER JOIN {role_assignments} ra ON ra.userid = u.id
                             INNER JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                             INNER JOIN {course} c ON c.id = ct.instanceid AND e.courseid = c.id
                             INNER JOIN {role} r ON r.id = ra.roleid AND r.shortname $roleinsql
                             WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                               AND (ue.timeend = 0 OR ue.timeend > :now)
                               AND ue.status = 0 AND c.id = :courseid";
                    $params2 = array_merge($roleparams, [
                        'now' => $now,
                        'courseid' => $courseid,
                    ]);
                    $result2 = $DB->get_record_sql($sql2, $params2);
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
        // 1. Try to get the course list from the request (comma-separated list).
        $currentcourses = optional_param_array(
            'socialflow_courseschoice',
            null,
            PARAM_INT
        );
        if ($currentcourses !== null) {
            // Store as comma-separated string.
            $coursestr = implode(',', $currentcourses);
            set_user_preference('socialflow_courseschoice', $coursestr);
        } else {
            $coursestr = get_user_preferences('socialflow_courseschoice', '');
            $currentcourses = $coursestr !== ''
                ? array_map('intval', explode(',', $coursestr))
                : [];
        }
        if ($coursestr !== null) {
            // Save user preference.
            set_user_preference('socialflow_courseschoice', $coursestr);
        } else {
            // Otherwise, load from user preferences.
            $coursestr = get_user_preferences('socialflow_courseschoice', null);
        }

        if ($coursestr !== null && $coursestr !== '') {
            // Convert string to array of course IDs.
            $allcurrentcourses = array_map('intval', explode(',', $coursestr));
            $currentcourses = [];

            // Exclude hidden courses.
            foreach ($allcurrentcourses as $courseid) {
                $course = $DB->get_record('course', ['id' => $courseid], 'id, visible', IGNORE_MISSING);
                if ($course && $course->visible) {
                    $currentcourses[] = $courseid;
                }
            }

            // No visible courses left.
            if (empty($currentcourses)) {
                $this->content = new stdClass();
                $this->content->text = html_writer::div(
                    get_string('nodata', 'block_socialflow'),
                    'socialflow_error'
                );
                return $this->content;
            }
        } else {
            // Fallback: use all courses.
            $currentcourses = $allcourses;
            $coursestr = implode(',', $currentcourses);
        }

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
        $this->content->text .= html_writer::empty_tag('input', [
           'type'  => 'hidden',
           'name'  => 'sesskey',
           'value' => sesskey(),
        ]);
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
        $this->content->text .= html_writer::empty_tag('input', [
           'type'  => 'hidden',
           'name'  => 'sesskey',
           'value' => sesskey(),
        ]);

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
        $this->content->text .= html_writer::empty_tag('input', [
           'type'  => 'hidden',
           'name'  => 'sesskey',
           'value' => sesskey(),
        ]);

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
        $this->content->text .= html_writer::empty_tag('input', [
           'type'  => 'hidden',
           'name'  => 'sesskey',
           'value' => sesskey(),
        ]);

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

        // Prepare the courses array and placeholder for the IN() clause.
        [$courseinsql, $courseparams] = $DB->get_in_or_equal($currentcourses, SQL_PARAMS_NAMED);

        // Prepare the dynamic CASE WHEN for nbpa division with placeholders.
        $casewhens = [];
        $caseparams = [];
        foreach ($currentcourses as $i => $id) {
            $caseparam = "nbpa$i";
            $casewhens[] = "WHEN ei.courseid = :courseid$i THEN ei.nbhits / :$caseparam";
            $caseparams["courseid$i"] = $id;
            $caseparams[$caseparam] = $coursenbpa[$id];
        }

        // Base SQL query using placeholders.
        $sql = "
             WITH event_hits AS (
           SELECT h.id, h.courseid, h.contextid, h.eventid, h.nbhits, h.userids
             FROM {logstore_socialflow_hits} h
            INNER JOIN {logstore_socialflow_closing} c ON h.id = c.hitid
            WHERE h.courseid $courseinsql
              AND h.lasttime > :loglifetime
              AND c.closingdate > (:now + 172800)
           )
           SELECT ei.id AS hitid,
                  ei.contextid,
                  ei.eventid,
                  ei.courseid,
                  evts.actiontype,
                  evts.moduletable,
                  evts.hasclosingdate,
                  evts.haslatesubmit,
                  evts.latedatefield,
                  c.instanceid,
                  cm.instance,
                  m.name,
                  ei.userids,
           CASE " . implode(" ", $casewhens) . " END AS freq
           FROM event_hits ei
           INNER JOIN {logstore_socialflow_evts} evts ON ei.eventid = evts.id
           INNER JOIN {context} c ON ei.contextid = c.id
           INNER JOIN {course_modules} cm ON c.instanceid = cm.id
           INNER JOIN {course_sections} cs ON cm.section = cs.id
           INNER JOIN {modules} m ON cm.module = m.id
           WHERE cm.visible = 1 AND cs.visible = 1
        ";

        // Merge parameters for placeholders.
        $params = array_merge($courseparams, $caseparams, [
            'loglifetime' => $loglifetime,
            'now' => $now,
        ]);

        // Add optional filter by event type.
        if ($currenttype != 'both') {
            $sql .= " AND evts.actiontype = :currenttype";
            $params['currenttype'] = $currenttype;
        }

        // Add limit/offset depending on the DB type.
        switch ($dbtype) {
            case 'mariadb':
                $limit = (int)$currentitemnum; // Cast to integer to avoid injection.
                $sql .= " ORDER BY freq DESC LIMIT $limit";
                break;
            case 'mysqli':
                $limit = (int)$currentitemnum; // Cast to integer to avoid injection.
                $sql .= " ORDER BY freq DESC LIMIT $limit";
                break;
            case 'sqlite':
                 $limit = (int)$currentitemnum; // Cast to integer to avoid injection.
                $sql .= " ORDER BY freq DESC LIMIT $limit";
                break;
            case 'pgsql':
                $limit = (int)$currentitemnum; // Cast to integer to avoid injection.
                $sql .= " ORDER BY freq DESC LIMIT $limit";
                break;
            case 'sqlsrv':
                $sql .= " ORDER BY freq DESC OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY";
                $params['limit'] = $currentitemnum;
                break;
            case 'oci':
                // For Oracle, the SQL is more complex, but placeholders can be used similarly.
                // Oracle requires ROW_NUMBER() to implement LIMIT.
                // Prepare the dynamic CASE WHEN for nbpa division.
                $casewhensoci = [];
                $caseparamsoci = [];
                foreach ($currentcourses as $i => $id) {
                    $caseparam = "nbpa$i";
                    $casewhensoci[] = "WHEN ei.courseid = :courseid$i THEN ei.nbhits / :$caseparam";
                    $caseparamsoci["courseid$i"] = $id;
                    $caseparamsoci[$caseparam] = $coursenbpa[$id];
                }

                // Build Oracle-specific query.
                $sql = "
                    SELECT * FROM (
                        SELECT ei.id AS hitid,
                               ei.contextid,
                               ei.eventid,
                               ei.courseid,
                               evts.actiontype,
                               evts.moduletable,
                               evts.hasclosingdate,
                               evts.haslatesubmit,
                               evts.latedatefield,
                               c.instanceid,
                               cm.instance,
                               m.name,
                               ei.userids,
                               CASE " . implode(" ", $casewhensoci) . " END AS freq,
                               ROW_NUMBER() OVER (ORDER BY CASE " . implode(" ", $casewhensoci) . " END DESC) AS rownum
                         FROM {logstore_socialflow_hits} ei
                         INNER JOIN {logstore_socialflow_closing} ccl ON ei.id = ccl.hitid
                         INNER JOIN {logstore_socialflow_evts} evts ON ei.eventid = evts.id
                         INNER JOIN {context} c ON ei.contextid = c.id
                         INNER JOIN {course_modules} cm ON c.instanceid = cm.id
                         INNER JOIN {course_sections} cs ON cm.section = cs.id
                         INNER JOIN {modules} m ON cm.module = m.id
                         WHERE ei.courseid $courseinsql
                           AND ei.lasttime > :loglifetime
                           AND ccl.closingdate > (:now + 172800)
                           AND cm.visible = 1
                           AND cs.visible = 1
                ";

                // Optional filter for event type.
                if ($currenttype != 'both') {
                    $sql .= " AND evts.actiontype = :currenttype";
                        $caseparamsoci['currenttype'] = $currenttype;
                }

                // Close the outer query to apply the row limit.
                $sql .= ") WHERE rownum <= :limit";
                    $caseparamsoci['limit'] = $currentitemnum;

                // Merge all parameters.
                $params = array_merge($courseparams, $caseparamsoci, [
                   'loglifetime' => $loglifetime,
                   'now' => $now,
                ]);
                break;
            default:
            throw new Exception("Unsupported DB type: " . $dbtype);
        }

        // Execute the secure recordset query.
        $result = $DB->get_recordset_sql($sql, $params);

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
                                $sql53 = "
                                    WITH recent_log AS (
                                        SELECT * FROM {logstore_socialflow_log}
                                         WHERE timecreated > :lastruntimet
                                    )
                                    SELECT COUNT(id) AS nbdone
                                      FROM recent_log
                                     WHERE courseid = :courseid
                                       AND contextid = :contextid
                                       AND eventid = :eventid
                                       AND userid = :userid
                                ";

                                $params = [
                                   'lastruntimet' => $lastruntimet,
                                   'courseid' => $courseid,
                                   'contextid' => $contextid,
                                   'eventid' => $eventid,
                                   'userid' => $cuserid,
                                ];

                                $result53 = $DB->get_record_sql($sql53, $params);
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
                                $sql6 = "SELECT closingdate FROM {logstore_socialflow_closing} WHERE hitid = :hitid";
                                $result6 = $DB->get_record_sql($sql6, ['hitid' => $hitid]);
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
