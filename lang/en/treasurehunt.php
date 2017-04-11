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
 * Strings for component 'treasurehunt', language 'en'
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['activitytoend'] = 'Complete selected activity before';
$string['activitytoend_help'] = 'The selected activity must be completed before the current clue is displayed. '
        . 'For the activities of the course to be displayed in the list it must be enabled the completion activity in '
        . 'Moodle\'s configuration, in the course and the activity itself.';
$string['activitytoendovercome'] = 'Activity \'<strong>{$a}</strong>\' overcome';
$string['activitytoendwarning'] = 'You must complete first the activity to solve';
$string['actnotavailableyet'] = 'The activity is not available yet';
$string['add'] = 'Add';
$string['addingroad'] = 'Adding road';
$string['addingstage'] = 'Adding stage';
$string['addsimplequestion'] = 'Add simple question';
$string['addsimplequestion_help'] = 'Adds a simple question before displaying the clue of this stage';
$string['aerialmap'] = 'Aerial';
$string['aerialview'] = 'Aerial';
$string['allowattemptsfromdate'] = 'Allow attempts from';
$string['allowattemptsfromdate_help'] = 'If enabled, students will not be able to play before this date. '
        . 'If disabled, students will be able to start play right away.';
$string['alwaysshowdescription'] = 'Always show description';
$string['alwaysshowdescription_help'] = 'If disabled, the Treasure hunt Description above will only become visible to students '
        . 'at the "Allow attempts from" date.';
$string['answerwarning'] = 'You must first answer the question';
$string['areyousure'] = 'Are you sure?';
$string['attempt'] = 'Attempt';
$string['attemptsdeleted'] = 'Treasure hunt attempts deleted';
$string['availability'] = 'Availability';
$string['back'] = 'Back';
$string['backtocourse'] = 'Back to the course';
$string['basemaps'] = 'Base maps';
$string['cancel'] = 'Cancel';
$string['changetogroupmode'] = 'The game mode has changed to play in groups';
$string['changetoindividualmode'] = 'The game mode has changed to individual play';
$string['changetoplaywithmove'] = 'The game mode has changed to dinamyc play';
$string['changetoplaywithoutmoving'] = 'The game mode has changed to static play';
$string['configintro'] = 'The values you set here define the default values that are used in the settings form when you '
        . 'create a new treasure hunt.';
$string['configmaximumgrade'] = 'The default grade that the treasure hunt grade is scaled to be out of.';
$string['confirm'] = 'Confirm';
$string['confirmdeletestage'] = 'The stage was successfully removed';
$string['continue'] = 'Continue';
$string['correctanswer'] = 'Correct answer.';
$string['cutoffdate'] = 'Cut-off date';
$string['cutoffdate_help'] = 'If set, the treasure hunt will not accept attempts after this date without an extension.';
$string['cutoffdatefromdatevalidation'] = 'Cut-off date must be after the allow submissions from date.';
$string['discoveredlocation'] = 'Discovered location';
$string['editingroad'] = 'Editing road';
$string['editingstage'] = 'Editing stage';
$string['editingtreasurehunt'] = 'Editing treasure hunt';
$string['edition'] = 'Edition panel';
$string['edition_help'] = 'To enable the geometry creation and edition panel must select the stage you want to edit';
$string['editroad'] = 'Edit road';
$string['editstage'] = 'Edit stage';
$string['edittreasurehunt'] = 'Edit treasure hunt';
$string['errcorrectanswers'] = 'You must select a correct answer';
$string['errcorrectsetanswerblank'] = 'Correct answer is set, but the answer is blank';
$string['erremptystage'] = 'All stages must have at least one geometry so that the road is valid';
$string['errnocorrectanswers'] = 'There must be only one correct answer';
$string['errnumeric'] = 'You must enter a valid decimal number';
$string['error'] = 'Error';
$string['errpenalizationexceed'] = 'The penalty can not be greater than 100';
$string['errpenalizationfall'] = 'The penalty can not be less than 0';
$string['errsendinganswer'] = 'The road has been updated while you was sending the answer, try again';
$string['errsendinglocation'] = 'The road has been updated while you was sending the location, try again';
$string['errvalidroad'] = 'There must be at least two stages that have at least one geometry so that the road is valid';
$string['eventattemptsubmitted'] = 'Attempt submitted';
$string['eventroadcreated'] = 'Road created';
$string['eventroaddeleted'] = 'Road deleted';
$string['eventroadupdated'] = 'Road updated';
$string['eventstagecreated'] = 'Stage created';
$string['eventstagedeleted'] = 'Stage deleted';
$string['eventstageupdated'] = 'Stage updated';
$string['exit'] = 'Back to Course';
$string['failedlocation'] = 'Failed location';
$string['faillocation'] = 'It is not the right place';
$string['gamemodeinfo'] = 'Game mode: {$a}';
$string['gameupdatetime'] = 'Game update time';
$string['gameupdatetime_help'] = 'Time interval in seconds between a user\'s game update and another. '
        . 'The larger, less update requests should be made, but more time passes to report a possible change. '
        . 'It must be greater than 0 seconds, but the time will be set by default.';
