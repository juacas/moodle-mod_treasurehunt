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
 * Register a link handler to open mod/treasurehunt/view.php links in the app
 *
 * @package    mod_treasurehunt
 * @copyright  2018 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function(t) {
    t.mod_treasurehunt = {};

    class MobilePlay {
        constructor() {}

        loadOlScript() {
            return new Promise((resolve, reject) => {
                //load script
                let script = document.createElement("script");
                script.type = "text/javascript";
                script.src = "http://localhost/moodle/mod/treasurehunt/appjs/ol.js";
                debugger;
                if (script.readyState) {
                    //IE
                    script.onreadystatechange = () => {
                        if (script.readyState === "loaded" || script.readyState === "complete") {
                            script.onreadystatechange = null;
                            resolve();
                        }
                    };
                } else {
                    //Others
                    script.onload = () => {
                        resolve();
                    };
                }
                script.onerror = error => reject();
                document.getElementsByTagName("head")[0].appendChild(script);
            });
        }
    }

    let mobilePlay = new MobilePlay();
    mobilePlay.loadOlScript().then(as => {
        debugger;
        var map = new ol.Map({
            target: "map",
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.OSM()
                })
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([37.41, 8.82]),
                zoom: 4
            })
        });
    });
})(this);

// import Map from "ol/Map";
// import View from "ol/View";
// import TileLayer from "ol/layer/Tile";
// import XYZ from "ol/source/XYZ";

// class MobilePlay {
//     constructor() {
//         new Map({
//             target: "map",
//             layers: [
//                 new TileLayer({
//                     source: new XYZ({
//                         url: "https://{a-c}.tile.openstreetmap.org/{z}/{x}/{y}.png"
//                     })
//                 })
//             ],
//             view: new View({
//                 center: [0, 0],
//                 zoom: 2
//             })
//         });
//     }
// }
