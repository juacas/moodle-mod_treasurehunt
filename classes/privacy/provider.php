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

namespace mod_treasurehunt\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use moodle_database;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for all user data stored in treasurehunt.
 * @package    mod_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,
    // This plugin does store personal user data.
    \core_privacy\local\metadata\provider,
    // This plugin is a core_user_data_provider.
    \core_privacy\local\request\plugin\provider
{
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'treasurehunt_attempts',
            [
            'userid' => 'privacy:metadata:treasurehunt_attempts:userid',
            'location' => 'privacy:metadata:treasurehunt_attempts:location',
            'timecreated' => 'privacy:metadata:treasurehunt_attempts:timecreated',
            ],
            'privacy:metadata:treasurehunt_attempts'
        );
        $collection->add_database_table(
            'treasurehunt_track',
            [
            'userid' => 'privacy:metadata:treasurehunt_track:userid',
            'location' => 'privacy:metadata:treasurehunt_track:location',
            'timestamp' => 'privacy:metadata:treasurehunt_track:timestamp',
            ],
            'privacy:metadata:treasurehunt_track'
        );

        return $collection;
    }
    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // Fetch all contexts with treasurehunt attempts.
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {treasurehunt} tr ON tr.id = cm.instance
            INNER JOIN {treasurehunt_roads} troad ON tr.id = troad.treasurehuntid
            INNER JOIN {treasurehunt_stages} tstages ON troad.id = tstages.roadid
            INNER JOIN {treasurehunt_attempts} ta ON ta.stageid = tstages.id
                 WHERE ta.userid = :userid";

        $params = [
            'modname'       => 'treasurehunt',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        // Fetch all contexts with treasurehunt tracks.
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {treasurehunt} tr ON tr.id = cm.instance
            INNER JOIN {treasurehunt_track} tracks ON tr.id = tracks.treasurehuntid
                 WHERE tracks.userid = :userid";

        $params = [
            'modname'       => 'treasurehunt',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }
    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        // Fetch all users with treasurehunt tracks.
        $sql = "SELECT DISTINCT tracks.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {treasurehunt} tr ON tr.id = cm.instance
                  JOIN {treasurehunt_track} tracks ON tr.id = tracks.treasurehuntid
                WHERE cm.id = :cmid";

        $params = [
            'cmid'      => $context->instanceid,
            'modname'   => 'treasurehunt',
        ];

        $userlist->add_from_sql('userid', $sql, $params);
        // Fetch all treasurehunt attempts.
        $sql = "SELECT DISTINCT ta.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {treasurehunt} tr ON tr.id = cm.instance
                  JOIN {treasurehunt_roads} troad ON tr.id = troad.treasurehuntid
                  JOIN {treasurehunt_stages} tstages ON troad.id = tstages.roadid
                  JOIN {treasurehunt_attempts} ta ON ta.stageid = tstages.id
                WHERE cm.id = :cmid";

        $params = [
            'cmid'      => $context->instanceid,
            'modname'   => 'treasurehunt',
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist.
     * User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     * @global moodle_database $DB
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        /** @var moodle_database $DB */
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sqlattempts = "SELECT cm.id AS cmid,
                       ta.location as location,
                       ta.timecreated as timestamp,
                       ta.success as success,
                       ta.type as type,
                       tstages.name as stage_name
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {treasurehunt} tr ON tr.id = cm.instance
            INNER JOIN {treasurehunt_roads} troad ON tr.id = troad.treasurehuntid
            INNER JOIN {treasurehunt_stages} tstages ON troad.id = tstages.roadid
            INNER JOIN {treasurehunt_attempts} ta ON ta.stageid = tstages.id
            WHERE c.id {$contextsql}
              AND ta.userid = :userid
              ORDER BY cm.id, ta.timecreated ASC";

        $sqltracks = "SELECT cm.id AS cmid,
                       tracks.location as location,
                       tracks.timestamp as timestamp
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {treasurehunt} tr ON tr.id = cm.instance
            INNER JOIN {treasurehunt_track} tracks ON tr.id = tracks.treasurehuntid
            WHERE c.id {$contextsql}
              AND tracks.userid = :userid
              ORDER BY cm.id, tracks.timestamp ASC";

        $params = ['modname' => 'treasurehunt', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;

        $treasurehuntdata = [];
        $treasurehunttreasurehuntattempts = $DB->get_recordset_sql($sqlattempts, $params);
        $treasurehunttreasurehunttracks = $DB->get_recordset_sql($sqltracks, $params);

        foreach ($treasurehunttreasurehuntattempts as $treasurehuntattempt) {
            $attempt = [
                'stage_name' => $treasurehuntattempt->stage_name,
                'location' => $treasurehuntattempt->location,
                'success' => $treasurehuntattempt->success,
                'timestamp' => \core_privacy\local\request\transform::datetime($treasurehuntattempt->timestamp),
                'type' => $treasurehuntattempt->type,
            ];
            $treasurehuntdata[$treasurehuntattempt->cmid]['attempts'][] = $attempt;
        }
        mtrace('Exporting attempts.');
        $treasurehunttreasurehuntattempts->close();
        mtrace('Exporting trackpoints.');
        foreach ($treasurehunttreasurehunttracks as $track) {
            $trackpoint = [
                'waypointlocation' => $track->location,
                'waypointtimestamp' => $track->timestamp,
            ];
            $treasurehuntdata[$track->cmid]['track'][] = $trackpoint;
        }

        $treasurehunttreasurehunttracks->close();

        foreach ($treasurehuntdata as $cmid => $data) {
            $context = \context_module::instance($cmid);
            self::export_treasurehunt_data_for_user($data, $context, $user);
        }
    }

    /**
     * Export the supplied personal data for a single treasurehunt activity, along with any generic data or area files.
     *
     * @param array $treasurehuntdata the personal data to export for the treasurehunt.
     * @param \context_module $context the context of the treasurehunt.
     * @param \stdClass $user the user record
     */
    protected static function export_treasurehunt_data_for_user(array $treasurehuntdata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the treasurehunt.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with treasurehunt data and write it.
        $contextdata = (object) array_merge((array) $contextdata, $treasurehuntdata);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        /** @var moodle_database $DB */
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('treasurehunt', $context->instanceid);
        $stages = treasurehunt_get_stages($cm->instance, $context);
        $stagesids = array_keys($stages);
        $DB->delete_records_list('treasurehunt_attempts', 'stageid', $stagesids);
        $DB->delete_records('treasurehunt_track', ['treasurehuntid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        /** @var moodle_database $DB */
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('treasurehunt', $context->instanceid);
            if (!$cm) {
                // Only treasurehunt module will be handled.
                continue;
            }

            $stages = treasurehunt_get_stages($cm->instance, $context);
            $stagesids = array_keys($stages);
            [$insql, $inparam] = $DB->get_in_or_equal($stagesids, SQL_PARAMS_NAMED, 'stage');
            $where = "userid = :userid AND stageid $insql";
            $params = ['userid' => $userid] + $inparam;
            $DB->delete_records_select('treasurehunt_attempts', $where, $params);
            $DB->delete_records('treasurehunt_track', ['treasurehuntid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('treasurehunt', $context->instanceid);
        if (!$cm) {
            // Only treasurehunt module will be handled.
            return;
        }
        $userids = $userlist->get_userids();
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $select = "treasurehuntid = :treasurehuntid AND userid $usersql";
        $params = ['treasurehuntid' => $cm->instance] + $userparams;
        $DB->delete_records_select('treasurehunt_track', $select, $params);

        $stages = treasurehunt_get_stages($cm->instance, $context);
        $stagesids = array_keys($stages);
        [$stagesql, $stageparams] = $DB->get_in_or_equal($stagesids, SQL_PARAMS_NAMED);
        $select = "stageid $stagesql AND userid $usersql";
        $params = $stageparams + $userparams;
        $DB->delete_records_select('treasurehunt_attempts', $select, $params);
    }
}
