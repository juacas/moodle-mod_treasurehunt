<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Compatibility
 *
 * @package    mod_treasurehunt
 * @copyright  2023
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
// After Moodle 4.2 external classes were moved to namespace core_external.
if ($CFG->version < 2023042400) {
    require_once($CFG->dirroot . '/lib/externallib.php');
    if (!class_exists(core_external\external_api::class)) {
        class_alias(external_api::class, \core_external\external_api::class);
        class_alias(restricted_context_exception::class, \core_external\restricted_context_exception::class);
        class_alias(external_value::class, \core_external\external_value::class);
        class_alias(external_single_structure::class, \core_external\external_single_structure::class);
        class_alias(external_multiple_structure::class, \core_external\external_multiple_structure::class);
        class_alias(external_function_parameters::class, \core_external\external_function_parameters::class);
        class_alias(external_warnings::class, \core_external\external_warnings::class);
    }

    if (!class_exists(\core_cache\store::class)) {
        class_alias(cache_store::class, \core_cache\store::class);
    }
}
