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
  constructor() {}

  init() {
    this.initMap();
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
    // setTimeout(() => {
    //   map.updateSize();
    // });
    window.onresize = () => {
      setTimeout(() => {
        map.updateSize();
      }, 200);
    };
  }
}

(function(t) {
  const treasureHuntPlayMobile = new TreasureHuntPlayMobile();

  /* Register a link handler to open mod/treasurehunt/view.php links anywhere in the app. */
  function AddonModTreasurehuntLinkHandler() {
    t.CoreContentLinksModuleIndexHandler.call(
      this,
      t.CoreCourseHelperProvider,
      "mmaModTreasurehunt",
      "treasurehunt"
    );
    this.name = "AddonModTreasurehuntLinkHandler";
  }
  AddonModTreasurehuntLinkHandler.prototype = Object.create(
    t.CoreContentLinksModuleIndexHandler.prototype
  );
  AddonModTreasurehuntLinkHandler.prototype.constructor = AddonModTreasurehuntLinkHandler;
  t.CoreContentLinksDelegate.registerHandler(
    new AddonModTreasurehuntLinkHandler()
  );

  /**
   * Check if script if loaded
   *
   * @param {*} url
   * @returns
   */
  function checkIfScriptIsLoaded(url) {
    var scripts = document.getElementsByTagName("script");
    for (var i = scripts.length; i--; ) {
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
      const site = t.CoreFilepoolProvider.sitesProvider.getCurrentSite();
      t.CoreFilepoolProvider.downloadUrl(
        site.id,
        site.siteUrl + "/mod/treasurehunt/js/ol/ol.js"
      ).then(localUrl => {
        // Check if script if loaded
        if (checkIfScriptIsLoaded(localUrl)) {
          resolve();
        } else {
          //load script
          let script = document.createElement("script");
          script.onload = () => {
            resolve();
          };
          script.onerror = error => reject();
          script.type = "text/javascript";
          script.src = localUrl;
          // script.src = url;
          document.getElementsByTagName("head")[0].appendChild(script);
        }
      });
    });
  }

  /**
   * Initialisation for the view page.
   *
   * @param {object} outerThis The main component.
   */
  window.treasurehuntViewPageInit = function(outerThis) {
    outerThis.showGradeMethodHelp = function() {
      t.CoreUtilsProvider.domUtils.showAlertTranslated(
        "plugin.mod_treasurehunt.grademethod",
        "plugin.mod_treasurehunt.grademethod_help"
      );
    };
  };

  /**
   * Initialisation for the play page.
   *
   * @param {object} outerThis The main component.
   */
  window.treasurehuntPlayPageInit = function(outerThis) {
    loadOlScript().then(() => {
      debugger;
      treasureHuntPlayMobile.init();
    });
    // // Check and handle module completion feature.
    // t.CoreCourseProvider.checkModuleCompletion(
    //     outerThis.courseId,
    //     outerThis.module.completiondata
    // );
    // // Make loadMoreDiscussion available from the template.
    // outerThis.loadMoreDiscussions = function(infiniteScrollEvent) {
    //     t.mod_forumng.loadMoreDiscussions(outerThis, infiniteScrollEvent);
    // };
  };
})(this);
