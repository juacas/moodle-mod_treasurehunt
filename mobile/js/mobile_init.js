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
 * @package   mod_treasurehunt
 * @copyright 2020 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let that = this;

/* Register a link handler to open mod/treasurehunt/view.php links anywhere in the app. */
function AddonModTreasurehuntLinkHandler() {
  that.CoreContentLinksModuleIndexHandler.call(
    this,
    that.CoreCourseHelperProvider,
    "AddonModTreasurehunt",
    "treasurehunt"
  );
  this.name = "AddonModTreasurehuntLinkHandler";
}

AddonModTreasurehuntLinkHandler.prototype = Object.create(
  this.CoreContentLinksModuleIndexHandler.prototype
);

AddonModTreasurehuntLinkHandler.prototype.constructor = AddonModTreasurehuntLinkHandler;

// Register the link handler.
this.CoreContentLinksDelegate.registerHandler(
  new AddonModTreasurehuntLinkHandler()
);
