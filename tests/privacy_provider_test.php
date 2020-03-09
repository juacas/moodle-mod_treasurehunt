<?php
namespace mod_treasurehunt;
use block_html\search\content;
use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\TestCase;
// define('CLI_SCRIPT', true);
//defined('MOODLE_INTERNAL') || die();
//echo __DIR__;
require_once(__DIR__ . '/../../../config.php');

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
require_once($CFG->dirroot . '/mod/treasurehunt/classes/privacy/provider.php');


class privacy_provider_test extends provider_testcase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->resetAfterTest();
xdebug_break();
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $params = [
            'course' => $course->id,
            'name' => 'First treasurehunt Activity',
            'intro' => 'Intro text',
            'introformat' => 1,
            'playwithoutmoving' => true,
            'groupmode' => false,
            'alwaysshowdescription' => false,
            'allowattemptsfromdate' => 1581007560,
			"cutoffdate" => 1612630200,
			"grade" => 10,
			"gradepenlocation" => 0.00000,
			"gradepenanswer" => 0.00000,
			"grademethod" => 1,
			"tracking" => true,
			"custommapconfig" => null,
			"completionfinish" => 1,
            "completionpass" => 0,
            "custombackground" => false
        ];

        $plugingenerator = $generator->get_plugin_generator('mod_treasurehunt');
        $this->setAdminUser();
        // The treasurehunt activity.
        $treasurehunt = $plugingenerator->create_instance($params);
        // Create another treasurehunt activity.
        $cm = get_coursemodule_from_instance('treasurehunt', $treasurehunt->id);

        // Create a student which will make a treasurehunt.
        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student->id,  $course->id, $studentrole->id);

        trea

        treasurehunt_user_submit_response($optionids[2], $treasurehunt, $student->id, $course, $cm);
        $this->student = $student;
        $this->treasurehunt = $treasurehunt;
        $this->course = $course;
    }

    public function testCalculate()
    {
        $this->assertEquals(2, 1 + 1);
    }

    public function test_Delete_datafor_user () {
        // TODO create test scenario.

        /** @var moodle_database $DB */
        global $DB;
        $user = $DB->get_record('user', ['id'=>20]);
        xdebug_break();

        $cm = get_course_and_cm_from_cmid(59);
        $context = context_module::instance(59);
        $contextslist = new approved_contextlist($user, 'treasurehunt', [ $context->id ]);
        \mod_treasurehunt\privacy\provider::delete_data_for_user($contextslist);
        // TODO assertions

        $stages = treasurehunt_get_stages($cm->instance);
        $stagesids = array_keys($stages);
        list($insql, $inparam) = $DB->get_in_or_equal($stagesids, SQL_PARAMS_NAMED, 'stage');
        $where = "userid = :userid AND stageid $insql";
        $params = ['userid' => $user->id] + $inparam;
        $res = $DB->get_records_select('treasurehunt_attempts', $where, $params);
        assertFalse($res, 'Not deleted.');
    }
}
    
