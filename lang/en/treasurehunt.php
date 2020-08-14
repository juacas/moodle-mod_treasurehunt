<?php
// This file is part of Treasurehunt for Moodle
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
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['activitytoend'] = 'Complete selected activity before';
$string['activitytoend_help'] = 'The selected activity must be completed before the current clue is displayed.
For the activities of the course to be displayed in the list it must be enabled the completion activity in
Moodle\'s configuration, in the course and the activity itself.';
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
$string['allowattemptsfromdate_help'] = 'If enabled, students will not be able to play before this date.
If disabled, students will be able to start play right away.';
$string['alwaysshowdescription'] = 'Always show description';
$string['alwaysshowdescription_help'] = 'If disabled, the Treasure hunt Description above will only become visible to students
at the "Allow attempts from" date.';
$string['answerwarning'] = 'You must first answer the question';
$string['areyousure'] = 'Are you sure?';
$string['attempt'] = 'Attempt';
$string['attempthistory'] = 'Attempt history';
$string['attemptsdeleted'] = 'Treasure hunt attempts deleted';
$string['availability'] = 'Availability';
$string['back'] = 'Back';
$string['backtocourse'] = 'Back to the course';
$string['baselayers'] = 'Base layers';
$string['basemaps'] = 'Base maps';
$string['cancel'] = 'Cancel';
$string['changecamera'] = 'Change camera';
$string['changetogroupmode'] = 'The game mode has changed to play in groups';
$string['changetoindividualmode'] = 'The game mode has changed to individual play';
$string['changetoplaywithmove'] = 'The game mode has changed to dynamic play';
$string['changetoplaywithoutmoving'] = 'The game mode has changed to static play';
$string['completionfinish'] = 'Require to finish road.';
$string['completionfinish_help'] = 'Complete when user passes all the stages in a road.';
$string['configintro'] = 'The values you set here define the default values that are used in the settings form when you
create a new treasure hunt.';
$string['configmaximumgrade'] = 'The default grade that the treasure hunt grade is scaled to be out of.';
$string['confirm'] = 'Confirm';
$string['confirmdeletestage'] = 'The stage was successfully removed';
$string['continue'] = 'Continue';
$string['correctanswer'] = 'Correct answer.';
$string['cleartreasurehunt'] = 'Reset the Treasure Hunt';
$string['cleartreasurehunt_done'] = 'The activity has been reset. All activity of the participants has been deleted.';
$string['cleartreasurehuntconfirm'] = 'Beware this action. All activity recorded will be deleted if you continue. Usually, this action is only needed if you need to change the number of roads or stages but the activity is blocked because someone has started to play the game.';
$string['clue'] = 'Clue';
$string['customlayername'] = 'Layer title';
$string['customlayername_help'] = 'If you use a custom layer a title is needed to show it in the maps or your users. If the title is empty the custom layer is fully disabled.';
$string['custommapimagefile'] = 'Custom image for map';
$string['custommapimagefile_help'] = 'Upload a image of enough resolution and fill the next 4 fields with the projection coordinates over the ground';
$string['custommapping'] = 'Custom map';
$string['custommapminlat'] = 'South latitude';
$string['custommapminlat_help'] = 'South latitude of the image. Use "." as decimal point. Greater than -85 degrees and lower than north latitude.';
$string['custommapminlon'] = 'West longitude';
$string['custommapminlon_help'] = 'West longitude of the image. Use "." as decimal point. Greater than -180 degrees and lower than east longitude.';
$string['custommapmaxlat'] = 'North latitude';
$string['custommapmaxlat_help'] = 'North latitude of the image. Use "." as decimal point. Lower than 85 degrees and greater than south latitude.';
$string['custommapmaxlon'] = 'East longitude';
$string['custommapmaxlon_help'] = 'East longitude of the image. Use "." as decimal point. Less than 180 degrees and greater than west longitude.';
$string['customlayertype'] = 'Layer type';
$string['customlayertype_help'] = 'The layer can be the only visible in the background or can be layered above the standard base maps.';
$string['customlayerwms'] = 'WMS service';
$string['customlayerwms_help'] = 'Use a map layer from an OGC WMS service. (For example EUNIS Forest Ecosystems WMS can be configured by: WMS: <code style="overflow-wrap: break-word;word-wrap: break-word;">http://bio.discomap.eea.europa.eu/arcgis/services/Ecosystem/Ecosystems/MapServer/WMSServer</code> PARAMS: <code>LAYERS=4</code>)';
$string['customwmsparams'] = 'WMS params';
$string['customwmsparams_help'] = 'These parameters define the look of the map. The format follows the following format: "LAYERS=background,streets&STYLES=blue,default" (For example EUNIS Forest Ecosystems WMS can be configured by: WMS: <code style="overflow-wrap: break-word;word-wrap: break-word;">http://bio.discomap.eea.europa.eu/arcgis/services/Ecosystem/Ecosystems/MapServer/WMSServer</code> PARAMS: <code>LAYERS=4</code>)';
$string['custommapbaselayer'] = 'The image is shown as an ADDITIONAL map background option';
$string['custommaponlybaselayer'] = 'The image is shown as the ONLY map background option';
$string['custommapoverlaylayer'] = 'The image is rendered above the standard map';
$string['custommapnongeographic'] = 'The image is not geographical';
$string['cutoffdate'] = 'Cut-off date';
$string['cutoffdate_help'] = 'If set, the treasure hunt will not accept attempts after this date without an extension.';
$string['cutoffdatefromdatevalidation'] = 'Cut-off date must be after the allow submissions from date.';
$string['discoveredlocation'] = 'Discovered location';
$string['editingroad'] = 'Editing road';
$string['editingstage'] = 'Editing stage';
$string['editingtreasurehunt'] = 'Editing treasure hunt';
$string['edition'] = 'Edition panel';
$string['edition_help'] = 'To enable the geometry creation and the buttons in the edition panel the stage you want to edit must be selected';
$string['editactivity_help'] = 'You can find a step-by-step tutorial about creating treasurehunt activities at <a href="http://juacas.github.io/moodle-mod_treasurehunt/create_activity.html"> this page.</a>';
$string['editmode'] = 'Edit';
$string['drawmode'] = 'Draw';
$string['browsemode'] = 'Browse';
$string['editroad'] = 'Edit road';
$string['editstage'] = 'Edit stage';
$string['edittreasurehunt'] = 'Change roads and stages';
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
$string['eventattemptsucceded'] = 'Stage passed';
$string['eventhuntsucceded'] = 'Treasurehunt successfully finished';
$string['eventplayerentered'] = 'Player started';
$string['eventroadcreated'] = 'Road created';
$string['eventroaddeleted'] = 'Road deleted';
$string['eventroadupdated'] = 'Road updated';
$string['eventstagecreated'] = 'Stage created';
$string['eventstagedeleted'] = 'Stage deleted';
$string['eventstageupdated'] = 'Stage updated';
$string['exit'] = 'Back to Course';
$string['failedlocation'] = 'Failed location';
$string['faillocation'] = 'It is not the right place';
$string['findplace'] = 'Find a place';
$string['gamemode'] = 'Game mode';
$string['gamemodeinfo'] = 'Game mode: {$a}';
$string['gameupdatetime'] = 'Game update time';
$string['gameupdatetime_help'] = 'Time interval in seconds between a user\'s game update and another.
The larger, less update requests should be made, but more time passes to report a possible change.
It must be greater than 0 seconds, but the time will be set by default.';
$string['defaultplayerstyle'] = 'Default game screen style';
$string['playerstyle'] = 'Game screen style';
$string['availableplayerstyles'] = 'Game screen styles available.';
$string['pegmanlabel'] = 'Look around on StreetView';
$string['playerstyle_help'] = 'There are several styles of the game screen that teachers can choose';
$string['playerclassic'] = 'Classic';
$string['playerfancy'] = 'Fancy';
$string['playerbootstrap'] = 'Bootstrap';
$string['geolocation_needed_title'] = 'This application needs geolocation.';
$string['geolocation_needed'] = 'To play this game your geolocation is needed.
<p>To activate it go to your browser Settings->Site settings->Location and remove the eviction for this site.
<p>Please reload this page and answer "YES" when your browser asks you if you want to share your location.
<p>In order to use the GPS to locate this device during the Treasurehunt, the server must be accessed by secure HTTPS URLs.
In other case, only "Play without moving" mode can be used and the players need to point manually each stage on the map.
Please contact your administrator if you can\'t solve this problem.';
$string['grade_explaination_fromposition'] = '{$a->rawscore}-{$a->penalization}%: You discovered {$a->nosuccessfulstages} stages in position {$a->position}. You penalizes {$a->penalization}% due to {$a->nolocationsfailed} wrong places, and {$a->noanswersfailed} wrong answers.';
$string['grade_explaination_fromtime'] = '{$a->rawscore}-{$a->penalization}%: You needed {$a->yourtime} to complete the hunt. The best time was {$a->besttime}. You penalizes {$a->penalization}% due to {$a->nolocationsfailed} wrong places, and {$a->noanswersfailed} wrong answers.';
$string['grade_explaination_fromabsolutetime'] = '{$a->rawscore}-{$a->penalization}%: You ended the huntg at {$a->yourtime}. The best time was at{$a->besttime}. You penalizes {$a->penalization}% due to {$a->nolocationsfailed} wrong places, and {$a->noanswersfailed} wrong answers.';
$string['grade_explaination_fromstages'] = '{$a->rawscore}-{$a->penalization}%: You discovered {$a->nosuccessfulstages} out of {$a->nostages} stages. You penalizes {$a->penalization}% due to {$a->nolocationsfailed} wrong places, and {$a->noanswersfailed} wrong answers.';
$string['grade_explaination_temporary'] = 'Unfinished hunt, receives 50% of score from stages: {$a->rawscore}-{$a->penalization}%: You discovered {$a->nosuccessfulstages} out of {$a->nostages} stages. You penalizes {$a->penalization}% due to {$a->nolocationsfailed} wrong places, and {$a->noanswersfailed} wrong answers.';
$string['gradefromposition'] = 'Grade from position';
$string['gradefromstages'] = 'Grade from stages';
$string['gradefromtime'] = 'Grade from finishing time';
$string['gradefromabsolutetime'] = 'Grade from duration of the hunt';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = '<p><b>Grade from stages</b></p>
<p>Each player (or team) scores proportionally by number of stages solved,
being 100% when a road is completely solved, and 0% when no stage is solved.</p>
<p><b>Grade from duration of the hunt</b></p>
<p>The hunter who ends the road in less time wins the hunt and marks the best time.
The time is measured from the moment in which the starting stage of the road is unlocked.
This means that the participants can play at different moments.
The grade is calculated by interpolating the finishing time being 50% the end time of the hunt
and 100% the best finishing time. The players that did not finish the
hunt receive a grade under 50 calculated just by the number of stages solved.</p>
<p><b>Grade from finishing time</b></p>
<p>The hunter who ends first is the winner of the hunt.
It is assumed that every hunter plays simultaneously.
The grade is calculated by interpolating the finishing time being 50% the end time of the hunt
and 100% the best finishing time. The players that did not finish the
hunt receive a grade under 50 calculated just by the number of stages solved.</p>
<p><b>Grade from position</b></p>
<p>The score is calculated by interpolating the position in the ranking,
being 100% the score for the first player and 50% for the last player.
The players that did not finish the hunt receive a grade under 50% calculated
just by the number of stages solved.</p>';
$string['grademethodinfo'] = 'Grading method: {$a->type}. Location penalization: {$a->gradepenlocation}%. Answer penalization: {$a->gradepenanswer}%';
$string['gradepenanswer'] = 'Penalty for failure in answer';
$string['gradepenlocation'] = 'Penalty for failure in location';
$string['gradepenlocation_help'] = 'Penalization is expressed in % of the grade.
Per example, if penalization is 5.4, a player with 3 failures will penalize
his grade by 16.2%, that is, will receive 83.8% of the grade calculated by the rest of the criteria.';
$string['gradesdeleted'] = 'Treasure hunt grades deleted';
$string['gradingsummary'] = 'Grading summary';
$string['group'] = 'Group';
$string['groupactivityovercome'] = 'Activity for stage {$a->position} successfully completed by {$a->user}  {$a->date}';
$string['groupid'] = 'Group assigned to the road';
$string['groupid_help'] = 'Users in this group are assigned to this road when the game starts.
If there is only one road and the selected option is "none", all participants in the activity will play for it';
$string['groupingid'] = 'Grouping assigned to the road';
$string['groupingid_help'] = 'Groups in this grouping are assigned to this road when the game starts';
$string['groupinvalidroad'] = '{$a} has assigned an invalid road.';
$string['grouplocationfailed'] = '<b>Failed "stage {$a->position}" location</b> by {$a->user} of  {$a->date}';
$string['grouplocationovercome'] = '<b>Successful stage {$a->position} location</b> by {$a->user} {$a->date}';
$string['groupmode'] = 'Students play in groups';
$string['groupmode_help'] = 'If enabled students will be divided into groups based on the configuration of course groups.
Every team-member can solve the current stage and the progress is common to every partner. <br/>
This allows to “parallelize” the hunt and cover more territory. The participants see the same information but team-oriented.';
$string['groupmultipleroads'] = '{$a} has more than one road assigned.';
$string['groupquestionfailed'] = '<b>Failed  stage {$a->position} answer</b> by {$a->user}  {$a->date}';
$string['groupquestionovercome'] = '<b>Successful stage {$a->position} answer</b> by {$a->user}  {$a->date}';
$string['groups'] = 'Groups';
$string['groupstageovercome'] = '<b>Stage {$a->position} overcome</b> by {$a->user} {$a->date}';
$string['hello'] = 'Hello';
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
$string['lockedaqclue'] = 'You must perform the activity \'<strong>{$a}</strong>\' and correctly answer the following
question to unlock the clue';
$string['lockedqclue'] = 'You must correctly answer the following question to unlock the clue';
$string['locktimeediting'] = 'Lock time editing';
$string['locktimeediting_help'] = 'Time in seconds for which a user can edit an instance without renewing the lock.
The larger, fewer requests for renewal lock must be made, but more time is locked the edit page once the user has finished.
It must be greater than 5 seconds, but the time will be set by default.';
$string['mapview'] = 'Map view';
$string['modify'] = 'Modify';
$string['modulename'] = 'Treasure Hunt';
$string['modulename_help'] = 'This module will be used to perform an activity geolocation.
Outdoor, indoor and virtual-map treasure-chases with geolocation and QR codes.
This module for Moodle allows to organize outdoor-serious-games with your students.
TreasureHunt implements a browser-based play application (no need to install any native app) and a geographical editor for encoding the stages of the game.
The game can be configured with a range of options that make the module to be very flexible and useful in many situations: individual/team,
moving/desktop-marking, scoring from time, position, completion, etc.
<p><b><a href = "https://juacas.github.io/moodle-mod_treasurehunt/index.html">More info and step-by-step tutorial in this online presentation.</a></b></p>';
$string['modulenameplural'] = 'Treasure Hunts';
$string['movingplay'] = 'Moving play';
$string['multiplegroupingsplay'] = 'Your group has assigned more than one road, so you can not play the activity.';
$string['multiplegroupsplay'] = 'You have assigned more than one road, so you can not play the activity.';
$string['multiplegroupssameroadplay'] = 'You belong to more than one group assigned to the same road, so you can not play the activity.';
$string['multipleteamsplay'] = 'Member of more than one group, so unable to make the activity.';
$string['mustanswerquestion'] = 'You must correctly answer the question before continuing';
$string['mustcompleteactivity'] = 'You must overcome the activity to complete before continuing';
$string['mustcompleteboth'] = 'You must answer the question correctly and overcome the activity to complete before continuing';
$string['nextcamera'] = 'Change camera';
$string['noanswerselected'] = 'You must select an answer';
$string['noattempts'] = 'You have not made any attempt';
$string['noexsitsstage'] = 'There is no stage number {$a} in the database. Reload the page';
$string['nogroupassigned'] = 'No group assigned to this road';
$string['nogroupingplay'] = 'You have no group assigned to a road, so you can not play the activity.';
$string['nogroupplay'] = 'You have no road assigned, so you can not play the activity.';
$string['nogrouproad'] = '{$a} has no road assigned.';
$string['nomarks'] = 'First mark on the map the desired point. Place the <img src="pix/my_location.png" width="28"/>';
$string['nomarksmobile'] = 'First mark on the map the desired point.';
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
$string['nousersprogress'] = 'No user / group has progress on this road.';
$string['outoftime'] = 'Out of time';
$string['overcomefirststage'] = 'To discover the first stage you should start from the marked area on the map';
$string['overlaylayers'] = 'Overlay layers';
$string['play'] = 'Play';
$string['playstagewithoutmoving'] = 'Discover stage without moving';
$string['playstagewithoutmoving_help'] = 'If this option is enabled, students can discover this stage without moving to any place.
To do this, every time the student takes a simple click on the map a mark is created, erasing the previous
if any, indicating the last desired point. Upon completion of the stage, the game will change to the default
settings of the activity';
$string['playstagewithqr'] = 'Discover stage by reading this QR text';
$string['playstagewithqr_help'] = 'If this option has a value, students can discover this stage by scanning a QR code available at that location.';

