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
 * Strings for component 'treasurehunt', language 'es'
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @author Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @author Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['activitytoend'] = 'Completar antes la actividad seleccionada';
$string['activitytoend_help'] = 'La actividad seleccionada deberá completarse antes de que se muestre la pista actual.
Para que las actividades del curso se muestren en la lista debe estar habilitada
la terminación de actividad en la configuración de Moodle, en el curso y en la propia actividad.';
$string['activitytoendovercome'] = 'Actividad \'<strong>{$a}</strong>\' superada';
$string['activitytoendwarning'] = 'Debes completar primero la actividad a resolver';
$string['actnotavailableyet'] = 'La actividad aún no está disponible';
$string['add'] = 'Añadir';
$string['addingroad'] = 'Añadiendo camino';
$string['addingstage'] = 'Añadiendo etapa';
$string['addsimplequestion'] = 'Añadir pregunta simple';
$string['addsimplequestion_help'] = 'Permite añadir una pregunta sencilla antes de mostrar la pista de esta etapa';
$string['aerialmap'] = 'Aéreo';
$string['aerialview'] = 'Aérea';
$string['allowattemptsfromdate'] = 'Permitir intentos desde';
$string['allowattemptsfromdate_help'] = 'Si está habilitado, los estudiantes no podrán jugar antes de esta fecha.
Si está deshabilitado, los estudiantes podrán comenzar a jugar de inmediato.';
$string['alwaysshowdescription'] = 'Mostrar siempre la descripción';
$string['alwaysshowdescription_help'] = 'Si está deshabilitado, la Descripción de la Caza del tesoro superior solo será
visible para los estudiantes en la fecha "Permitir intentos desde".';
$string['answerwarning'] = 'Debes responder primero a la pregunta';
$string['areyousure'] = '¿Estás seguro?';
$string['attempt'] = 'Intento';
$string['attemptsdeleted'] = 'Eliminados intentos de resolver la caza del tesoro';
$string['availability'] = 'Disponibilidad';
$string['back'] = 'Atrás';
$string['backtocourse'] = 'Volver al curso';
$string['basemaps'] = 'Mapas base';
$string['cancel'] = 'Cancelar';
$string['changecamera'] = 'Cambiar cámara';
$string['changetogroupmode'] = 'El modo de juego ha cambiado a jugar en grupos';
$string['changetoindividualmode'] = 'El modo de juego ha cambiado a jugar individual';
$string['changetoplaywithmove'] = 'El modo de juego ha cambiado a jugar desplazándose';
$string['changetoplaywithoutmoving'] = 'El modo de juego ha cambiado a jugar sin desplazarse';
$string['cleartreasurehunt'] = 'Resetear la Caza del Tesoro';
$string['cleartreasurehunt_done'] = 'La actividad se ha inicializado. Toda la actividad de los participantes se ha eliminado.';
$string['cleartreasurehuntconfirm'] = 'Cuidado con esta acción. Si continua se eliminará toda la actividad de los participantes. Normalmente sólo es necesario para reiniciar la actividad y poder modificar el número de caminos o etapas en una Caza del Tesoro en la que haya participado alguien por error o para pruebas. Actualmente hay {$a} registros de actividad.';
$string['configintro'] = 'Los valores fijados aquí definen los valores por defecto usados en el formulario de especificaciones
cuando usted crea una nueva caza del tesoro.';
$string['configmaximumgrade'] = 'Valor por defecto al que se ajustará la calificación de la caza del tesoro.';
$string['confirm'] = 'Confirmar';
$string['confirmdeletestage'] = 'Se eliminó la etapa correctamente';
$string['continue'] = 'Continuar';
$string['correctanswer'] = 'Respuesta correcta.';
$string['customlayername'] = 'Título de la capa';
$string['customlayername_help'] = 'Si usa una capa personalizada tendrá que indicar un nombre para mostrarlo en los mapas de los usuarios. Si el nombre está vacío la capa personalizada estará desactivada.';
$string['custommapimagefile'] = 'Imagen de mapa';
$string['custommapimagefile_help'] = 'Cargue una imagen de suficiente resolución y rellene los 4 campos siguientes con las coordenadas de proyección sobre el terreno.';
$string['custommapping'] = 'Mapa personalizado';
$string['custommapminlat'] = 'Latitud sur';
$string['custommapminlat_help'] = 'Latitud sur de la imagen. Use "." para los decimales. Mayor de -85 grados y menor que la latitud norte.';
$string['custommapminlon'] = 'Longitud oeste';
$string['custommapminlon_help'] = 'Longitud oeste de la imagen. Use "." para los decimales. Mayor de -180 grados y menor que la longitud este.';
$string['custommapmaxlat'] = 'Latitud norte';
$string['custommapmaxlat_help'] = 'Latitud norte de la imagen. Use "." para los decimales. Menor de 85 grados y mayor que la latitud sur.';
$string['custommapmaxlon'] = 'Longitud este';
$string['custommapmaxlon_help'] = 'Longitud este de la imagen. Use "." para los decimales. Menor de 180 grados y mayor que la longitud oeste.';
$string['customlayertype'] = 'Tipo de capa';
$string['customlayertype_help'] = 'La imagen puede usarse como fondo del mapa o mostrarse por encima de los mapas usuales de carreteras o satélite.';
$string['customlayerwms'] = 'Servicio WMS';
$string['customlayerwms_help'] = 'Utiliza una capa cartográfica obtenida de un servicio OGC WMS. (Por ejemplo, la capa de ecosistemas de bosques EUNIS Forest Ecosystems WMS se configura con: WMS: <code style="overflow-wrap: break-word;word-wrap: break-word;">http://bio.discomap.eea.europa.eu/arcgis/services/Ecosystem/Ecosystems/MapServer/WMSServer</code> PARAMS: <code>LAYERS=4</code>)';
$string['customwmsparams'] = 'Parámetros adicionales WMS';
$string['customwmsparams_help'] = 'Los parámetros que definen el aspecto del mapa. Con formato análogo a: "LAYERS=fondo,calles&STYLES=azul,callejero"';
$string['custommapbaselayer'] = 'La imagen se usa como una opción adicional de mapa base.';
$string['custommaponlybaselayer'] = 'La imagen se usa como ÚNICO mapa base.';
$string['custommapoverlaylayer'] = 'La imagen se coloca por encima de los mapas';
$string['custommapnongeographic'] = 'La imagen no es geográfica';
$string['cutoffdate'] = 'Fecha límite';
$string['cutoffdate_help'] = 'Si se activa la opción, no se aceptarán intentos después de esta fecha sin una ampliación.';
$string['cutoffdatefromdatevalidation'] = 'La fecha límite debe ser posterior de la de inicio.';
$string['discoveredlocation'] = 'Localización descubierta';
$string['editingroad'] = 'Editando camino';
$string['editingstage'] = 'Editando etapa';
$string['editingtreasurehunt'] = 'Editando caza del tesoro';
$string['edition'] = 'Panel de edición';
$string['edition_help'] = 'Para habilitar el panel de creación y edición de geometrías debe seleccionar previamente la etapa que desea editar';
$string['editactivity_help'] = 'Puede encontrar un tutorial para crear una caza del tesoro paso a paso en <a href="http://juacas.github.io/moodle-mod_treasurehunt/es/crear_actividad.html"> esta página.</a>';
$string['editroad'] = 'Editar camino';
$string['editstage'] = 'Editar etapa';
$string['edittreasurehunt'] = 'Cambiar caminos y pistas';
$string['errcorrectanswers'] = 'Debe seleccionar una respuesta correcta';
$string['errcorrectsetanswerblank'] = 'Respuesta correcta marcada, pero la respuesta está vacía';
$string['errnocorrectanswers'] = 'Debe haber solo una respuesta correcta';
$string['errnumeric'] = 'Debe introducir un número decimal válido';
$string['erremptystage'] = 'Todas las etapas deben tener al menos una geometría para que el camino sea válido';
$string['error'] = 'Error';
$string['errpenalizationexceed'] = 'La penalización no puede ser mayor que 100';
$string['errpenalizationfall'] = 'La penalización no puede ser menor que 0';
$string['errsendinganswer'] = 'El camino se ha actualizado mientras enviabas la respuesta, vuelvelo a intentar';
$string['errsendinglocation'] = 'El camino se ha actualizado mientras enviabas tu localización, vuelvelo a intentar';
$string['errvalidroad'] = 'Deben existir al menos dos etapas que tengan al menos una geometría para que el camino sea válido';
$string['eventattemptsubmitted'] = 'Intento enviado';
$string['eventattemptsucceded'] = 'Etapa superada';
$string['eventhuntsucceded'] = 'Treasurehunt terminado con éxito';
$string['eventplayerentered'] = 'Player iniciado';
$string['eventroadcreated'] = 'Camino creado';
$string['eventroaddeleted'] = 'Camino eliminado';
$string['eventroadupdated'] = 'Camino actualizado';
$string['eventstagecreated'] = 'Etapa creada';
$string['eventstagedeleted'] = 'Etapa eliminada';
$string['eventstageupdated'] = 'Etapa actualizada';
$string['exit'] = 'Volver al curso';
$string['failedlocation'] = 'Localización fallada';
$string['faillocation'] = 'No es el lugar correcto';
$string['gamemodeinfo'] = 'Modo de juego: {$a}';
$string['gameupdatetime'] = 'Tiempo de actualización de juego';
$string['gameupdatetime_help'] = 'Intervalo de tiempo en segundos entre una actualización del juego de un usuario y otra.
Cuanto más grande es, menos peticiones de actualización deben hacerse, pero más tiempo pasa en informar de un posible cambio.
Debe ser mayor a 0 segundos, sino se fijará el tiempo por defecto.';
$string['geolocation_needed_title'] = 'Esta aplicación necesita geolocalización';
$string['geolocation_needed'] = 'Para participar en la caza del tesoro es necesario permitir que el teléfono nos informe de su posición. <p>Para activarlo vaya en su navegador a Configuración->Configuración de sitios web->Ubicación y borre el bloqueo para este sitio. <p>Recargue la página y responda "SÍ" cuando el navegador le pregunte si desea compartir su localización.
<p>Para poder usar el GPS para localizar este dispositivo durante la Caza del Tesoro, se debe acceder al servidor mediante
URLs seguras con HTTPS. En caso contrario, sólo se podrá usar el modo "Jugar sin moverse" en el que los jugadores
tienen que marcar manualmente en el mapa cada una de las etapas.
Por favor contacte con su administrador si no puede resolver este problema.';
$string['grade_explaination_fromposition'] = '{$a->rawscore}-{$a->penalization}%: Has superado {$a->nosuccessfulstages} pistas en la posición {$a->position}. Penalizas un {$a->penalization}% por {$a->nolocationsfailed} lugares mal, y {$a->noanswersfailed} fallos de respuesta.';
$string['grade_explaination_fromtime'] = '{$a->rawscore}-{$a->penalization}%: Has tardado {$a->yourtime} en terminar la caza. El mejor tiempo ha sido {$a->besttime}. Penalizas un {$a->penalization}% por {$a->nolocationsfailed} lugares mal y {$a->noanswersfailed} fallos de respuesta.';
$string['grade_explaination_fromabsolutetime'] = '{$a->rawscore}-{$a->penalization}%: Has terminado la caza el {$a->yourtime}. El mejor terminó el {$a->besttime}. Penalizas un {$a->penalization}% por {$a->nolocationsfailed} lugares mal y {$a->noanswersfailed} fallos de respuesta.';
$string['grade_explaination_fromstages'] = '{$a->rawscore}-{$a->penalization}%: Has cubierto {$a->nosuccessfulstages} de {$a->nostages} pistas. Penalizas un {$a->penalization}% por {$a->nolocationsfailed} lugares mal y {$a->noanswersfailed} fallos de respuesta.';
$string['grade_explaination_temporary'] = 'Caza sin terminar. Recibes el 50% por pistas descubiertas:
{$a->rawscore}-{$a->penalization}%: Has cubierto {$a->nosuccessfulstages} de {$a->nostages} pistas.
Penalizas un {$a->penalization}% por {$a->nolocationsfailed} lugares mal y {$a->noanswersfailed} fallos de respuesta.';
$string['gradefromposition'] = 'Puntuación por posición';
$string['gradefromstages'] = 'Puntuación por etapas';
$string['gradefromtime'] = 'Puntuación por tiempo total de caza';
$string['gradefromabsolutetime'] = 'Puntuación por hora de finalización';

