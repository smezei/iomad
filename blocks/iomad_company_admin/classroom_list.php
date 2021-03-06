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

require_once( '../../config.php');
require_once($CFG->dirroot . '/local/iomad/lib/blockpage.php');
require_once( 'lib.php');
require_once($CFG->dirroot . '/local/iomad/lib/user.php');

$delete       = optional_param('delete', 0, PARAM_INT);
$confirm      = optional_param('confirm', '', PARAM_ALPHANUM);   // Md5 confirmation hash.
$sort         = optional_param('sort', 'name', PARAM_ALPHA);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 30, PARAM_INT);        // How many per page.

global $DB, $email;

$block = 'block_iomad_company_admin';

// Correct the navbar.
// Set the name for the page.
$linktext = get_string('classrooms', $block);
// Set the url.
$linkurl = new moodle_url('/blocks/iomad_company_admin/classroom_list.php');
// Build the nav bar.
company_admin_fix_breadcrumb($PAGE, $linktext, $linkurl);

$blockpage = new blockpage($PAGE, $OUTPUT, 'iomad_company_admin', 'block', 'classrooms');
$blockpage->setup();

require_login(null, false); // Adds to $PAGE, creates $OUTPUT.

// Get the SYSTEM context.
$context = get_context_instance(CONTEXT_SYSTEM);

// Set the companyid
$companyid = iomad::get_my_companyid($context);

$PAGE->set_context($context);

$baseurl = new moodle_url(basename(__FILE__), array('sort' => $sort,
                                                    'dir' => $dir,
                                                    'perpage' => $perpage));
$returnurl = $baseurl;


if ($delete and confirm_sesskey()) {
    // Delete a selected override template, after confirmation.

    require_capability('block/iomad_company_admin:classrooms_delete', $context);

    $classroom = $DB->get_record('classroom', array('id' => $delete), '*', MUST_EXIST);

    if ($confirm != md5($delete)) {
        echo $OUTPUT->header();
        $name = $classroom->name;
        echo $OUTPUT->heading(get_string('classroom_delete', $block), 2, 'headingblock header');
        $optionsyes = array('delete' => $delete, 'confirm' => md5 ($delete), 'sesskey ' => sesskey());
        echo $OUTPUT->confirm(get_string('classroom_delete_checkfull', $block, "'$name'"),
                               new moodle_url('classroom_list.php', $optionsyes),
                               'classroom_list.php');
        echo $OUTPUT->footer();
        die;
    } else if (data_submitted()) {
        $transaction = $DB->start_delegated_transaction();

        if ( $DB->delete_records('classroom', array('id' => $delete)) ) {
            $transaction->allow_commit();
            redirect($returnurl);
        } else {
            $transaction->rollback();
            echo $OUTPUT->header();
            echo $OUTPUT->notification($returnurl, get_string('deletednot', '', $classroom->name));
            die;
        }

        $transaction->rollback();
    }

}
$blockpage->display_header();

$company = new company($companyid);
echo get_string('classrooms_for', $block, $company->get_name() );

// Check we can actually do anything on this page.
require_capability('block/iomad_company_admin:classrooms', $context);

// Get the number of templates.
$objectcount = $DB->count_records('classroom', array('companyid' => $companyid));
echo $OUTPUT->paging_bar($objectcount, $page, $perpage, $baseurl);

flush();

if ($classrooms = $DB->get_recordset('classroom', array('companyid' => $companyid),
                                     'name', '*', $page, $perpage)) {
    $stredit   = get_string('edit');
    $strdelete = get_string('delete');

    $table = new html_table();
    $table->head = array ("Name", "Capacity",  "", "");
    $table->align = array ("left", "left", "center", "center");
    $table->width = "95%";

    foreach ($classrooms as $classroom) {
        if (has_capability('block/iomad_company_admin:classrooms_delete', $context)) {
            $deletebutton = "<a href=\"classroom_list.php?delete=$classroom->id&amp;sesskey=".
                             sesskey()."\">$strdelete</a>";
        } else {
            $deletebutton = "";
        }

        if (has_capability('block/iomad_company_admin:classrooms_edit', $context)) {
            $editbutton = "<a href='".
                           new moodle_url('classroom_edit_form.php', array("id" => $classroom->id)).
                           "'>$stredit</a>";
        } else {
            $editbutton = "";
        }

        $table->data[] = array ($classroom->name,
                            $classroom->capacity,
                            $editbutton,
                            $deletebutton);
    }

    if (!empty($table)) {
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($objectcount, $page, $perpage, $baseurl);
    }

    $classrooms->close();
}

if (has_capability('block/iomad_company_admin:classrooms_add', $context)) {
    echo "<a href=\"classroom_edit_form.php\">" . get_string('classrooms_add', $block) . "</a>";
}

echo $OUTPUT->footer();