$string['playwithoutmoving'] = 'Playing without moving';
$string['playwithoutmoving_help'] = 'If this option is enabled, students may play from their computers without moving to
places. To do this, every time the student takes a simple click on the map a mark is created, erasing the previous
if any, indicating the last desired point.';
$string['pluginadministration'] = 'Treasure hunt administration';
$string['pluginname'] = 'Treasure Hunt';
$string['qrreaded'] = 'QR code readed:';
$string['question'] = 'Question';
$string['remove'] = 'Delete';
$string['removealltreasurehuntattempts'] = 'Delete all treasure hunts attempts';
$string['removedactivitytoend'] = 'Activity to complete has been removed';
$string['removedquestion'] = 'The question has been removed';
$string['removeroadwarning'] = 'If you remove the road all associated stages were removed and you can no longer recover';
$string['removewarning'] = 'If you remove it you can not retrieve it';
$string['restrictionsdiscoverstage'] = 'Restrictions to discover stage';
$string['reviewofplay'] = 'Review of play';
$string['road'] = 'Road';
$string['roadmap'] = 'Road';
$string['roadended'] = 'This road is complete. Congratulations! You have done the treasure hunt. You can check your history in the map.';
$string['roadname'] = 'Road\'s name';
$string['roadview'] = 'Road';
$string['save'] = 'Save';
$string['saveemptyridle'] = 'All modified stages must have geometry before saving';
$string['savewarning'] = 'You have not saved changes.';
$string['scanQR_scanbutton'] = 'Scan QRCode';
$string['scanQR_generatebutton'] = 'Generate a QRCode';
$string['search'] = 'Search';
$string['searching'] = 'Searching';
$string['searchlocation'] = 'Search location';
$string['send'] = 'Send';
$string['sendlocationcontent'] = 'This action can not be undone.';
$string['sendlocationtitle'] = 'Are you sure you want to send this location?';
$string['showclue'] = 'Show clue';
$string['stage'] = 'Stage';
$string['stageclue'] = 'Clue to locate the next stage';
$string['stageclue_help'] = 'Here you should describe the clue to reach the next location.
In case it is the last stage, must leave a feedback message indicating that the treasure hunt has ended.';
$string['stagename'] = 'Stage\'s name';
$string['stageovercome'] = 'Stage overcome';
$string['stages'] = 'Stages';
$string['start'] = 'Start';
$string['startfromhere'] = 'You can only start from here';
$string['state'] = 'State';
$string['successlocation'] = 'It is the right place!';
$string['timeexceeded'] = 'You have exceeded the time limit for the activity. This screen only serves to review the game';
$string['totalprogress'] = 'Total progress';
$string['totaltime'] = 'Total time';
$string['timeago'] = '{$a->shortduration} ago';
$string['timeat'] = 'at {$a->date}';
$string['timeagolong'] = '{$a->shortduration} ago ({$a->date})';
$string['timetocome'] = 'in {$a->shortduration}';
$string['timetocomelong'] = 'in {$a->shortduration} ({$a->date})';
$string['trackusers'] = 'Track trajectories';
$string['trackusers_help'] = 'Register the paths made by the users. They can be seen in the “Track viewer” screen.<br/>
The user positions are logged between validation attempts (with every poll request).<br/>
If the user has his GPS disabled then the only location he can report is that of the scanned QR-Codes.<br/>
If this option is <b>disabled</b>, the only locations recorded are those of the validation attempts.';
$string['trackviewer'] = 'Track Viewer';
$string['trackviewerrefreshtracks'] = 'Refresh tracks each {$a} seconds.';
$string['treasurehunt'] = 'Treasure hunt';
$string['treasurehunt:addinstance'] = 'Add a new treasurehunt';
$string['treasurehunt:addroad'] = 'Add road';
$string['treasurehunt:addstage'] = 'Add stage';
$string['treasurehuntclosed'] = 'This treasure hunt closed {$a}';
$string['treasurehuntcloses'] = 'Treasure hunt closes';
$string['treasurehuntcloseson'] = 'This treasure hunt will close {$a}';
$string['treasurehunt:editroad'] = 'Edit road';
$string['treasurehunt:editstage'] = 'Edit stage';
$string['treasurehuntislocked'] = '{$a} is editing this treasurehunt right now. Try to edit it in a few minutes.';
$string['treasurehunt:managetreasure'] = 'Manage treasurehunt';
$string['treasurehunt:managetreasurehunt'] = 'Manage treasurehunt';
$string['treasurehuntname'] = 'Treasure hunt\'s name';
$string['treasurehuntnotavailable'] = 'The treasure hunt will be available {$a}';
$string['treasurehuntopens'] = 'Treasure hunt opens';
$string['treasurehuntopenedon'] = 'This treasure hunt opened {$a}';
$string['treasurehunt:play'] = 'Play';
$string['treasurehunt:view'] = 'View treasurehunt';
$string['treasurehunt:viewusershistoricalattempts'] = 'View users attempt history';
$string['updates'] = 'Updates';
$string['updatetimes'] = 'Update times';
$string['user'] = 'User';
$string['useractivityovercome'] = '<b>Moodle activity for "stage {$a->position}" successfully completed</b> {$a->date}';
$string['userattempthistory'] = 'Attempt history of {$a}';
$string['userinvalidroad'] = '{$a} has assigned an invalid road.';
$string['userlocationfailed'] = '<b>Failed "stage {$a->position}" location</b> {$a->date}';
$string['userlocationovercome'] = '<b>Successful "stage {$a->position}" location</b> {$a->date}';
$string['usermultipleroads'] = '{$a} has more than one road assigned.';
$string['usermultiplesameroad'] = '{$a} belong to more than one group assigned to the same road.';
$string['userprogress'] = 'User progress successfully updated';
$string['userquestionfailed'] = '<b>Failed "stage {$a->position}" answer</b> {$a->date}';
$string['userquestionovercome'] = '<b>Successful "stage {$a->position}" answer</b> {$a->date}';
$string['usersprogress'] = 'User progress';
$string['usersprogress_help'] = 'Indicates the progress of the stages of each student / group according to the colors:
<p>The color <b> green </b> indicates that the stage has been overcome without failures.</p>
<p>The color <b> yellow </b> indicates that the stage has been oavercome with failures.</p>
<p>The color <b> red </b> indicates that the stage has not been overcome and failures have been made.</p>
<p>The color <b> grey </b> indicates that the stage has not been overcome and no failures have been made.</p>';
$string['userstageovercome'] = '<b>Stage {$a->position} overcome</b>: {$a->date}';
$string['validatelocation'] = 'Validate location';
$string['validateqr'] = 'Scan QR';
$string['warmatchanswer'] = 'The answer does not match the question';
$string['warnqrscanner'] = '<table><tr><td> This Treasurehunt includes {$a} stages with QRCodes.
Please be sure your device can scan codes from the web browser. A view of your cam should be appeared bellow. Try to read any qrcode
like this.</td><td> <a href="pix/qr.png">
 <img src="pix/qr.png" align="top" width="100"></a></td></tr></table>';
