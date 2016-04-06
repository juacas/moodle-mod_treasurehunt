// Standard license block omitted.
/*
 * @package    block_overview
 * @copyright  2015 Someone cool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module block_overview/helloworld
 */

define(['jquery', 'core/notification', 'core/str'], function ($, notification, str) {

// Definimos el constructor Persona
    function Persona(primerNombre) {
        this.primerNombre = primerNombre;
    }

// Agregamos un par de métodos a Persona.prototype
    Persona.prototype.caminar = function () {
        alert("Estoy caminando!");
    };
    Persona.prototype.diHola = function () {
        alert("Hola, Soy" + this.primerNombre);
    };
    Persona.prototype.cambiarNombre = function (primerNombre) {
        this.primerNombre=primerNombre;
    };

// Definimos el constructor Estudiante
    function Estudiante(primerNombre, asunto) {
        // Llamamos al constructor padre, nos aseguramos (utilizando Function#call) que "this" se
        // ha establecido correctamente durante la llamada
        Persona.call(this, primerNombre);

        //Inicializamos las propiedades específicas de Estudiante
        this.asunto = asunto;
    }
    ;

// Creamos el objeto Estudiante.prototype que hereda desde Persona.prototype
// Nota: Un error común es utilizar "new Persona()" para crear Estudiante.prototype 
// Esto es incorrecto por varias razones, y no menos importante, nosotros no le estamos pasando nada
// a Persona desde el argumento "primerNombre". El lugar correcto para llamar a Persona
// es arriba, donde nosotros llamamos a Estudiante.
    Estudiante.prototype = Object.create(Persona.prototype);    // Vea las siguientes notas

// Establecer la propiedad "constructor" para referencias  a Estudiante
    Estudiante.prototype.constructor = Estudiante;

// Remplazar el método "diHola"
    Estudiante.prototype.diHola = function () {
        alert("Hola, Soy " + this.primerNombre + ". Yo estoy estudiando " + this.asunto + ".");
    };

// Agregamos el método "diAdios"
    Estudiante.prototype.diAdios = function () {
        alert("¡ Adios !");
    };
    return Estudiante;
});

