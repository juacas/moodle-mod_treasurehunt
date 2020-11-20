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
 * Defines the function to be used from the mobile course play search template.
 *
 * @package   mod_treasurehunt
 * @copyright 2020 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>, Juan Pablo de Castro <jpdecastro@tel.uva.es>
 * @license   http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let that = this;

// Content variables
this.minSearch = 2;
this.searchText = "";
this.places = [];
this.loading = false;

/** Functions */
this.searchLocation = () => {
  let location = this.searchText;
  location = location.trim();
  this.places = [];

  if (location.length > this.minSearch) {
    this.loading = true;
    that.HttpClient.get(
      `https://nominatim.openstreetmap.org/search?format=json&q=${location}`
    )
      .toPromise()
      .then((osmResponse) => {
        this.places = osmResponse;
      })
      .finally(() => {
        this.loading = false;
      });
  }
};

this.cancelSearch = () => {
  this.places = [];
};

this.zoomToPlace = (place) => {
  let extent = [];
  extent[0] = parseFloat(place.boundingbox[2]);
  extent[1] = parseFloat(place.boundingbox[0]);
  extent[2] = parseFloat(place.boundingbox[3]);
  extent[3] = parseFloat(place.boundingbox[1]);
  extent = ol.proj.transformExtent(extent, "EPSG:4326", "EPSG:3857");
  // Needed to avoid interrupting the execution of the callback
  setTimeout(() => this.flyToCallback(extent), 500);
  this.NavController.pop();
};