$string['grademethod'] = 'Método de calificación';
$string['grademethod_help'] = '<P><B>Puntuación por etapas</B><P>
<UL>
<P>Cada jugador (o equipo) puntua de forma proporcional al número de etapas
resueltas, siendo el 100% de la calificación máxima cuando se ha completado el camino
y 0 cuando no se ha resuelto ninguna etapa.</UL>
<P><B>Puntuación por tiempo de caza</B><P>
<UL>
<P>El ganador de la caza es el que termina la caza en el menor tiempo
(medido desde el momento en que desbloqueó la etapa de salida,
por lo que los participantes pueden comenzar en momentos distintos).
La calificación se calcula interpolando el tiempo
de finalización, siendo el 50% de la calificación máxima el peor tiempo de finalización y el 100% el mejor.
Los jugadores que no terminaron la caza reciben una calificación por debajo del 50% calculado simplemente por el número de etapas resueltas.
</UL>
<P><B>Puntuación por hora de finalización</B><P>
<UL>
<P>El ganador de la caza es el que termina la caza antes (asume que todos los participantes juegan simultáneamente).
La calificación se calcula interpolando el tiempo
de finalización, siendo el 50% de la calificación máxima el peor tiempo de finalización y el 100% el mejor.
Los jugadores que no terminaron la caza reciben una calificación por debajo del 50% calculado simplemente por el número de etapas resueltas.
</UL>
<P><B>Puntuación por posición</B><P>
<UL>
<P>La puntuación se calcula interpolando la posición en el ranking,
siendo el 100% de la calificación máxima para al primer jugador (o equipo)
en finalizar y 50% para el último jugador. Los jugadores que no terminaron
la caza reciben una calificación por debajo del 50% calculado simplemente
por el número de etapas resueltas.</UL>';
$string['grademethodinfo'] = 'Método de calificación: {$a->type}. Penaliza {$a->gradepenlocation}% por localización, {$a->gradepenanswer}% por respuestas';
$string['gradepenanswer'] = 'Penalización por fallo en respuesta';
$string['gradepenlocation'] = 'Penalización por fallo en localización';
$string['gradepenlocation_help'] = 'La penalización es expresada en % de la calificación.
Por ejemplo, si la penalización es 5.4, un jugador con 3 fallos penalizará su
nota en un 16.2%, es decir, recibirá el 83.8% de la calificación calculada con el resto de criterios.';
$string['gradesdeleted'] = 'Eliminadas calificaciones de la caza del tesoro';
$string['gradingsummary'] = 'Sumario de calificaciones';
$string['group'] = 'Grupo';
$string['groupactivityovercome'] = 'Actividad a finalizar completada con éxito por {$a->user}
para la etapa {$a->position} en la fecha: {$a->date}';
$string['groupid'] = 'Grupo asignado al camino';
$string['groupid_help'] = 'Los usuarios de este grupo son asignados a este camino cuando empieza el juego.
Si sólo existe un camino y la opción seleccionada es "ninguno", todos los participantes de la actividad jugarán por él';
$string['groupingid'] = 'Agrupación asignada al camino';
$string['groupingid_help'] = 'Los grupos de esta agrupación son asignados a este camino cuando empieza el juego';
$string['groupinvalidroad'] = '{$a} tiene asignado un camino no validado.';
$string['grouplocationfailed'] = 'Localización fallida por {$a->user} de la etapa {$a->position} en la fecha: {$a->date}';
$string['grouplocationovercome'] = 'Localización encontrada por {$a->user} de la etapa {$a->position} en la fecha: {$a->date}';
$string['groupmode'] = 'Juego en grupos';
$string['groupmode_help'] = 'Si está habilitado los estudiantes se dividirán en grupos en función de la configuración de grupos del curso.
El juego del grupo será compartido entre los miembros del grupo y todos ellos verán los cambios producidos en el juego.';
$string['groupmultipleroads'] = '{$a} tiene más de un camino asignado.';
$string['groupquestionfailed'] = 'Respuesta fallida por {$a->user} a la pregunta de la etapa {$a->position} en la fecha: {$a->date}';
$string['groupquestionovercome'] = 'Respuesta acertada por {$a->user} a la pregunta de la etapa {$a->position} en la fecha: {$a->date}';
$string['groups'] = 'Grupos';
$string['groupstageovercome'] = 'Etapa {$a->position} superada por {$a->user} en la fecha: {$a->date}';
$string['hello'] = 'Hola';
$string['historicalattempts'] = 'Historial de intentos de {$a}';
$string['history'] = 'Historial';
$string['huntcompleted'] = 'Ya has completado esta caza del tesoro';
$string['incorrectanswer'] = 'Respuesta incorrecta.';
$string['info'] = 'Información';
$string['infovalidatelocation'] = 'Validar ubicación para esta etapa';
$string['invalidassignedroad'] = 'El camino asignado no está validado';
$string['invalroadid'] = 'El camino no está validado';
$string['layers'] = 'Capas';
$string['loading'] = 'Cargando';
$string['lockedaclue'] = 'Debes realizar la actividad \'<strong>{$a}</strong>\' para desbloquear la pista';
$string['lockedclue'] = 'Pista bloqueada';
$string['lockedaqclue'] = 'Debes realizar la actividad \'<strong>{$a}</strong>\' y responder correctamente
a la siguiente pregunta para desbloquear la pista';
$string['lockedqclue'] = 'Debes responder correctamente a la siguiente pregunta para desbloquear la pista';
$string['locktimeediting'] = 'Tiempo de bloqueo de edición';
$string['locktimeediting_help'] = 'Tiempo en segundos durante el cual un usuario puede editar una instancia sin
renovar el bloqueo. Cuanto más grande es, menos peticiones de renovación deben hacerse,
pero más tiempo queda bloqueada la página de edición una vez que el usuario termine.
Debe ser mayor a 5 segundos, sino se fijará el tiempo por defecto.';
$string['mapview'] = 'Vista del mapa';
$string['modify'] = 'Modificar';
$string['modulename'] = 'Caza del tesoro';
$string['modulename_help'] = 'Caza del tesoro al aire libre, en interiores y con mapas virtuales con geolocalización y códigos QR.
Este módulo para Moodle permite organizar juegos serios al aire libre con sus alumnos.
TreasureHunt incluye una aplicación de navegador (no es necesario instalar ninguna aplicación nativa) para el juego y un editor geográfico
para codificar las etapas del juego. El juego se puede configurar con una amplia gama de opciones que hacen que el módulo sea muy flexible y
útil en muchas situaciones: individual / equipo, movimiento / marcado manual en escritorio, puntuación de tiempo, posición, finalización, etc.
<p><b><a href = "https://juacas.github.io/moodle-mod_treasurehunt/es/index.html">Más información e instrucciones online.</a></b></p>';
$string['modulenameplural'] = 'Cazas del tesoro';
$string['movingplay'] = 'Jugar en movimiento';
$string['multiplegroupingsplay'] = 'Tu grupo tiene asignados más de un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupsplay'] = 'Tienes asignados más de un camino, por lo que no puedes jugar la actividad.';
$string['multiplegroupssameroadplay'] = 'Perteneces a más de un grupo asignado al mismo camino, por lo que no puedes jugar la actividad.';
$string['multipleteamsplay'] = 'Es miembro de más de un grupo, por lo que no puede realizar la actividad.';
$string['mustanswerquestion'] = 'Debes responder correctamente a la pregunta antes de continuar';
$string['mustcompleteactivity'] = 'Debes superar la actividad a completar antes de continuar';
$string['mustcompleteboth'] = 'Debes responder correctamente a la pregunta y superar la actividad a completar antes de continuar';
$string['noanswerselected'] = 'Debes seleccionar una respuesta';
$string['noattempts'] = 'No has realizado ningún intento';
$string['noexsitsstage'] = 'No existe la etapa número {$a} en la base de datos. Recargue la página';
$string['nogroupassigned'] = 'Ningún grupo asignado a este camino';
$string['nogroupingplay'] = 'No tienes ningún grupo asignado a un camino, por lo que no puedes jugar la actividad.';
$string['nogroupplay'] = 'No tienes ningún camino asignado, por lo que no puedes jugar la actividad.';
$string['nogrouproad'] = '{$a} no tiene ningún camino asignado.';
$string['nomarks'] = 'Marca primero en el mapa el punto deseado. Sitúa el <img src="pix/my_location.png" width="28"/>';
$string['noresults'] = 'No se han encontrado resultados.';
$string['noroads'] = 'Todavía no se han añadido caminos';
$string['notchangeorderstage'] = 'No puedes cambiar el orden de las etapas una vez que se han realizado intentos sobre el camino.';
$string['notcreatestage'] = 'Ya se han realizado intentos sobre este camino, no puedes añadir más etapas.';
$string['notdeletestage'] = 'Ya se han realizado intentos sobre este camino, no puedes eliminar ninguna etapa.';
$string['noteam'] = 'No es miembro de ningún grupo';
$string['notreasurehunts'] = 'No existe ninguna caza del tesoro en este curso';
$string['nouserassigned'] = 'Ningún usuario asignado a este camino';
$string['nouserattempts'] = '{$a} no ha realizado ningún intento';
$string['nouserroad'] = '{$a} no tiene ningún camino asignado.';
$string['nousersprogress'] = 'Ningún usuario/grupo tiene progresos en este camino.';
$string['outoftime'] = 'Fuera de tiempo';
$string['overcomefirststage'] = 'Para descubrir la primera etapa debes comenzar desde el área marcada en el mapa';
$string['play'] = 'Jugar';
$string['playstagewithoutmoving'] = 'Descubrir etapa sin desplazarse';
$string['playstagewithoutmoving_help'] = 'Si esta opción se habilita, los alumnos podrán descubrir esta etapa sin tener
que desplazarse. Para ello, cada vez que el alumno realiza un click simple sobre el mapa se crea una marca,
borrando la anterior si existiese, indicando el último punto deseado. Al completar la etapa, el juego cambiará
a la configuración por defecto de la actividad';
$string['playstagewithqr'] = 'Descubrir etapa escaneando este QR';
$string['playstagewithqr_help'] = 'Si se rellena, los estudiantes pueden descubrir esta etapa escaneando este QR.';

