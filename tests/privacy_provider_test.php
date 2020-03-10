<?php
namespace mod_treasurehunt;

use approved_userlist_test;
use block_html\search\content;
use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\tests\provider_testcase;
use mod_treasurehunt\model\attempt;
use mod_treasurehunt\model\road;
use mod_treasurehunt\model\stage;
use PHPUnit\Framework\TestCase;
// define('CLI_SCRIPT', true);
//defined('MOODLE_INTERNAL') || die();
//echo __DIR__;
require_once(__DIR__ . '/../../../config.php');

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
require_once($CFG->dirroot . '/mod/treasurehunt/classes/privacy/provider.php');
require_once($CFG->dirroot . '/mod/treasurehunt/classes/stage.php');
require_once($CFG->dirroot . '/mod/treasurehunt/classes/road.php');
require_once($CFG->dirroot . '/mod/treasurehunt/classes/attempt.php');

class privacy_provider_test extends provider_testcase
{
    var $stages = [];
    var $student;
    var $student2;
    var $treasurehunt;
    var $road;
    var $course;
    var $context;
    var $cm;
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->resetAfterTest(true);
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
        
        $this->cm = get_coursemodule_from_instance('treasurehunt', $treasurehunt->id);
        $this->context = context_module::instance($this->cm->id);
        
        $road = new road('road1');
        $road = treasurehunt_add_update_road($treasurehunt, $road, $this->context);
        // Two stages.
        $stage1 = new stage($road, 'stage1', 'clue to 2');
        $stage2 = new stage($road, 'stage 2 finish.', 'Congratulations');
        
        treasurehunt_add_new_stage($stage1, $road);
        treasurehunt_add_new_stage($stage2, $road);
        // Create a student which will make a treasurehunt.
        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student->id,  $course->id, $studentrole->id);
        $student2 = $generator->create_user();
        $generator->enrol_user($student2->id,  $course->id, $studentrole->id);

<<<<<<< HEAD
=======
        // Create attempts for student1.
        $attempt1 = new attempt($this->stages[0]->id, $this->student->id, "location");
        treasurehunt_insert_attempt($attempt1, $this->context);
        $attempt2 = new attempt($this->stages[0]->id, $this->student->id, "question");
        treasurehunt_insert_attempt($attempt2, $this->context);
        // Create atempts for student2.
        $attempt1 = new attempt($this->stages[0]->id, $this->student2->id, "location");
        treasurehunt_insert_attempt($attempt1, $this->context);
        $attempt2 = new attempt($this->stages[0]->id, $this->student2->id, "question");
        treasurehunt_insert_attempt($attempt2, $this->context);
                
>>>>>>> a012183beac28e2f09354c7f5a70f9358fd6b709
        $this->student = $student;
        $this->student2 = $student2;
        $this->treasurehunt = $treasurehunt;
        $this->road = $road;
        $this->stages = [$stage1, $stage2];
        $this->course = $course;

<<<<<<< HEAD
        // Create attempts for student1.
        $attempt1 = new attempt($this->stages[0]->id, $this->student->id, "location");
        treasurehunt_insert_attempt($attempt1, $this->context);
        $attempt2 = new attempt($this->stages[0]->id, $this->student->id, "question");
        treasurehunt_insert_attempt($attempt2, $this->context);
        // Create atempts for student2.
        $attempt1 = new attempt($this->stages[0]->id, $this->student2->id, "location");
        treasurehunt_insert_attempt($attempt1, $this->context);
        $attempt2 = new attempt($this->stages[0]->id, $this->student2->id, "question");
        treasurehunt_insert_attempt($attempt2, $this->context);
    }

    public function test_Delete_datafor_user () {
        $contextslist = new approved_contextlist($this->student, 'treasurehunt', [ $this->context->id ]);
        \mod_treasurehunt\privacy\provider::delete_data_for_user($contextslist);
        
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student->id);
        $this->assertEmpty($attempts, 'Attempts not deleted.');
        // student2 must have attempts.
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student2->id);
        $this->assertNotEmpty($attempts, 'Attempts deleted for other user.');
        // TODO: Check tracks.
    }

    public function test_delete_data_for_all_users_in_context()
    {
        $contextslist = new approved_contextlist($this->student, 'treasurehunt', [$this->context->id]);
        xdebug_break();
        \mod_treasurehunt\privacy\provider::delete_data_for_all_users_in_context($this->context);
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student->id);
        $this->assertEmpty($attempts, 'Attempts not deleted.');
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student2->id);
        $this->assertEmpty($attempts, 'Attempts not deleted.');
        // TODO Check tracks.
    }
    public function test_delete_data_for_users() {
        // Only delete for student 1.
        $userlist = new approved_userlist($this->context, 'treasurehunt', [$this->student->id ]);
        \mod_treasurehunt\privacy\provider::delete_data_for_users($userlist);

=======
    public function test_Delete_datafor_user () {

        $contextslist = new approved_contextlist($this->student, 'treasurehunt', [ $this->context->id ]);
        \mod_treasurehunt\privacy\provider::delete_data_for_user($contextslist);

        $attempts = $this->get_user_attempts($this->cm->instance, $this->student->id);
        $this->assertEmpty($attempts, 'Attempts not deleted.');
        // student2 must have attempts.
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student2->id);
        $this->assertCount(2, $attempts, 'Attempts deleted for other user.');
        // TODO: Check tracks.
    }

    public function test_delete_data_for_all_users_in_context()
    {
        $contextslist = new approved_contextlist($this->student, 'treasurehunt', [$this->context->id]);
        \mod_treasurehunt\privacy\provider::delete_data_for_all_users_in_context($this->context);

        $attempts = $this->get_user_attempts($this->cm->instance, $this->student->id);
        $this->assertEmpty($attempts, 'Attempts not deleted.');
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student2->id);
        $this->assertEmpty($attempts, 'Attempts not deleted.');
        // TODO Check tracks.
    }
    public function test_delete_data_for_users()
    {
        // Only delete for student 1.
        $userlist = new approved_userlist($this->context, 'treasurehunt', [$this->student->id ]);
        \mod_treasurehunt\privacy\provider::delete_data_for_users($userlist);

>>>>>>> a012183beac28e2f09354c7f5a70f9358fd6b709
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student->id);
        $this->assertEmpty($attempts, 'Attempts for student not deleted.');
        // student2 should have attempts.
        $attempts = $this->get_user_attempts($this->cm->instance, $this->student2->id);
<<<<<<< HEAD
        $this->assertNotEmpty($attempts, 'Attempts for student2 incorrectly deleted.');
=======
        $this->assertCount(2, $attempts, 'Attempts for student2 incorrectly deleted.');
>>>>>>> a012183beac28e2f09354c7f5a70f9358fd6b709
        // TODO Check tracks.
    }
    function get_user_attempts($cminstance, $userid) {
        $stages = treasurehunt_get_stages($this->cm->instance);
        $stagesids = array_keys($stages);
        global $DB;
        list($insql, $inparam) = $DB->get_in_or_equal($stagesids, SQL_PARAMS_NAMED, 'stage');
        $where = "userid = :userid AND stageid $insql";
<<<<<<< HEAD
        $params = ['userid' => $userid] + $inparam;
=======
        $params = ['userid' => $this->student->id] + $inparam;
>>>>>>> a012183beac28e2f09354c7f5a70f9358fd6b709
        global $DB;
        $res = $DB->get_records_select('treasurehunt_attempts', $where, $params);
        return $res;
    }
}
    
