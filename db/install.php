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
 * Provides code to be executed during the module installation
 *
 * This file replaces the legacy STATEMENTS section in db/install.xml,
 * lib.php/modulename_install() post installation hook and partially defaults.php.
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name <your@email.adress>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_scavengerhunt_install() {
    global $DB;
    $dbman = $DB->get_manager();
    $table1 = new xmldb_table('current_scavengerhunt');
    $table2 = new xmldb_table('scavengerhunt_riddle');
    //Compruebo si existe la base de datos con la tabla
    if($dbman->table_exists($table1) && $dbman->table_exists($table2))
    {
        //switch($DB->get_dbfamily() o get_dbvendor()) ... Y pongo según la base de datos que sea
        //Create multipolygon. change_database_structure no permite poner la tabla entre corchetes
        //Method on moodle/lib/dml/database_native_moodle_database
        $DB->change_database_structure('ALTER TABLE mdl_scavengerhunt_riddle ADD geom MULTIPOLYGON NOT NULL');
        //Create points
        $DB->change_database_structure('ALTER TABLE mdl_current_scavengerhunt ADD locations POINT NOT NULL');

    }

}

/**
 * Post installation recovery procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_scavengerhunt_install_recovery() {
}