$string['gradefromposition'] = 'Grade from position';
$string['gradefromstages'] = 'Grade from stages';
$string['gradefromtime'] = 'Grade from time';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = '<P><B>Grade from stages</B><P>
<UL>
<P>Each player (or team) scores proportionally by number of stages solved, 
being 100% when a road is completely solved, and 0% when no stage is solved.</UL>

<P><B>Grade from time</B><P>
<UL>
<P>The winner of the hunt marks the best time. The grade is calculated 
by interpolating the finishing time being 50% the end time of the hunt 
and 100% the best finishing time. The players that did not finish the 
hunt receive a grade under 50 calculated just by the number of stages solved.</UL>

<P><B>Grade from position</B><P>
<UL>
<P>The score is calculated by interpolating the position in the ranking, 
being 100% the score for the first player and 50% for the last player. 
The players that did not finish the hunt receive a grade under 50% calculated 
just by the number of stages solved.</UL>';
$string['grademethodinfo'] = 'Grading method: {$a}';
$string['gradepenanswer'] = 'Penalty for failure in answer';
$string['gradepenlocation'] = 'Penalty for failure in location';
$string['gradepenlocation_help'] = 'Penalization is expressed in % of the grade. '
        . 'Per example, if penalization is 5.4, a player with 3 failures will penalize '
        . 'his grade by 16.2%, that is, will receive 83.8% of the grade calculated by the rest of the criteria.';
$string['gradesdeleted'] = 'Treasure hunt grades deleted';
$string['gradingsummary'] = 'Grading summary';
$string['group'] = 'Group';
$string['groupactivityovercome'] = 'Activity to end successfully completed by {$a->user} for stage {$a->position} on the date: {$a->date}';
$string['groupid'] = 'Group assigned to the road';
$string['groupid_help'] = 'Users in this group are assigned to this road when the game starts. '
        . 'If there is only one road and the selected option is "none", all participants in the activity will play for it';
$string['groupingid'] = 'Grouping assigned to the road';
$string['groupingid_help'] = 'Groups in this grouping are assigned to this road when the game starts';
$string['groupinvalidroad'] = '{$a} has assigned an invalid road.';
$string['grouplocationfailed'] = 'Failed location by {$a->user} of stage {$a->position} on the date: {$a->date}';
$string['grouplocationovercome'] = 'Succesful location by {$a->user} of stage {$a->position} on the date: {$a->date}';
$string['groupmode'] = 'Students play in groups';
$string['groupmode_help'] = 'If enabled students will be divided into groups based on the configuration of course groups. '
        . 'A group game will be shared among group members and they will see the changes in the game.';