$string['warnqrscannersuccess'] = 'This Treasurehunt includes {$a} stages with QRCodes.
It seems that you have passed a QR test with this device.';
$string['warnqrscannererror'] = 'This Treasurehunt includes {$a} stages with QRCodes.
It seems that your device can not use the camera with this application. Please give permissions to access the camera.
If you can\'t manage to activate the camera this device may not be suitable to play the Treasurehunt.';
$string['warnunsecuregeolocation'] = 'Geolocation may not work in your server. This is a <b>SEVERE misconfiguration</b> caused by your
server configuration. Geolocation is forbidden for non-Secure servers that use HTTP instead of HTTPS. In order to use the GPS of
to locate the students during the Treasurehunt the server must be accessed by secure HTTPS URLs. In other case, only "Play without moving" mode
can be used and the players need to point manually each stage on the map.
Please contact your administrator.
(References <a href="https://www.chromestatus.com/feature/5636088701911040">Chrome</a>, <a href="https://blog.mozilla.org/security/2015/04/30/deprecating-non-secure-http/">Firefox</a>)';
$string['warnusersgroup'] = 'The following users belong to more than one group: {$a}, so are unable to play the activity.';
$string['warnusersgrouping'] = 'The following groups belong to more than one grouping: {$a}, so are unable to play the activity.';
$string['warnusersoutside'] = 'The following users do not belong to any group/grouping: {$a}, so are unable to play the activity.';

