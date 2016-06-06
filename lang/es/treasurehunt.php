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

$string['modulename'] = 'Caza del tesoro';
$string['modulenameplural'] = 'Cazas del tesoro';
$string['modulename_help'] = 'Este módulo servirá para realizar una actividad de geolocalización';
$string['riddlename'] = 'Nombre de la pista';
$string['roadname'] = 'Nombre del camino';
$string['successlocation'] = '¡Es el lugar correcto!';
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
$string['historicalattempts'] = 'Historial de intentos de {$a}';
$string['aerialview'] = 'Aérea';
$string['roadview'] = 'Callejero';
$string['mapview'] = 'Vista del mapa';
$string['ost'] = 'Open Street Maps';
$string['noattempts'] = 'No has realizado ningún intento';
$string['nouserattempts'] = '{$a} no ha realizado ningún intento';
$string['state'] = 'Estado';
$string['play'] = 'Jugar';
$string['reviewofplay'] = 'Revisión del juego';
$string['treasurehuntclosed'] = 'Esta caza del tesoro está cerrada desde el {$a}';
$string['treasurehuntcloseson'] = 'Esta caza del tesoro cerrará el {$a}';
$string['loading'] = 'Cargando';
$string['updates'] = 'Actualizaciones';
$string['usersprogress'] = 'Progreso de los usuarios';
$string['usersprogress_help'] = 'Indica el progreso de las pistas de cada alumno/grupo en función de los colores: '
        . '<P>El color <B>verde</B> indica que la pista se ha superado sin fallos.</P>'
        . '<P>El color <B>amarillo</B> indica que la pista se ha superado con fallos.</P>'
        . '<P>El color <B>rojo</B> indica que la pista no se ha superado y se han cometido fallos.</P>'
        . '<P>El color <B>gris</B> indica que la pista no se ha superado y no se han cometido fallos.</P>';