$string['groupmultipleroads'] = '{$a} has more than one road assigned.';
$string['groupquestionfailed'] = 'Failed answer by {$a->user} to the question of stage {$a->position} on the date: {$a->date}';
$string['groupquestionovercome'] = 'Succesful answer by {$a->user} to the question of stage {$a->position} on the date: {$a->date}';
$string['groups'] = 'Groups';
$string['groupstageovercome'] = 'Stage {$a->position} overcome by {$a->user} on the date: {$a->date}';
$string['hello'] = 'Hello';
$string['historicalattempts'] = 'Historical attempts of {$a}';
$string['history'] = 'History';
$string['huntcompleted'] = 'You have already completed this treasure hunt';
$string['incorrectanswer'] = 'Incorrect answer.';
$string['info'] = 'Info';
$string['infovalidatelocation'] = 'Validate location of this stage';
$string['invalidassignedroad'] = 'Assigned road is not validated';
$string['invalroadid'] = 'The road is not validated';
$string['layers'] = 'Layers';
$string['loading'] = 'Loading';
$string['lockedclue'] = 'Locked clue';
$string['lockedaclue'] = 'You must perform the activity \'<strong>{$a}</strong>\' to unlock the clue';
$string['lockedaqclue'] = 'You must perform the activity \'<strong>{$a}</strong>\' and correctly answer the following '
        . 'question to unlock the clue';
$string['lockedqclue'] = 'You must correctly answer the following question to unlock the clue';
$string['locktimeediting'] = 'Lock time editing';
$string['locktimeediting_help'] = 'Time in seconds for which a user can edit an instance without renewing the lock. '
        . 'The larger, fewer requests for renewal lock must be made, but more time is locked the edit page once the user has finished. '
        . 'It must be greater than 5 seconds, but the time will be set by default.';
$string['mapview'] = 'Map view';
$string['modify'] = 'Modify';
$string['modulename'] = 'Treasure Hunt';
$string['modulename_help'] = 'This module will be used to perform an activity geolocation';
$string['modulenameplural'] = 'Treasures Hunt';
$string['movingplay'] = 'Moving play';
$string['multiplegroupingsplay'] = 'Your group has assigned more than one road, so you can not play the activity.';
$string['multiplegroupsplay'] = 'You have assigned more than one road, so you can not play the activity.';
$string['multiplegroupssameroadplay'] = 'You belong to more than one group assigned to the same road, so you can not play the activity.';
$string['multipleteamsplay'] = 'Member of more than one group, so unable to make the activity.';
$string['mustanswerquestion'] = 'You must correctly answer the question before continuing';
$string['mustcompleteactivity'] = 'You must overcome the activity to complete before continuing';
$string['mustcompleteboth'] = 'You must answer the question correctly and overcome the activity to complete before continuing';
$string['noanswerselected'] = 'You must select an answer';
$string['noattempts'] = 'You have not made any attempt';
$string['noexsitsstage'] = 'There is no stage number {$a} in the database. Reload the page';
$string['nogroupassigned'] = 'No group assigned to this road';
$string['nogroupingplay'] = 'You have no group assigned to a road, so you can not play the activity.';
$string['nogroupplay'] = 'You have no road assigned, so you can not play the activity.';
$string['nogrouproad'] = '{$a} has no road assigned.';
$string['nomarks'] = 'First mark on the map the desired point.';
$string['noresults'] = 'No results found.';
$string['noroads'] = 'No roads have been added yet';
$string['notchangeorderstage'] = 'You can not change the order of stages after attempts have been made on the road';
$string['notcreatestage'] = 'Attempts have already been made in this road, you can not add more stages';
$string['notdeletestage'] = 'Attempts have already been made in this road, you can not delete any stage';
$string['noteam'] = 'Not a member of any group';
$string['notreasurehunts'] = 'There is no treasure hunt in this course';
$string['nouserassigned'] = 'No user assigned to this road';
$string['nouserattempts'] = '{$a} has not made any attempt';
$string['nouserroad'] = '{$a} has no road assigned.';
$string['outoftime'] = 'Out of time';
$string['overcomefirststage'] = 'To discover the first stage you should start from the marked area on the map';
$string['play'] = 'Play';
$string['playstagewithoutmoving'] = 'Discover stage without moving';
$string['playstagewithoutmoving_help'] = 'If this option is enabled, students can discover this stage without moving to any place. '
        . 'To do this, every time the student takes a simple click on the map a mark is created, erasing the previous '
        . 'if any, indicating the last desired point. Upon completion of the stage, the game will change to the default '
        . 'settings of the activity';