// Initial tour help.
$string['addstage_tour'] = 'Each road must have two or more stages. Each stage gives a clue to find out the next.';
$string['addroad_tour'] = 'Add one or more roads to be followed by your students.';
$string['editend_tour'] = 'Enjoy making exciting games for your students!';
$string['map_tour'] = 'In this map you can manage all the components of a funny geolocated game!';
$string['remove_tour'] = 'You can delete parts of the locations geometries. Just select a polygon and then press this button.';
$string['roads_tour'] = 'In this area you will find the different roads of your game. Select one of them to edit the stages.';
$string['save_tour'] = 'After drawing your locations, don\'t forget to save your changes.';
$string['searchlocation_tour'] = 'With this search area you can find your way rapidly';
$string['stages_tour'] = 'In this area you will find the stages of the selected road. Select each stage to zoom to the actual location in the map and begin to edit its geometries by clicking on them and the Edit/Draw buttons above.';
$string['welcome_edit_tour'] = 'Welcome to the authoring page of TreasureHunt. ';
$string['welcome_play_tour'] = '<span style="font-size: 1.5em; font-weight: bold">Welcome to your Treasure Hunt!</span><br>This map and the clues will be your guide.';
$string['bigbutton_play_tour'] = 'This is your best friend.<br>A click and you are shown <b>challenges</b> or valuable <b>hints</b>.';
$string['autolocate_tour'] = 'Show your <b>current location</b>.<br>(give permissions to use "location" when asked)';
$string['playerhelp_tour'] = 'This tour can be reviewed whenever you want.';
$string['validatelocation_tour'] = 'Confident about the location of a stage?<br><b>Submit your position</b> and discover if you are correct.';
$string['lastsuccessfulstage_tour'] = 'In this panel you can find out your last successfull stage. It can be yours of your group\'s successfull stage.';
$string['mapplay_tour'] = 'The <b>map</b> shows you all your attempts!<br>Successful ones: <img src="pix/success_mark.png" width="28"/><br>Failed ones: <img src="pix/failure_mark.png" width="28"/>';
$string['mapplaymobile_tour'] = 'The <b>map</b> shows you all your attempts!<br>Successful ones: <img src="{$a->successurl}" width="28"/><br>Failed ones: <img src="{$a->failureurl}" width="28"/>';
$string['playend_tour'] = '<span style="font-size: 1.5em; font-weight: bold">Enjoy your Treasure Hunt</span><br>with your mates!';

