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
 * Function to interoperate with availability API.
 * Developed for availability_treasurehunt in mind.
 * Maybe more.
 *
 * @package    mod_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check if availability_treasurehunt is avilable in the system.
 */
function treasurehunt_availability_available() {
    $config = get_config('availability_treasurehunt');
    if (isset($config->version) && !isset($config->disabled)) {
        return true;
    } else {
        return false;
    }
}
/**
 * Obtiene la lista de actividades del curso.
 * para la stageid especificada, ya sea sola o combinada mediante AND con otras restricciones.
 * Las que tienen aplicada la restricción availability/treasurehunt están marcadas con locked=true.
 *
 * @param int $courseid ID del curso
 * @param int $stageid ID de la etapa del treasurehunt
 * @return array Array de objetos con información de las actividades
 */
function treasurehunt_get_activities_with_stage_restriction($courseid, $stageid) {
    // Obtener información del curso
    $modinfo = get_fast_modinfo($courseid);
    
    $matching_activities = [];
    
    // Iterar sobre todas las actividades del curso
    foreach ($modinfo->get_cms() as $cm) {
        $modinfo = treasurehunt_get_activity_info_from_cm($cm);
        // Verificar si la actividad tiene restricciones de disponibilidad
        if ($cm->availability) {
            // Decodificar el JSON de availability
            $availability = json_decode($cm->availability, true);
            
            if ($availability && isset($availability['c'])) {
                // Buscar la restricción treasurehunt en las condiciones
                if (treasurehunt_check_stage_restriction($availability['c'], $stageid)) {
                    $modinfo->locked = true;
                }
            }
        }
        $matching_activities[] = $modinfo;
    }
    
    return $matching_activities;
}
/**
 * Obtiene información de una actividad desde el objeto cm_info
 *
 * @param cm_info $cm Objeto cm_info de Moodle
 * @return object Objeto con información completa de la actividad
 */
function treasurehunt_get_activity_info_from_cm($cm) {
    $activity_info = new stdClass();
    $activity_info->cmid = $cm->id;
    $activity_info->course = $cm->course;
    $activity_info->module = $cm->module;
    $activity_info->instance = $cm->instance;
    $activity_info->modulename = $cm->modname;
    $activity_info->name = $cm->name;
    $activity_info->availability = $cm->availability;
    $activity_info->url = $cm->url;
    $activity_info->visible = $cm->visible;
    $activity_info->uservisible = $cm->uservisible;
    $activity_info->locked = false;
    
    return $activity_info;
}
/**
 * Función auxiliar para verificar si una stageid está presente en las restricciones
 *
 * @param array $conditions Array de condiciones de availability
 * @param int $stageid ID de la etapa a buscar
 * @return bool True si encuentra la restricción con la stageid
 */
function treasurehunt_check_stage_restriction($conditions, $stageid) {
    foreach ($conditions as $condition) {
        // Verificar si es una restricción treasurehunt
        if (isset($condition['type']) && $condition['type'] === 'treasurehunt') {
            if (isset($condition['stageid']) && $condition['stageid'] == $stageid) {
                return true;
            }
        }
        
        // Si hay condiciones anidadas (operadores AND/OR), verificar recursivamente
        if (isset($condition['c']) && is_array($condition['c'])) {
            if (treasurehunt_check_stage_restriction($condition['c'], $stageid)) {
                return true;
            }
        }
    }
    
    return false;
}
/**
 * Añade una restricción treasurehunt a las restricciones existentes
 *
 * @param course_modinfo $cm course module
 * @param stdClass $stageid record etapa
 * @param bool $replace Si reemplazar todas las restricciones existentes
 * @return string JSON actualizado de availability
 */
function treasurehunt_add_restriction($cm, $stage, $treasurehuntid, $replace = false) {
    $current_availability = $cm->availability;
    $new_restriction = [
        'treasurehuntid'=> $treasurehuntid,
        'type' => 'treasurehunt',
        'conditiontype' => 'current_stage',
        'requiredvalue' => 0,
        'stageid' => $stage->id
    ];
    
    if ($replace || empty($current_availability)) {
        // Crear nueva estructura de availability.
        $availability = [
            'op' => '&',
            'c' => [$new_restriction],
            'showc' => [true]
        ];
    } else {
        // Decodificar availability existente
        $availability = json_decode($current_availability, true);
        
        if (!$availability || !isset($availability['c'])) {
            // Si no hay estructura válida, crear nueva
            $availability = [
                'op' => '&',
                'c' => [$new_restriction],
                'showc' => [true]
            ];
        } else {
            // Verificar si ya existe esta restricción
            if (!treasurehunt_check_stage_restriction($availability['c'], $stage->id)) {
                // Añadir la nueva restricción
                $availability['c'][] = $new_restriction;
                $availability['showc'][]= true;
            }
        }
    }
    
    return json_encode($availability);
}

/**
 * Elimina una restricción treasurehunt específica
 *
 * @param course_modinfo $cm to edit
 * @param stdClass $stage record etapa a eliminar
 * @return string JSON actualizado de availability
 */
function treasurehunt_remove_restriction($cm, $stage) {
    if (empty($cm->availability)) {
        return null;
    }
    
    $availability = json_decode($cm->availability, true);
    
    if (!$availability || !isset($availability['c'])) {
        return $cm->availability;
    }
    
    // Filtrar las restricciones para eliminar la que coincida con stageid.
    $conditions =  treasurehunt_filter_restrictions($availability['c'], $stage->id);
    $availability['c'] = $conditions;
    // Si no quedan restricciones, retornar null
    if (empty($availability['c'])) {
        return null;
    }
    // Calculate showc array.
    $availability['showc'] = array_fill(0, count($conditions), true);
    return json_encode($availability);
}

function treasurehunt_update_activity_availability($cm, $new_availability) {
    global $DB;
    // Clear cache.
    $courseid = $cm->get_course()->id;
        // Usar la API de Moodle para actualizar la disponibilidad
        try {
            // Actualizar usando la DB
            $DB->set_field('course_modules', 'availability',$new_availability, ['id'=> $cm->id]);
            
            // Invalidar caché del curso
            rebuild_course_cache($courseid, true);
            
            return true;
        } catch (Exception $e) {
            debugging('Error updating availability: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
}


/**
 * Filtra recursivamente las restricciones para eliminar la treasurehunt específica
 *
 * @param array $conditions Array de condiciones
 * @param int $stageid ID de la etapa a eliminar
 * @return array Array filtrado de condiciones
 */
function treasurehunt_filter_restrictions($conditions, $stageid) {
    $filtered = [];
    
    foreach ($conditions as $condition) {
        // Si es una restricción treasurehunt con el stageid específico, la saltamos
        if (isset($condition['type']) && $condition['type'] === 'treasurehunt' && 
            isset($condition['stageid']) && $condition['stageid'] == $stageid) {
            continue;
        }
        
        // Si hay condiciones anidadas, filtrar recursivamente
        if (isset($condition['c']) && is_array($condition['c'])) {
            $condition['c'] = treasurehunt_filter_restrictions($condition['c'], $stageid);
            // Solo mantener si quedan condiciones anidadas
            if (!empty($condition['c'])) {
                $filtered[] = $condition;
            }
        } else {
            // Mantener otras restricciones
            $filtered[] = $condition;
        }
    }
    
    return $filtered;
}