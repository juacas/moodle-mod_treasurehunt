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
 * English strings for scavengerhunt
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Caza del tesoro';
$string['modulenameplural'] = 'Cazas del tesoro';
$string['modulename_help'] = 'Este módulo servirá para realizar una actividad de geolocalización';
$string['riddlename'] = 'Nombre de la pista';
$string['roadname'] = 'Nombre del camino';
$string['successlocation'] = '¡¡¡Enhorabuena, has acertado!!!';
$string['faillocation'] = 'No es el lugar correcto';
$string['continue'] = 'Continuar';
$string['user'] = 'Usuario';
$string['group'] = 'Grupo';
$string['start'] = 'Empezar';
$string['nogroupassigned'] = 'Ningún grupo asignado a este camino';
$string['nouserassigned'] = 'Ningún usuario asignado a este camino';
$string['overcomefirstriddle'] = 'Para descubrir la primera pista debes comenzar desde el área marcada en el mapa';
$string['noroads'] = 'Todavía no se han añadido caminos';
$string['attempt'] = 'Intento';
$string['historicalattempts'] = 'Historial de intentos';
$string['aerialview'] = 'Aérea';
$string['roadview'] = 'Callejero';
$string['mapview'] = 'Vista del mapa';
$string['ost'] = 'Open Street Maps';
$string['noattempts'] = 'No has realizado ningún intento';
$string['state'] = 'Estado';
$string['play'] = 'Jugar';
$string['updates'] = 'Actualizaciones';
$string['usersprogress'] = 'Progreso de los usuarios';
$string['nomarks'] = 'Marca primero en el mapa el punto deseado.';
$string['noresults'] = 'No se han encontrado resultados.';
$string['startfromhere'] = 'Solo se puede empezar desde aquí';
$string['userattemptovercome'] = 'Pista {$a->num_riddle} descubierta en la fecha: {$a->date}';
$string['userattemptfailed'] = 'Localización fallida para la pista {$a->num_riddle} en la fecha: {$a->date}';
$string['groupattemptovercome'] = 'Pista {$a->num_riddle} descubierta por {$a->user} en la fecha: {$a->date}';
$string['groupattemptfailed'] = 'Localización fallida por {$a->user} para la pista {$a->num_riddle} en la fecha: {$a->date}';
$string['lockedriddle'] = 'Debes realizar la actividad tal para desbloquear la pista';
$string['scavengerhuntname'] = 'Nombre de la caza del tesoro';
$string['scavengerhuntname_help'] = 'Este es el contenido asociado al nombre. Soporta barra baja.';
$string['scavengerhunt'] = 'Scavenger hunt';
$string['noscavengerhunts'] = 'A ver que sale D:';
$string['pluginadministration'] = 'Administración de la caza del tesoro';
$string['pluginname'] = 'Caza del tesoro';
$string['question_scavengerhunt'] = '¿Esto funciona?';
$string['hello'] = 'Hola';
$string['question'] = 'Pregunta';
$string['welcome'] = 'Bienvenido a mi módulo de caza del tesoro, espero que lo disfrutes';
$string['addsimplequestion'] = 'Añadir pregunta simple';
$string['addsimplequestion_help'] = 'Permite aññadir una pregunta sencilla antes de superar la pista con éxito';
$string['insert_road'] = 'Insertar nuevo camino';
$string['insert_riddle'] = 'Insertar nueva pista';
$string['confirm_delete_riddle'] = 'Se eliminaron las pistas correctamente';
$string['saveemptyridle'] = 'Todas las pistas modificadas deben tener geometría antes de guardar';
$string['empty_ridle'] = 'La pista no tiene ninguna geometría asociada. Debe introducir una para que el camino se pueda realizar';
$string['eventriddleupdated'] = 'La pista ha sido actualizada';
$string['eventriddlecreated'] = 'La pista ha sido creada';
$string['eventriddledeleted'] = 'La pista ha sido eliminada';
$string['eventroadupdated'] = 'El camino ha sido actualizado';
$string['eventroadcreated'] = 'El camino ha sido creado';
$string['eventroaddeleted'] = 'El camino ha sido eliminado';
$string['scavengerhunt:managescavenger'] = 'Administrar Caza del tesoro';
$string['scavengerhunt:view'] = 'Ver la caza del tesoro';
$string['scavengerhunt:addinstance'] = 'Añadir nueva caza del tesoro';
$string['scavengerhuntislocked'] = '{$a} está editando esta caza del tesoro. Intenta editarla dentro de unos minutos.';
$string['availability'] = 'Disponibilidad';
$string['overcomeriddlerestrictions'] = 'Restricciones para superar la pista';
$string['groups'] = 'Grupos';
$string['editscavengerhunt'] = 'Editar caza del tesoro';
$string['gradingsummary'] = 'Sumario de calificaciones';
$string['groupmode'] = 'Juego en grupos';
$string['groupmode_help'] = 'Si está habilitado los estudiantes se dividirán en grupos en función de la configuración por defecto de los grupos o de una agrupación personalizada para cada camino. El juego del grupo será compartido entre los miembros del grupo y todos los miembros del grupo verán los cambios producidos en el juego.';
$string['allowsubmissionsfromdate'] = 'Permitir entregas desde';
$string['allowsubmissionsfromdate_help'] = 'Si está habilitado, los estudiantes no podrán hacer entregas antes de esta fecha. Si está deshabilitado, los estudiantes podrán comenzar las entregas de inmediato.';
$string['duedatereached'] = 'La fecha de vencimiento de esta tarea ya ha pasado';
$string['cutoffdate'] = 'Fecha límite';
$string['cutoffdatefromdatevalidation'] = 'La fecha límite debe ser posterior de la de inicio.';
$string['cutoffdate_help'] = 'Si se activa la opción, no se aceptarán entregas de tareas después de esta fecha sin una ampliación.';
$string['alwaysshowdescription'] = 'Mostrar siempre la descripción';
$string['alwaysshowdescription_help'] = 'Si está deshabilitado, la Descripción de la Tarea superior solo será visible para los estudiantes en la fecha "Permitir entregas desde",';
/**Template */
$string['sendlotacion_title'] = '¿Estás seguro de que deseas enviar esta ubicación?';
$string['sendlotacion_content'] = 'Esta acción no se puede deshacer.';
$string['cancel'] = 'Cancelar';
$string['send'] = 'Enviar';
$string['exit'] = 'Salir';
$string['back'] = 'Atrás';
$string['layers'] = 'Layers';
$string['searching'] = 'Buscando';
$string['discoveredriddle'] = 'Pista descubierta';
$string['failedlocation'] = 'Localización fallada';
$string['riddledescription'] = 'Descripción de la pista';
$string['info_validate_location'] = 'Validar ubicación para esta pista';
$string['button_validate_location'] = 'Validar ubicación';
$string['search'] = 'Buscar';
$string['info'] = 'Información';
$string['riddles'] = 'Pistas';
$string['playwithoutmove'] = 'Jugar sin desplazarse';
$string['playwithoutmove_help'] = 'Si esta opción se habilita los alumnos podrán jugar sin desplazarse a los lugares. Se habilita una marca en el mapa para seleccionar el punto deseado';
$string['groupid'] = 'Grupo asignado al camino';
$string['groupid_help'] = 'Los usuarios de este grupo son asignados a este camino cuando empieza el juego. Si sólo existe un camino y la opción seleccionada es "ninguno", todos los participantes de la actividad jugarán por él';
$string['groupingid'] = 'Agrupación asignada al camino';
$string['groupingid_help'] = 'Los grupos de esta agrupación son asignados a este camino cuando empieza el juego';
$string['activitytoend'] = 'Completar antes la actividad seleccionada';
$string['activitytoend_help'] = 'La actividad seleccionada deberá completarse antes de que se muestre la pista actual. Para que las actividades del curso se muestren en la lista debe estar habilitada la terminación de actividad en la configuración de Moodle, en la configuración del curso y en la propia actividad.';
$string['noteam'] = 'No es miembro de ningún grupo';
$string['noteamplay'] = 'No es miembro de ningún grupo, por lo que no puede realizar la actividad.';
$string['notdeleteriddle'] = 'Ya se han realizado intentos sobre este camino, no puedes eliminar ninguna pista';
$string['invalidassignedroad'] = 'El camino asignado no está validado';
$string['invalidroad'] = 'El camino no está validado';
$string['multipleteamsplay'] = 'Es miembro de más de un grupo, por lo que no puede realizar la actividad.';
$string['timelabelfailed'] = 'Ubicación enviada en la fecha: ';
$string['timelabelsuccess'] = 'Pista descubierta en la fecha: ';
$string['nogroupplay'] = 'No tienes ningún camino asignado, por lo que no puedes jugar la actividad.';
$string['nogroupingplay'] = 'No tienes ningún grupo asignado a un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupsplay'] = 'Tienes asignados más de un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupingsplay'] = 'Tu grupo tiene asignados más de un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupssameroadplay'] = 'Perteneces a más de un grupo asignado al mismo camino, por lo que no puedes jugar la actividad.';
$string['warnusersgrouping'] = 'Algunos usuarios no son ya sea miembros de una agrupación, o son miembros de más de una agrupación, o es un miembro de más de un grupo perteneciente al mismo camino, por lo que no son capaces de jugar la actividad.';
$string['warnusersgroup'] = 'Algunos usuarios no son ya sea miembros de un grupo, o son miembros de más de un grupo, por lo que no son capaces de jugar la actividad.';