$string['playwithoutmoving'] = 'Playing without moving';
$string['playwithoutmoving_help'] = 'If this option is enabled, students may play from their computers without moving to '
        . 'places. To do this, every time the student takes a simple click on the map a mark is created, erasing the previous '
        . 'if any, indicating the last desired point.';
$string['pluginadministration'] = 'Treasure hunt administration';
$string['pluginname'] = 'Treasure Hunt';
$string['question'] = 'Question';
$string['remove'] = 'Remove';
$string['removealltreasurehuntattempts'] = 'Delete all treasure hunts attempts';
$string['removedactivitytoend'] = 'Activity to complete has been removed';
$string['removedquestion'] = 'The question has been removed';
$string['removeroadwarning'] = 'If you remove the road all associated stages were removed and you can no longer recover';
$string['removewarning'] = 'If you remove it you can not retrieve it';
$string['restrictionsdiscoverstage'] = 'Restrictions to discover stage';
$string['reviewofplay'] = 'Review of play';
$string['road'] = 'Road';
$string['roadmap'] = 'Road';
$string['roadname'] = 'Road\'s name';
$string['roadview'] = 'Road';
$string['save'] = 'Save';
$string['saveemptyridle'] = 'All modified stages must have geometry before saving';
$string['savewarning'] = 'You have not saved changes.';
$string['search'] = 'Search';
$string['searching'] = 'Searching';
$string['searchlocation'] = 'Search location';
$string['send'] = 'Send';
$string['sendlotacioncontent'] = 'This action can not be undone.';
$string['sendlotaciontitle'] = 'Are you sure you want to send this location?';
$string['stage'] = 'Stage';
$string['stageclue'] = 'Clue to locate the next stage';
$string['stageclue_help'] = 'Here you should describe the clue to reach the next location. '
        . 'In case it is the last stage, must leave a feedback message indicating that the treasure hunt has ended.';
$string['stagename'] = 'Stage\'s name';
$string['stageovercome'] = 'Stage overcome';
$string['stages'] = 'Stages';
$string['start'] = 'Start';
$string['startfromhere'] = 'You can only start from here';
$string['state'] = 'State';
$string['successlocation'] = 'It is the right place!';
$string['timeexceeded'] = 'You have exceeded the time limit for the activity. This screen only serves to review the game';
$string['timelabelfailed'] = 'Location sent on the date: ';
$string['timelabelsuccess'] = 'Stage discovered on the date: ';
$string['treasurehunt'] = 'Treasure hunt';
$string['treasurehunt:addinstance'] = 'Add a new treasurehunt';
$string['treasurehunt:addroad'] = 'Add road';
$string['treasurehunt:addstage'] = 'Add stage';
$string['treasurehuntclosed'] = 'This treasure hunt closed on {$a}';
$string['treasurehuntcloses'] = 'Treasure hunt closes';
$string['treasurehuntcloseson'] = 'This treasure hunt will close at {$a}';
$string['treasurehunt:editroad'] = 'Edit road';
$string['treasurehunt:editstage'] = 'Edit stage';
$string['treasurehuntislocked'] = '{$a} is editing this treasurehunt right now. Try to edit it in a few minutes.';
$string['treasurehunt:managetreasure'] = 'Manage treasurehunt';
$string['treasurehunt:managetreasurehunt'] = 'Manage treasurehunt';
$string['treasurehuntname'] = 'Treasure hunt\'s name';
$string['treasurehuntnotavailable'] = 'The treasure hunt will not be available until {$a}';
$string['treasurehuntopens'] = 'Treasure hunt opens';
$string['treasurehuntopenedon'] = 'This treasure hunt opened at {$a}';
$string['treasurehunt:play'] = 'Play';
$string['treasurehunt:view'] = 'View treasurehunt';
$string['treasurehunt:viewusershistoricalattempts'] = 'View users historical attempts';
$string['updates'] = 'Updates';
$string['updatetimes'] = 'Update times';
$string['user'] = 'User';
$string['useractivityovercome'] = 'Activity to end successfully completed for stage {$a->position} on the date: {$a->date}';
$string['userinvalidroad'] = '{$a} has assigned an invalid road.';
$string['userlocationfailed'] = 'Failed location of stage {$a->position} on the date: {$a->date}';
$string['userlocationovercome'] = 'Succesful location of stage {$a->position} on the date: {$a->date}';
$string['usermultipleroads'] = '{$a} has more than one road assigned.';
$string['usermultiplesameroad'] = '{$a} belong to more than one group assigned to the same road.';
$string['userprogress'] = 'User progress successfully updated';
$string['userquestionfailed'] = 'Failed answer to the question of stage {$a->position} on the date: {$a->date}';
$string['userquestionovercome'] = 'Succesful answer to the question of stage {$a->position} on the date: {$a->date}';
$string['usersprogress'] = 'Progress users';
$string['usersprogress_help'] = 'Indicates the progress of the stages of each student / group according to the colors: '
        . '<P>The color <B> green </B> indicates that the stage has been overcome without failures.</P>'
        . '<P>The color <B> yellow </B> indicates that the stage has been oavercome with failures.</P>'
        . '<P>The color <B> red </B> indicates that the stage has not been overcome and failures have been made.</P>'
        . '<P>The color <B> grey </B> indicates that the stage has not been overcome and no failures have been made.</P>';
