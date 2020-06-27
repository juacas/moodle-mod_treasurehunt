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

class TreasureHuntPlayMobile {
  constructor() {
    this.map = null;
    this.defaultAnimationDuration = 700;
    this.defaultzoom = 15;
  }

  init() {
    this.initMap();
  }

  initMap() {
    this.map = new ol.Map({
      target: "map",
      layers: [
        new ol.layer.Tile({
          source: new ol.source.OSM(),
        }),
      ],
      view: new ol.View({
        center: ol.proj.fromLonLat([37.41, 8.82]),
        zoom: 4,
      }),
      controls: ol.control.defaults({
        attribution: false,
        zoom: false,
      }),
    });

    // Required to draw the map after calculating container height
    setTimeout(() => {
      this.map.updateSize();
    }, 500);

    window.onresize = () => {
      this.map.updateSize();
    };
  }

  flyTo(point, extent) {
    const view = this.map.getView();
    if (extent) {
      view.fit(extent, {
        duration: this.defaultAnimationDuration,
      });
    } else {
      view.animate({
        zoom: this.defaultzoom,
        center: point,
        duration: this.defaultAnimationDuration,
      });
    }
  }
}

var that = this;

const treasureHuntPlayMobile = new TreasureHuntPlayMobile();

// Needed for set jsData object to Search Page
this.flyToCallback = {
  flyToCallback: treasureHuntPlayMobile.flyTo.bind(treasureHuntPlayMobile),
};

/**
 * Check if script if loaded
 *
 * @param {*} url
 * @returns
 */
function checkIfScriptIsLoaded(url) {
  let scripts = document.getElementsByTagName("script");
  for (let i = scripts.length; i--; ) {
    if (scripts[i].src == url) return true;
  }
  return false;
}

/**
 *
 *
 * @param {*} url
 * @returns
 */
function loadOlScript() {
  return new Promise((resolve, reject) => {
    // Download the file if it isn't downloaded already.
    const site = that.CoreFilepoolProvider.sitesProvider.getCurrentSite();
    that.CoreFilepoolProvider.downloadUrl(
      site.id,
      site.siteUrl + "/mod/treasurehunt/js/ol/ol.js"
    ).then((localUrl) => {
      // Check if script if loaded
      if (checkIfScriptIsLoaded(localUrl)) {
        resolve();
      } else {
        //load script
        let script = document.createElement("script");
        script.onload = () => {
          resolve();
        };
        script.onerror = (error) => reject();
        script.type = "text/javascript";
        script.src = localUrl;
        document.body.appendChild(script);
      }
    });
  });
}

loadOlScript().then(() => {
  treasureHuntPlayMobile.init();
});
