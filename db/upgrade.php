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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script for the treasurehunt module.
 *
 * @package mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro
 *            <jpdecastro@tel.uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
/**
 * Execute treasurehunt upgrade from the given old version
 *
 * @global moodle_database $DB
 * @param int $oldversion
 * @return bool
 */
function xmldb_treasurehunt_upgrade($oldversion) {
    global $DB;
    /** @var database_manager */
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    if ($oldversion < 2017042000) {
        $table = new xmldb_table('treasurehunt');
        $field = new xmldb_field('tracking', XMLDB_TYPE_INTEGER, 2, true, true, false, 0);
        $dbman->add_field($table, $field);

        $table = new xmldb_table('treasurehunt_track');
        $table->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $table->addField(new xmldb_field('treasurehuntid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL));
        $table->addField(new xmldb_field('stageid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, !XMLDB_NOTNULL));
        $table->addField(new xmldb_field('userid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL));
        $table->addField(new xmldb_field('location', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL));
        $table->addField(new xmldb_field('timestamp', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL));
        $table->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $table->addKey(new xmldb_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']));
        $table->addKey(new xmldb_key('treasurehunt_fk', XMLDB_KEY_FOREIGN, ['treasurehuntid'], 'treasurehunt', ['id']));
        $table->addKey(new xmldb_key('stage_fk', XMLDB_KEY_FOREIGN, ['stageid'], 'treasurehunt_stages', ['id']));
        $table->addIndex(new xmldb_index('timestamp_idx', XMLDB_INDEX_NOTUNIQUE, ['timestamp']));

        $dbman->create_table($table);
        upgrade_mod_savepoint(true, 2017042000, 'treasurehunt');
    }
    if ($oldversion < 2017070100) {
        // Define field qrtext to be added to treasurehunt_stages.
        $table = new xmldb_table('treasurehunt_stages');
        $field = new xmldb_field('qrtext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'questiontexttrust');

        // Conditionally launch add field qrtext.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2017070100, 'treasurehunt');
    }
    if ($oldversion < 2017101904) {
        $table = new xmldb_table('treasurehunt');
        $field = new xmldb_field('custommapconfig', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tracking');

        // Conditionally launch add field custombbox.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2017101904, 'treasurehunt');
    }
    if ($oldversion < 2018022800) {
        $table = new xmldb_table('treasurehunt');
        $field = new xmldb_field('completionfinish', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED,
                                XMLDB_NOTNULL, null, 0, 'custommapconfig');

        // Conditionally launch add field custombbox.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED,
                XMLDB_NOTNULL, null, 0, 'completionfinish');

        // Conditionally launch add field custombbox.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2018022800, 'treasurehunt');
    }
    if ($oldversion < 2020040204) {
        $table = new xmldb_table('treasurehunt_stages');
        $field = new xmldb_field(
            'activitytoend',
            XMLDB_TYPE_INTEGER,
            '10',
            XMLDB_UNSIGNED,
            false,
            null,
            0,
            'playstagewithoutmoving'
        );
        $key = new xmldb_key('activitytoend', XMLDB_KEY_FOREIGN, ['activitytoend'], 'course_modules', ['id']);
        $dbman->drop_key($table, $key);
        $dbman->change_field_default($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->add_key($table, $key);
        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2020040204, 'treasurehunt');
    }
    if ($oldversion < 2020050400) {
        $table = new xmldb_table('treasurehunt');
        $field = new xmldb_field('playerstyle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'classic', 'completionpass');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2020050400, 'treasurehunt');
    }
    if ($oldversion < 2023091100) {
        $table = new xmldb_table('treasurehunt');
        // If true the hunt's board of progress is shown to users regardless the permission.
        $field = new xmldb_field('showboard', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'tracking');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2023091100, 'treasurehunt');
    }
    if ($oldversion < 2025022600) {
        // Change in all records playerstyle from classic to bootstrap.
        $DB->set_field('treasurehunt', 'playerstyle', TREASUREHUNT_PLAYERBOOTSTRAP, ['playerstyle' => TREASUREHUNT_PLAYERCLASSIC]);
        // Treasurehunt savepoint reached.
        upgrade_mod_savepoint(true, 2025022600, 'treasurehunt');
    }
    return true;
}
