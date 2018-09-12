# moodle-mod_treasure
Activity module for Moodle that allows to run outdoor, indoor (QRCodes), virtual maps (canvas) treasure-chases with geolocation. [Tutorial and manuals in English and Spanish](https://juacas.github.io/moodle-mod_treasurehunt/)

<img src="https://juacas.github.io/moodle-mod_treasurehunt/assets/images/main.jpg" height="200">
<img src="https://juacas.github.io/moodle-mod_treasurehunt/assets/images/4.png" height="200">
<img src="https://juacas.github.io/moodle-mod_treasurehunt/assets/images/1.png" height="200">
<img src="https://juacas.github.io/moodle-mod_treasurehunt/assets/images/users.jpg" height="200">

Installation
=============

1. unzip, and copy into Moodle's /mod folder
2. visit administration page to install module
3. configure default settings for your site
4. use in any course as wished


Operation
==========

1. create an instance of the activity
2. create one or more roads to be followed by your "hunters"
3. create a set of stages in each road.
4. write smart and educational clues that point to the next stage. Optinally, write a multiple-chice question to double-check the discovery of the stage
5. draw the location of the stages with th buil-in GIS editor
6. select the modality of the treasure hunt: individual or grouped (you will need to create the groups and groupings of students).
7. adjust the grading method and penalizations.
8. let your students play the game.


See also
=========

 - [Moodle plugins entry page](http://moodle.org/plugins/view.php?plugin=mod_treasurehunt)
 - [Moodle.org forum discussion thread](https://moodle.org/mod/forum/discuss.php?d=354875)
 - [Tutorial and manuals in English and Spanish](https://juacas.github.io/moodle-mod_treasurehunt/)

Change log
==========
 - v1.2.21 Fixed a minor bug in event reporting in play mode. Icons resized.
 - v1.2.20 Fixed compatibility with PostgreSQL.
 - v1.2.19 Fixed validation error when filemanager is conditionally disabled.
 - v1.2.18 Fixed compatibility of GIS editor with Edge browser when using jqueryui > 1.11.
 - v1.2.17 Auto update timer in GPX tracker.
 - v1.2.16 Bug fixed: exception when completing an activity set to COMPLETION_MARKING_MANUAL.
 - v1.2.15 Full compatibility of QRScanner with IOS 11 (iPhone && iPad).
 - v1.2.14 Fix camera preview sizes. Implement switching cameras.
 - v1.2.13 Change QR scanning library to fix the firefox regression.
 - v1.2.12 Fix for compatibility with Moodle 2.9.x.
 - v1.2.11 Link to online tutorial added to edit page.
 - v1.2.10 Fix support to long texts in clues. Left panel shows a truncated text.
 - v1.2.9 Incompatibility with IOS 11 fixed.
 - v1.2.8 Clues with long text were not readable in lateral panel in the player. Text moved to History Page.
 - v1.2.7 Compatibility with MSSQL
 - v1.2.6 Custom image maps. Validation form fixed.
 - v1.2.5 Non-geographic images as base of the game.
 - v1.2.4 Fixed conflict with Essential theme.
 - v1.2.3 Custom image maps. Support for WMS and uploaded images.
 - v1.2.1 SVG images render with vector quality.
 - v1.2.0 Custom uploaded images.

(c) 2016 onwards. EDUVALab. University of Valladolid.