$string['nextstep'] = 'Next';
$string['prevstep'] = 'Prev';
$string['skiptutorial'] = 'Quit';
$string['donetutorial'] = 'End';
// Privacy strings.
$string['privacy:metadata_treasurehunt_track'] = 'The treasure hunt stores the sequence of locations followed by a user during the activity.';
$string['privacy:metadata_treasurehunt_track_userid'] = 'The ID of the user being tracked.';
$string['privacy:metadata_treasurehunt_track_treasurehuntid'] = 'The ID of the Treasure hunt the user is playing in.';
$string['privacy:metadata_treasurehunt_track_location'] = 'The location of the user at a particular time.';
$string['privacy:metadata_treasurehunt_track_timestamp'] = 'The time the user is tracked at.';

$string['privacy:metadata_treasurehunt_attempts'] = 'The treasure hunt stores the type, time and location of the attempts, successes and failures of the users during the activity';
$string['privacy:metadata_treasurehunt_attempts_userid'] = 'The ID of the user that made an attempt.';
$string['privacy:metadata_treasurehunt_attempts_timecreated'] = 'The time at which the user made an attempt.';
$string['privacy:metadata_treasurehunt_attempts_groupid'] = 'The group in which the user played the activity.';
$string['privacy:metadata_treasurehunt_attempts_stageid'] = 'The stage ID the user was trying to solve.';
