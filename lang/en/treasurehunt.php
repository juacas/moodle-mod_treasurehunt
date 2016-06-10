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
 * English strings for treasurehunt
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_treasurehunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Treasure Hunt';
$string['modulenameplural'] = 'Treasures Hunt';
$string['modulename_help'] = 'Use the treasure hunt module for... | The treasurehunt module allows...';
$string['treasurehuntfieldset'] = 'Custom example fieldset';
$string['riddlename'] = 'Riddle\'s name';
$string['roadname'] = 'Road\'s name';
$string['continue'] = 'Continue';
$string['updates'] = 'Updates';
$string['user'] = 'User';
$string['group'] = 'Group';
$string['start'] = 'Start';
$string['nogroupassigned'] = 'No group assigned to this road';
$string['overcomefirstriddle'] = 'To discover the first riddle you should start from the marked area on the map';
$string['nouserassigned'] = 'No user assigned to this road';
$string['userprogress'] = 'User progress successfully updated';
$string['usersprogress'] = 'Progress users';
$string['usersprogress_help'] = 'Indicates the progress of the riddles of each student / group according to the colors: '
        . '<P>The color <B> green </B> indicates that the riddle has been overcome without failures.</P>'
        . '<P>The color <B> yellow </B> indicates that the riddle has been oavercome with failures.</P>'
        . '<P>The color <B> red </B> indicates that the riddle has not been overcome and failures have been made.</P>'
        . '<P>The color <B> grey </B> indicates that the riddle has not been overcome and no failures have been made.</P>';