$string['nomarks'] = 'Marca primero en el mapa el punto deseado.';
$string['noresults'] = 'No se han encontrado resultados.';
$string['startfromhere'] = 'Solo se puede empezar desde aquí';
$string['userlocationovercome'] = 'Localización encontrada de la pista {$a->number} en la fecha: {$a->date}';
$string['userriddleovercome'] = 'Pista {$a->number} superada en la fecha: {$a->date}';
$string['userlocationfailed'] = 'Localización fallida de la pista {$a->number} en la fecha: {$a->date}';
$string['usercompletionovercome'] = 'Actividad a finalizar completada con éxito para la pista {$a->number} en la fecha: {$a->date}';
$string['userquestionfailed'] = 'Respuesta fallida a la pregunta de la pista {$a->number}  en la fecha: {$a->date}';
$string['userquestionovercome'] = 'Respuesta acertada a la pregunta de la pista {$a->number} en la fecha: {$a->date}';
$string['groupquestionovercome'] = 'Respuesta acertada por {$a->user} a la pregunta de la pista {$a->number} en la fecha: {$a->date}';
$string['groupquestionfailed'] = 'Respuesta fallida por {$a->user} a la pregunta de la pista {$a->number} en la fecha: {$a->date}';
$string['grouplocationovercome'] = 'Localización encontrada por {$a->user} de la pista {$a->number} en la fecha: {$a->date}';
$string['groupriddleovercome'] = 'Pista {$a->number} superada por {$a->user} en la fecha: {$a->date}';
$string['grouplocationfailed'] = 'Localización fallida por {$a->user} de la pista {$a->number} en la fecha: {$a->date}';
$string['groupcompletionovercome'] = 'Actividad a finalizar completada con éxito por {$a->user} para la pista {$a->number} en la fecha: {$a->date}';
$string['lockedriddle'] = 'Pista bloqueada';
$string['lockedcriddle'] = 'Debes realizar la actividad \'<strong>{$a}</strong>\' para desbloquear la pista';
$string['lockedqacriddle'] = 'Debes realizar la actividad \'<strong>{$a}</strong>\' y responder correctamente a la siguiente pregunta para desbloquear la pista';
$string['lockedqriddle'] = 'Debes responder correctamente a la siguiente pregunta para desbloquear la pista';
$string['treasurehuntname'] = 'Nombre de la caza del tesoro';
$string['treasurehuntname_help'] = 'Este es el contenido asociado al nombre. Soporta barra baja.';
$string['treasurehunt'] = 'Treasure hunt';
$string['notreasurehunts'] = 'A ver que sale D:';
$string['pluginadministration'] = 'Administración de la caza del tesoro';
$string['pluginname'] = 'Caza del tesoro';
$string['question_treasurehunt'] = '¿Esto funciona?';
$string['hello'] = 'Hola';
$string['question'] = 'Pregunta';
$string['welcome'] = 'Bienvenido a mi módulo de caza del tesoro, espero que lo disfrutes';
$string['addsimplequestion'] = 'Añadir pregunta simple';
$string['addsimplequestion_help'] = 'Permite añadir una pregunta sencilla antes de mostrar la descripción de esta pista';
$string['insert_road'] = 'Insertar nuevo camino';
$string['insert_riddle'] = 'Insertar nueva pista';
$string['confirm_delete_riddle'] = 'Se eliminaron las pistas correctamente';
$string['saveemptyridle'] = 'Todas las pistas modificadas deben tener geometría antes de guardar';
$string['erremptyriddle'] = 'Todas las pistas deben tener al menos una geometría para que el camino sea válido';
$string['errvalidroad'] = 'Debe existir al menos dos pistas que tengan al menos una geometría para que el camino sea válido';
$string['eventriddleupdated'] = 'La pista ha sido actualizada';
$string['eventriddlecreated'] = 'La pista ha sido creada';
$string['eventriddledeleted'] = 'La pista ha sido eliminada';
$string['eventroadupdated'] = 'El camino ha sido actualizado';
$string['eventroadcreated'] = 'El camino ha sido creado';
$string['eventroaddeleted'] = 'El camino ha sido eliminado';
$string['treasurehunt:managetreasure'] = 'Administrar Caza del tesoro';
$string['treasurehunt:view'] = 'Ver la caza del tesoro';
$string['treasurehunt:addinstance'] = 'Añadir nueva caza del tesoro';
$string['treasurehuntislocked'] = '{$a} está editando esta caza del tesoro. Intenta editarla dentro de unos minutos.';
$string['availability'] = 'Disponibilidad';
$string['restrictionsdiscoverriddle'] = 'Restricciones para descubrir la pista';
$string['groups'] = 'Grupos';
$string['edittreasurehunt'] = 'Editar caza del tesoro';
$string['editingtreasurehunt'] = 'Editando caza del tesoro';
$string['editriddle'] = 'Editar pista';
$string['editingriddle'] = 'Editando pista';
$string['addingriddle'] = 'Añadiendo pista';
$string['editroad'] = 'Editar camino';
$string['editingroad'] = 'Editando camino';
$string['addingroad'] = 'Añadiendo camino';
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
/* * Template */
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
$string['riddledescription'] = 'Descripción para localizar la siguiente pista';
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
$string['noexsitsriddle'] = 'No existe la pista número {$a} en la base de datos. Recargue la página';
$string['noteamplay'] = 'No es miembro de ningún grupo, por lo que no puede realizar la actividad.';
$string['notdeleteriddle'] = 'Ya se han realizado intentos sobre este camino, no puedes eliminar ninguna pista.';
$string['notcreateriddle'] = 'Ya se han realizado intentos sobre este camino, no puedes añadir más pistas.';
$string['notchangeorderriddle'] = 'No puedes cambiar el orden de las pistas una vez que se han realizado intentos sobre el camino.';
$string['invalidassignedroad'] = 'El camino asignado no está validado';
$string['invalroadid'] = 'El camino no está validado';
$string['multipleteamsplay'] = 'Es miembro de más de un grupo, por lo que no puede realizar la actividad.';
$string['timelabelfailed'] = 'Ubicación enviada en la fecha: ';
$string['timelabelsuccess'] = 'Pista descubierta en la fecha: ';
$string['nogroupplay'] = 'No tienes ningún camino asignado, por lo que no puedes jugar la actividad.';
$string['nogroupingplay'] = 'No tienes ningún grupo asignado a un camino, por lo que no puedes jugar la actividad.';
$string['nogrouproad'] = '{$a} no tiene ningún camino asignado.';
$string['groupmultipleroads'] = '{$a} tiene más de un camino asignado.';
$string['groupinvalidroad'] = '{$a} tiene asignado un camino no validado.';
$string['nouserroad'] = '{$a} no tiene ningún camino asignado.';
$string['usermultiplesameroad'] = '{$a} pertenece a más de un grupo asignado al mismo camino.';
$string['usermultipleroads'] = '{$a} tiene más de un camino asignado.';
$string['userinvalidroad'] = '{$a} tiene asignado un camino no validado.';
$string['multiplegroupsplay'] = 'Tienes asignados más de un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupingsplay'] = 'Tu grupo tiene asignados más de un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupssameroadplay'] = 'Perteneces a más de un grupo asignado al mismo camino, por lo que no puedes jugar la actividad.';
$string['warnusersgrouping'] = 'Los siguientes grupos pertenecen a más de una agrupación: {$a}, por lo que no son capaces de jugar la actividad.';
$string['warnusersgroup'] = 'Los siguientes usuarios pertenecen a más de un grupo: {$a}, por lo que no son capaces de jugar la actividad.';
$string['warnusersoutside'] = 'Los siguientes usuarios no pertenecen a ningún grupo/agrupación: {$a}, por lo que no son capaces de jugar la actividad.';
$string['correctanswer'] = 'Respuesta correcta.';
$string['errcorrectsetanswerblank'] = 'Respuesta correcta marcada, pero la respuesta está vacía';
$string['errnocorrectanswers'] = 'Debe haber solo una respuesta correcta';
$string['errcorrectanswers'] = 'Debe seleccionar una respuesta correcta';
$string['erroutoftimeanswer'] = 'No es posible enviar la respuesta, estás fuera del tiempo de entrega';
$string['erroutoftimelocation'] = 'No es posible enviar la localización, estás fuera del tiempo de entrega';
$string['errsendinganswer'] = 'El camino se ha actualizado mientras enviabas la respuesta, vuelvelo a intentar';
$string['errsendinglocation'] = 'El camino se ha actualizado mientras enviabas tu localización, vuelvelo a intentar';
$string['gradefromtime'] = 'Puntuación por tiempo';
$string['gradefromriddles'] = 'Puntuación por pistas';
$string['gradefromposition'] = 'Puntuación por posición';
$string['grademethodinfo'] = 'Método de calificación: {$a}';
$string['backtocourse'] = 'Volver al curso';
$string['grademethod'] = 'Método de calificación';
$string['grademethod_help'] = '<P><B>Puntuación por pistas</B><P>
<UL>
<P>Cada jugador (o equipo) puntua de forma proporcional al número de pistas
resueltas, siendo el 100% de la calificación máxima cuando se ha completado el camino
y 0 cuando no se ha resuelto ninguna pista.</UL>
<P><B>Puntuación por tiempo</B><P>
<UL>
<P>El ganador de la caza es el que marca el mejor tiempo. La calificación se calcula interpolando el tiempo 
de finalización, siendo el 50% de la calificación máxima el peor tiempo de finalización y el 100% el mejor. 
Los jugadores que no terminaron la caza reciben una calificación por debajo del 50% calculado simplemente por el número de pistas resueltas.
</UL>
<P><B>Puntuación por posición</B><P>
<UL>
<P>La puntuación se calcula interpolando la posición en el ranking,
siendo el 100% de la calificación máxima para al primer jugador (o equipo)
en finalizar y 50% para el último jugador. Los jugadores que no terminaron 
la caza reciben una calificación por debajo del 50% calculado simplemente
por el número de pistas resueltas.</UL>';
$string['gradepenlocation'] = 'Penalizar fallo en localización';
$string['gradepenanswer'] = 'Penalizar fallo en respuesta';
$string['gradepenlocation_help'] = 'La penalización es expresada en % de la calificación. '
        . 'Por ejemplo, si la penalización es 5.4, un jugador con 3 fallos penalizará su '
        . 'nota en un 16.2%, es decir, recibirá el 83.8% de la calificación calculada con el resto de criterios.';
$string['errpenalizationexceed'] = 'La penalización no puede ser mayor que 100';
$string['errpenalizationfall'] = 'La penalización no puede ser menor que 0';
$string['errnumeric'] = 'Debe introducir un número decimal válido';
$string['treasurehuntnotavailable'] = 'Esta caza del tesoro no estará disponible hasta el {$a}';
$string['treasurehuntopenedon'] = 'Esta caza del tesoro está abierta desde el {$a}';
$string['treasurehunt:addriddle'] = 'Añadir pista';
$string['treasurehunt:addroad'] = 'Añadir camino';
$string['treasurehunt:editriddle'] = 'Editar pista';
$string['treasurehunt:editroad'] = 'Editar camino';
$string['treasurehunt:gettreasurehunt'] = 'Obtener todas las pistas y caminos de la caza del tesoro';
$string['treasurehunt:managetreasurehunt'] = 'Gestionar caza del tesoro';
$string['treasurehunt:play'] = 'Jugar';