$string['userstageovercome'] = 'Stage {$a->position} overcome on the date: {$a->date}';
$string['validatelocation'] = 'Validate location';
$string['warmatchanswer'] = 'The answer does not match the question';
$string['warnusersgroup'] = 'The following users belong to more than one group: {$a}, so are unable to play the activity.';
$string['warnusersgrouping'] = 'The following groups belong to more than one grouping: {$a}, so are unable to play the activity.';
$string['warnusersoutside'] = 'The following users do not belong to any group/grouping: {$a}, so are unable to play the activity.';

// Initial tour help
$string['addstage_tour']='Each road must have two or more stages. Each stage gives a clue to find out the next.';
$string['addroad_tour']='Add one or more roads to be followed by your students.';
$string['editend_tour']='Enjoy making exciting games for your students!';
$string['map_tour']='In this map you can manage all the components of a funny geolocated game!';
$string['remove_tour']= 'You can delete parts of the locations geometries. Just select a polygon and then press this button.';
$string['roads_tour'] = 'In this area you will find the diferent roads of your game. Select one of them to edit the stages.';
$string['save_tour'] = 'After drawing yout locations, don\'t forget to save your changes.';
$string['searchlocation_tour'] = 'Whith this search area you can find your way rapidly';
$string['stages_tour'] = 'In this area you will find the stages of the selected road. Select each stage to zoom to the location of the stages in the map.';
$string['welcome_edit_tour']='Welcome to the authoring page of TreasureHunt. ';

$string['autolocate_tour'] = 'While playing, you can geolocate yourself using the GPS of your device with this button. Please, give permissions to use "location" when asked.';
$string['validatelocation_tour'] = 'When you are confident about the location of a stage you must submit your position to check if you are correct.';
$string['lastsuccessfulstage_tour'] = 'In this panel you can find out your last successfull stage. It can be yours of your group\'s successfull stage.';
$string['mapplay_tour']='In this map you can see all attempts o this geolocated game! Passed stages are marked with <img src="pix/parchment.png" width="28"/> and failed stages with <img src="pix/failure.png" width="28"/>';
$string['playend_tour']='Enjoy pursuing the treasure with your mates!';
$string['welcome_play_tour'] = 'Welcome to the Treasure Hunt play screen. This is the main interface to research, chase and win your treasure.';
$string['nextstep']='Next';
$string['prevstep']='Prev';
$string['skiptutorial']='Quit';
$string['donetutorial']='End';