$string['attempt'] = 'Attempt';
$string['state'] = 'State';
$string['play'] = 'Play';
$string['reviewofplay'] = 'Review of play';
$string['treasurehuntclosed'] = 'This treasure hunt closed on {$a}';
$string['treasurehuntcloseson'] = 'This treasure hunt will close at {$a}';
$string['historicalattempts'] = 'Historical attempts of {$a}';
$string['history'] = 'History';
$string['aerialview'] = 'Aerial';
$string['roadview'] = 'Road';
$string['loading'] = 'Loading';
$string['mapview'] = 'Map view';
$string['noexsitsriddle'] = 'There is no riddle number {$a} in the database. Reload the page';
$string['noattempts'] = 'You have not made any attempt';
$string['nouserattempts'] = '{$a} has not made any attempt';
$string['notcreateriddle'] = 'Attempts have already been made in this road, you can not add more riddles';
$string['notdeleteriddle'] = 'Attempts have already been made in this road, you can not delete any riddle';
$string['notchangeorderriddle'] = 'You can not change the order of riddles after attempts have been made on the road';
$string['noroads'] = 'No roads have been added yet';
$string['noresults'] = 'No results found.';
$string['nomarks'] = 'First mark on the map the desired point.';
$string['startfromhere'] = 'You can only start from here';
$string['userlocationovercome'] = 'Succesful location of riddle {$a->number} on the date: {$a->date}';
$string['userriddleovercome'] = 'Riddle {$a->number} overcome on the date: {$a->date}';
$string['userlocationfailed'] = 'Failed location of riddle {$a->number} on the date: {$a->date}';
$string['usercompletionovercome'] = 'Activity to end successfully completed for riddle {$a->number} on the date: {$a->date}';
$string['userquestionfailed'] = 'Failed answer to the question of riddle {$a->number} on the date: {$a->date}';
$string['userquestionovercome'] = 'Succesful answer to the question of riddle {$a->number} on the date: {$a->date}';
$string['groupquestionovercome'] = 'Succesful answer by {$a->user} to the question of riddle {$a->number} on the date: {$a->date}';
$string['groupquestionfailed'] = 'Failed answer by {$a->user} to the question of riddle {$a->number} on the date: {$a->date}';
$string['grouplocationovercome'] = 'Succesful location by {$a->user} of riddle {$a->number} on the date: {$a->date}';
$string['groupriddleovercome'] = 'Riddle {$a->number} overcome by {$a->user} on the date: {$a->date}';
$string['grouplocationfailed'] = 'Failed location by {$a->user} of riddle {$a->number} on the date: {$a->date}';
$string['groupcompletionovercome'] = 'Activity to end successfully completed by {$a->user} for riddle {$a->number} on the date: {$a->date}';
$string['successlocation'] = 'It is the right place!';
$string['faillocation'] = 'It is not the right place';
$string['lockedriddle'] = 'Locked riddle';
$string['lockedcpriddle'] = 'You must perform the activity \'<strong>{$a}</strong>\' to unlock the riddle';
$string['lockedqacriddle'] = 'You must perform the activity \'<strong>{$a}</strong>\' and correctly answer the following question to unlock the riddle';
$string['lockedqriddle'] = 'You must correctly answer the following question to unlock the riddle';
$string['treasurehuntname'] = 'Treasure hunt\'s name';
$string['treasurehunt'] = 'Treasure hunt';
$string['notreasurehunts'] = 'Nothing to do here';
$string['pluginadministration'] = 'Treasure hunt administration';
$string['pluginname'] = 'Treasure Hunt';
$string['question_treasurehunt'] = 'This works?';
$string['hello'] = 'Hello';
$string['welcome'] = 'Welcome to my module treasure hunt, I hope you enjoy';
$string['question'] = 'Question';
$string['addsimplequestion'] = 'Add simple question';
$string['addsimplequestion_help'] = 'Adds a simple question before displaying the description of this riddle';
$string['insert_road'] = 'Insert new road';
$string['insert_riddle'] = 'Insert new riddle';
$string['saveemptyridle'] = 'All modified riddles must have geometry before saving';
$string['erremptyriddle'] = 'All riddles must have at least one geometry so that the road is valid';
$string['errvalidroad'] = 'There must be at least two riddles that have at least one geometry so that the road is valid';
$string['confirm_delete_riddle'] = 'The riddle(s) were successfully removed';
$string['eventriddleupdated'] = 'Riddle has been updated';
$string['eventriddlecreated'] = 'Riddle has been created';
$string['eventriddledeleted'] = 'Riddle has been deleted';
$string['eventroadupdated'] = 'Road has been updated';
$string['eventroadcreated'] = 'Road has been created';
$string['eventroaddeleted'] = 'Road has been deleted';
$string['treasurehunt:managetreasure'] = 'Manage treasurehunt';
$string['treasurehunt:view'] = 'View treasurehunt';
$string['treasurehunt:addinstance'] = 'Add a new treasurehunt';
$string['treasurehuntislocked'] = '{$a} is editing this treasurehunt right now. Try to edit it in a few minutes.';
$string['availability'] = 'Availability';
$string['restrictionsdiscoverriddle'] = 'Restrictions to discover riddle';
$string['groups'] = 'Groups';
$string['edittreasurehunt'] = 'Edit treasure hunt';
$string['editingtreasurehunt'] = 'Editing treasure hunt';
$string['editriddle'] = 'Edit riddle';
$string['editingriddle'] = 'Editing riddle';
$string['addingriddle'] = 'Adding riddle';
$string['editroad'] = 'Edit road';
$string['editingroad'] = 'Editing road';
$string['addingroad'] = 'Adding road';
$string['gradingsummary'] = 'Grading summary';
$string['groupmode'] = 'Students play in groups';
$string['changetogroupmode'] = 'The game mode has changed to play in groups';
$string['changetoindividualmode'] = 'The game mode has changed to individual play';
$string['changetoplaywithoutmove'] = 'The game mode has changed to static play';
$string['changetoplaywithmove'] = 'The game mode has changed to dinamyc play';
$string['groupmode_help'] = 'If enabled students will be divided into groups based on the default set of groups or a custom grouping for each road. A group game will be shared among group members and all members of the group will see each others changes to the game.';
$string['allowattemptsfromdate'] = 'Allow attempts from';
$string['allowattemptsfromdate_help'] = 'If enabled, students will not be able to play before this date. If disabled, students will be able to start submitting right away.';
$string['cutoffdate'] = 'Cut-off date';
$string['cutoffdate_help'] = 'If set, the assignment will not accept submissions after this date without an extension.';
$string['cutoffdatefromdatevalidation'] = 'Cut-off date must be after the allow submissions from date.';
$string['alwaysshowdescription'] = 'Always show description';
$string['alwaysshowdescription_help'] = 'If disabled, the Assignment Description above will only become visible to students at the "Allow submissions from" date.';
/* * Template */
$string['sendlotacion_title'] = 'Are you sure you want to send this location?';
$string['sendlotacion_content'] = 'This action can not be undone.';
$string['cancel'] = 'Cancel';
$string['send'] = 'Send';
$string['exit'] = 'Exit';
$string['back'] = 'Back';
$string['layers'] = 'Layers';
$string['searching'] = 'Searching';
$string['discoveredriddle'] = 'Discovered riddle';
$string['failedlocation'] = 'Failed location';
$string['riddledescription'] = 'Description to locate the next riddle';
$string['riddledescription_help'] = 'Here you should describe the riddle to reach the next location. '
        . 'In case it is the last riddle, must leave a feedback message indicating that the treasure hunt has ended.';
