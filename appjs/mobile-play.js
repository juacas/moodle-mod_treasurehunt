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
    debugger;

    class MobilePlay {
        constructor() {}

        init() {
            const baseUrlScript = this.getIndexUrl() + "/appjs/ol/ol.js";
            debugger;
            // Si no está cargada ya, cargo la librería ol
            if (this.isOlScriptLoaded(baseUrlScript)) {
                this.initMap();
            } else {
                this.loadOlScript(baseUrlScript).then(as => {
                    this.initMap();
                });
            }
        }

        initMap() {
            const map = new ol.Map({
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
        }

        isOlScriptLoaded(url) {
            let scripts = document.getElementsByTagName("script");
            for (let i = scripts.length; i--; ) {
                if (scripts[i].src == url) return true;
            }
            return false;
        }

        loadOlScript(url) {
            return new Promise((resolve, reject) => {
                //load script
                let script = document.createElement("script");
                script.type = "text/javascript";
                // script.src = "https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v6.0.1/build/ol.js";
                script.src = url;
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

        getIndexUrl() {
            return t.module.url.substr(0, t.module.url.indexOf("/mod/treasurehunt") + 17);
        }
    }

    const mobilePlay = new MobilePlay();
    mobilePlay.init();
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
