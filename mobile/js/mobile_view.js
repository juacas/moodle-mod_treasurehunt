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
 * This file is part of the Moodle apps support for the treasurehunt plugin.
 * Defines the function to be used from the mobile course view template.
 *
 * @package   mod_treasurehunt
 * @copyright 2020 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let that = this;

// Needed for detect changes on core-tabs component
this.ChangeDetectorRef.detectChanges();

// If it is not a group, get the profile pictures of users
if (!this.CONTENT_OTHERDATA.groupmode) {
  setRoadsUserProfiles(this.CONTENT_OTHERDATA.usersprogress.roads);
}

function setRoadsUserProfiles(roads) {
  for (let road of roads) {
    let promises = [];

    promises = road.userlist.map((user) => {
      return that.CoreUserProvider.getProfile(user.id).then((profile) => {
        profile.ratings = user.ratings;
        return profile;
      });
    });

    Promise.all(promises).then((userList) => {
      road.userlist = userList;
    });
  }
}

this.pageOnBackground = false;

this.ionViewDidLeave = () => {
  this.pageOnBackground = true;
};

this.ionViewWillEnter = () => {
  if (this.pageOnBackground) {
    this.refreshContent();
  }
};

this.showGradeMethodHelp = () => {
  that.CoreDomUtilsProvider.showAlertTranslated(
    "plugin.mod_treasurehunt.grademethod",
    "plugin.mod_treasurehunt.grademethod_help"
  );
};

this.showUsersProgressHelp = () => {
  that.CoreDomUtilsProvider.showAlertTranslated(
    "plugin.mod_treasurehunt.usersprogress",
    "plugin.mod_treasurehunt.usersprogress_help"
  );
};