$string['info_validate_location'] = 'Validate location of this riddle';
$string['button_validate_location'] = 'Validate location';
$string['search'] = 'Search';
$string['info'] = 'Info';
$string['riddles'] = 'Riddles';
$string['playwithoutmove'] = 'Playing without moving';
$string['playwithoutmove_help'] = 'If this option is enabled students may play from their computers without moving to places. A mark on the map is enabled to select the desired point';
$string['groupid'] = 'Group assigned to the road';
$string['groupid_help'] = 'Users in this group are assigned to this road when the game starts. If there is only one road and the selected option is "none", all participants in the activity will play for it';
$string['groupingid'] = 'Grouping assigned to the road';
$string['groupingid_help'] = 'Groups in this grouping are assigned to this road when the game starts';
$string['activitytoend'] = 'Complete selected activity before';
$string['activitytoend_help'] = 'The selected activity must be completed before the current riddle is displayed. For the activities of the course to be displayed in the list it must be enabled the completion activity in Moodle\'s configuration, in course\'s configuration and the activity itself.';
$string['noteam'] = 'Not a member of any group';
$string['nogroupplay'] = 'You have no road assigned, so you can not play the activity.';
$string['nogroupingplay'] = 'You have no group assigned to a road, so you can not play the activity.';
$string['nogrouproad'] = '{$a} has no road assigned.';
$string['groupmultipleroads'] = '{$a} has more than one road assigned.';
$string['groupinvalidroad'] = '{$a} has assigned an invalid road.';
$string['nouserroad'] = '{$a} has no road assigned.';
$string['usermultipleroads'] = '{$a} has more than one road assigned.';
$string['usermultiplesameroad'] = '{$a} belong to more than one group assigned to the same road.';
$string['userinvalidroad'] = '{$a} has assigned an invalid road.';
$string['multiplegroupsplay'] = 'You have assigned more than one road, so you can not play the activity.';
$string['multiplegroupingsplay'] = 'Your group has assigned more than one road, so you can not play the activity.';
$string['multiplegroupssameroadplay'] = 'You belong to more than one group assigned to the same road, so you can not play the activity.';
$string['invalidassignedroad'] = 'Assigned road is not validated';
$string['invalroadid'] = 'The road is not validated';
$string['multipleteamsplay'] = 'Member of more than one group, so unable to make the activity.';
$string['warnusersgrouping'] = 'The following groups belong to more than one grouping: {$a}, so are unable to play the activity.';
$string['warnusersgroup'] = 'The following users belong to more than one group: {$a}, so are unable to play the activity.';
$string['warnusersoutside'] = 'The following users do not belong to any group/grouping: {$a}, so are unable to play the activity.';
$string['timelabelfailed'] = 'Location sent on the date: ';
$string['timelabelsuccess'] = 'Riddle discovered on the date: ';
$string['correctanswer'] = 'Correct answer.';
$string['errcorrectsetanswerblank'] = 'Correct answer is set, but the Answer is blank';
$string['errnocorrectanswers'] = 'There must be only one correct answer';
$string['errcorrectanswers'] = 'You must select a correct answer';
$string['errsendinganswer'] = 'The road has been updated while you was sending the answer, try again';
$string['errsendinglocation'] = 'The road has been updated while you was sending the location, try again';
$string['erroutoftimeanswer'] = 'You can not send the answer, you are out of your delivery time';
$string['erroutoftimelocation'] = 'You can not send the location, you are out of your delivery time';
$string['gradefromtime'] = 'Grade from time';
$string['gradefromriddles'] = 'Grade from riddles';
$string['gradefromposition'] = 'Grade from position';
$string['grademethod'] = 'Grading method';
$string['grademethodinfo'] = 'Grading method: {$a}';
$string['treasurehuntnotavailable'] = 'The treasure hunt will not be available until {$a}';
$string['treasurehuntopenedon'] = 'This treasure hunt opened at {$a}';
$string['grademethod_help'] = '<P><B>Grade from riddles</B><P>
<UL>
<P>Each player (or team) scores proportionally by number of riddles solved, 
being 100% when a road is completely solved, and 0% when no riddle is solved.</UL>

<P><B>Grade from time</B><P>
<UL>
<P>The winner of the hunt marks the best time. The grade is calculated 
by interpolating the finishing time being 50% the end time of the hunt 
and 100% the best finishing time. The players that did not finish the 
hunt receive a grade under 50 calculated just by the number of riddles solved.</UL>

<P><B>Grade from position</B><P>
<UL>
<P>The score is calculated by interpolating the position in the ranking, 
being 100% the score for the first player and 50% for the last player. 
The players that did not finish the hunt receive a grade under 50% calculated 
just by the number of riddles solved.</UL>';
$string['gradepenlocation'] = 'Penalize failure in location';
$string['gradepenanswer'] = 'Penalize failure in answer';
$string['gradepenlocation_help'] = 'Penalization is expressed in % of the grade. '
        . 'Per example, if penalization is 5.4, a player with 3 failures will penalize '
        . 'his grade by 16.2%, that is, will receive 83.8% of the grade calculated by the rest of the criteria.';
$string['errpenalizationexceed'] = 'The penalty can not be greater than 100';
$string['errpenalizationfall'] = 'The penalty can not be less than 0';
$string['errnumeric'] = 'You must enter a valid decimal number';
$string['treasurehunt:addriddle'] = 'Add riddle';
$string['treasurehunt:addroad'] = 'Add road';
$string['treasurehunt:editriddle'] = 'Edit riddle';
$string['treasurehunt:editroad'] = 'Edit road';
$string['treasurehunt:gettreasurehunt'] = 'Get all riddles and roads of treasure hunt';
$string['treasurehunt:managetreasurehunt'] = 'Manage treasurehunt';
$string['treasurehunt:play'] = 'Play';