$string['playwithoutmoving'] = 'Jugar sin desplazarse';
$string['playwithoutmoving_help'] = 'Si esta opción se habilita, los alumnos podrán jugar sin desplazarse a los lugares.
Para ello, cada vez que el alumno realiza un click simple sobre el mapa se crea una marca,
borrando la anterior si existiese, indicando el último punto deseado';
$string['pluginadministration'] = 'Administración de la caza del tesoro';
$string['pluginname'] = 'Caza del tesoro';
$string['question'] = 'Pregunta';
$string['remove'] = 'Eliminar';
$string['removealltreasurehuntattempts'] = 'Eliminar todos los intentos de resolver la caza del tesoro';
$string['removedactivitytoend'] = 'Se ha eliminado la actividad a completar';
$string['removedquestion'] = 'Se ha eliminado la pregunta';
$string['removeroadwarning'] = 'Si eliminas el camino se eliminaran todas las etapas asociadas y ya no podras recuperarlas';
$string['removewarning'] = 'Si la eliminas ya no podras recuperarla';
$string['restrictionsdiscoverstage'] = 'Restricciones para descubrir la etapa';
$string['reviewofplay'] = 'Revisión del juego';
$string['road'] = 'Camino';
$string['roadmap'] = 'Callejero';
$string['roadname'] = 'Nombre del camino';
$string['roadview'] = 'Callejero';
$string['roadended'] = 'Este camino está completado. ¡Enhorabuena! Ya has terminado la caza del tesoro. Puedes comprobar tu histórico en el mapa.';
$string['save'] = 'Guardar';
$string['saveemptyridle'] = 'Todas las etapas modificadas deben tener geometría antes de guardar';
$string['savewarning'] = 'No ha guardado los cambios realizados.';
$string['scanQR_scanbutton'] = 'Escanear código QR';
$string['scanQR_generatebutton'] = 'Generar un nuevo QR';
$string['search'] = 'Buscar';
$string['searching'] = 'Buscando';
$string['searchlocation'] = 'Buscar localización';
$string['send'] = 'Enviar';
$string['sendlotacioncontent'] = 'Esta acción no se puede deshacer.';
$string['sendlotaciontitle'] = '¿Estás seguro de que deseas enviar esta ubicación?';
$string['stage'] = 'Etapa';
$string['stageclue'] = 'Pista para localizar la siguiente etapa';
$string['stageclue_help'] = 'Aquí se debe describir la pista para alcanzar
la siguiente localización. En el caso de que esta sea la última etapa debe dejar
un mensaje de retroalimentación indicando que la caza del tesoro ha finalizado';
$string['stagename'] = 'Nombre de la etapa';
$string['stageovercome'] = 'Etapa superada';
$string['stages'] = 'Etapas';
$string['start'] = 'Empezar';
$string['startfromhere'] = 'Solo se puede empezar desde aquí';
$string['state'] = 'Estado';
$string['successlocation'] = '¡Es el lugar correcto!';
$string['timeexceeded'] = 'Se ha superado el tiempo limite para realizar la actividad. Esta pantalla solo sirve para revisar el juego';
$string['timelabelfailed'] = 'Ubicación enviada en la fecha: ';
$string['timelabelsuccess'] = 'Etapa descubierta en la fecha: ';
$string['trackusers'] = 'Almacenar itinerarios';
$string['trackusers_help'] = 'Almacena el itinerario que realizan los usuarios mientras usan el modo de juego.';
$string['trackviewer'] = 'Visor de rastreo';
$string['trackviewerrefreshtracks'] = 'Actualizar las rutas cada {$a} segundos.';
$string['treasurehunt'] = 'Treasure hunt';
$string['treasurehunt:addinstance'] = 'Añadir nueva caza del tesoro';
$string['treasurehunt:addroad'] = 'Añadir camino';
$string['treasurehunt:addstage'] = 'Añadir etapa';
$string['treasurehuntclosed'] = 'Esta caza del tesoro está cerrada desde el {$a}';
$string['treasurehuntcloses'] = 'Caza del tesoro cerrada';
$string['treasurehuntcloseson'] = 'Esta caza del tesoro cerrará el {$a}';
$string['treasurehunt:editroad'] = 'Editar camino';
$string['treasurehunt:editstage'] = 'Editar etapa';
$string['treasurehuntislocked'] = '{$a} está editando esta caza del tesoro. Intenta editarla dentro de unos minutos.';
$string['treasurehunt:managetreasure'] = 'Administrar Caza del tesoro';
$string['treasurehunt:managetreasurehunt'] = 'Gestionar caza del tesoro';
$string['treasurehuntname'] = 'Nombre de la caza del tesoro';
$string['treasurehuntnotavailable'] = 'Esta caza del tesoro no estará disponible hasta el {$a}';
$string['treasurehuntopens'] = 'Caza del tesoro abierta';
$string['treasurehuntopenedon'] = 'Esta caza del tesoro está abierta desde el {$a}';
$string['treasurehunt:play'] = 'Jugar';
$string['treasurehunt:view'] = 'Ver la caza del tesoro';
$string['treasurehunt:viewusershistoricalattempts'] = 'Ver el historial de intentos de los usuarios';
$string['updates'] = 'Actualizaciones';
$string['updatetimes'] = 'Tiempos de actualización';
$string['user'] = 'Usuario';
$string['useractivityovercome'] = 'Actividad a finalizar completada con éxito para la etapa {$a->position} en la fecha: {$a->date}';
$string['userinvalidroad'] = '{$a} tiene asignado un camino no validado.';
$string['userlocationfailed'] = 'Localización fallida de la etapa {$a->position} en la fecha: {$a->date}';
$string['userlocationovercome'] = 'Localización encontrada de la etapa {$a->position} en la fecha: {$a->date}';
$string['usermultipleroads'] = '{$a} tiene más de un camino asignado.';
$string['usermultiplesameroad'] = '{$a} pertenece a más de un grupo asignado al mismo camino.';
$string['userprogress'] = 'El progreso de usuario se ha actualizado con éxito';
$string['userquestionfailed'] = 'Respuesta fallida a la pregunta de la etapa {$a->position}  en la fecha: {$a->date}';
$string['userquestionovercome'] = 'Respuesta acertada a la pregunta de la etapa {$a->position} en la fecha: {$a->date}';
$string['usersprogress'] = 'Progreso de los usuarios';
$string['usersprogress_help'] = 'Indica el progreso de las etapas de cada alumno/grupo en función de los colores:
<P>El color <B>verde</B> indica que la etapa se ha superado sin fallos.</P>
<P>El color <B>amarillo</B> indica que la etapa se ha superado con fallos.</P>
<P>El color <B>rojo</B> indica que la etapa no se ha superado y se han cometido fallos.</P>
<P>El color <B>gris</B> indica que la etapa no se ha superado y no se han cometido fallos.</P>';
$string['userstageovercome'] = 'Etapa {$a->position} superada en la fecha: {$a->date}';
$string['validatelocation'] = 'Validar ubicación';
$string['validateqr'] = 'Escanea QR';
$string['warmatchanswer'] = 'La respuesta no corresponde con la pregunta';
$string['warnqrscanner'] = '<table><tr><td> Esta Caza del Tesoro tiene {$a} etapas que pueden necesitar escanear códigos QR.
Comprueba que tu dispositivo puede escanear códigos QR desde el navegador Web.
La imagen de tu cámara debería aparecer más abajo. Intenta leer con ella un código como
éste.</td><td><a href="pix/qr.png"><img align="top" src="pix/qr.png" width="100"></a></td></tr></table>';
$string['warnqrscannersuccess'] = 'Esta Caza del Tesoro tiene {$a} etapas que hay que superar con códigos QR.
Parece que ya has pasado una prueba de escaneo y puedes usar este dispositivo para esa parte.';
$string['warnusersgroup'] = 'Los siguientes usuarios pertenecen a más de un grupo: {$a}, por lo que no son capaces de jugar la actividad.';
$string['warnusersgrouping'] = 'Los siguientes grupos pertenecen a más de una agrupación: {$a}, por lo que no son capaces de jugar la actividad.';
$string['warnusersoutside'] = 'Los siguientes usuarios no pertenecen a ningún grupo/agrupación: {$a},
por lo que no son capaces de jugar la actividad.';
$string['warnunsecuregeolocation'] = 'Es muy posible que la geolocalización NO FUNCIONE en este servidor.
Esto es un <b>error de configuración muy GRAVE</b> provocada por la configuración de su servidor:
Las funciones de Geolocalización están prohibidas cuando se usa un servidor no seguro (que use HTTP en lugar de HTTPS).
Para poder usar el GPS para localizar a los estudiantes durante la Caza del Tesoro se debe acceder al servidor mediante
URLs seguras con HTTPS. En caso contrario, sólo se podrá usar el modo "Jugar sin moverse" en el que los jugadores
tienen que marcar manualmente en el mapa cada una de las etapas.
Por favor contacte con su administrador.
(Referencias: <a href="https://www.chromestatus.com/feature/5636088701911040">Chrome</a>, <a href="https://blog.mozilla.org/security/2015/04/30/deprecating-non-secure-http/">Firefox</a> announces.';

// Initial tour help.
$string['addroad_tour'] = 'Una caza del tesoro debe tener al menos un camino para recorrer. Cada camino debe tener dos o más etapas. Comienza a diseñar tu juego añadiendo un camino.';
$string['addstage_tour'] = 'Añade varias etapas para que los estudiantes puedan seguir el camino. Cada etapa debe contener una pista para descubrir la siguiente etapa del camino. Además puedes poner una pregunta de verificación o establecer otras condiciones de desbloqueo.';
$string['editend_tour'] = '¡Ya estás listo para diseñar emocionantes juegos de Caza del Tesoro para tus estudiantes!';
$string['map_tour'] = 'En el mapa se ven y gestionan todas las localizaciones de las etapas de un juego. Las etapas están numeradas para identificarlas facilmente.';
$string['remove_tour'] = 'Se pueden borrar los polígonos de las etapas. Símplemente selecciona un polígono en el mapa y pulsa este botón.';
$string['roads_tour'] = 'En este área se muestran los caminos que has creado. Selecciona uno de los caminos para ver y gestionar sus etapas.';
$string['save_tour'] = 'Tras dibujar los polígonos de las etapas, no olvides guardar tus cambios con este botón.';
$string['searchlocation_tour'] = 'Con este buscador puedes localizar rápidamente tus puntos de interés por su nombre.';
$string['stages_tour'] = 'En esta zona aparecen las etapas del juego. Selecciona cada etapa y se ampliarán en el mapa.';
$string['welcome_edit_tour'] = 'Bienvenido a la página de autor de juegos de la Caza del Tesoro. ';

$string['autolocate_tour'] = 'Durante el juego, puedes situarte en el mapa mediante el GPS de tu dispositivo pulsando en este botón. Si tu dispositivo pide permiso para localizarte, responde que sí.';
$string['validatelocation_tour'] = 'Cuando estés seguro de la localización de una etapa del juego debes enviar tu posición para comprobar si has acertado. Pulsa este botón una vez que el mapa esté centrado en tu posición.';
$string['lastsuccessfulstage_tour'] = 'En este panel encontrarás la última pista descubierta. Puede ser la que tú has descubierto o la de tus compañeros de equipo (si juegas por equipos).';
$string['mapplay_tour'] = 'En este mapa verás todos los intentos que habéis hecho durante este juego geolocalizado. Las pistas acertadas se marcan con <img src="pix/success_mark.png" width="28"/> y las falladas con <img src="pix/failure_mark.png" width="28"/>';
$string['playend_tour'] = '¡Disfruta de la Caza del Tesoro con tus compañeros de búsqueda!';
$string['welcome_play_tour'] = 'Bienvenido a la pantalla de juego de la Caza del Tesoro. Este es el interfaz para buscar, investigar y conseguir tu tesoro.';
$string['nextstep'] = 'Sig.';
$string['prevstep'] = 'Ant.';
$string['skiptutorial'] = 'Salir';
$string['donetutorial'] = '¡Visto!';
// Privacy strings.
$string['privacy:metadata_treasurehunt_track_userid'] = 'Treasure hunt almacena la secuencia de posiciones del usuario durante la actividad (si el profesor ha activado esa opción).';
$string['privacy:metadata_treasurehunt_attempts_userid'] = 'Treasure hunt almacena el histórico de los intentos realizados por cada usuario incluyendo tiempo, tipo y éxito o fracaso en cada intento.';