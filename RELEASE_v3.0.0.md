# Treasure Hunt v3.0.0

Treasure Hunt 3.0.0 expands how teachers build hunts and how students explore
them: mixed sequential and out-of-sequence discovery, animated 3D players,
OpenData-assisted stage creation, image georeferencing and live participant
progress. This release also redesigns the editor and improves game-state
handling, team diagnostics and activity management.

| Release information | Value |
| --- | --- |
| Version | v3.0.0 |
| Plugin version number | 2026101801 |
| Date | 2026-10-18 |
| Maturity | Beta |
| Minimum Moodle version | 4.5 |
| Configured CI targets | Moodle 4.5 and 5.3, PHP 8.3, MariaDB |

## Flexible discovery: random mode

Teachers can allow selected stages to be discovered outside the normal road
sequence. These stages remain part of the road, and the game skips them later
if they have already been completed. Progress counts each completed stage once,
in both individual and team play.

Each stage now has two HTML clue fields: **Clue for finding this stage** and
**Clue for the next stage or end of the treasure hunt**. At least one must contain
content. When a preceding stage has been completed, its next-stage clue takes
priority over the target stage's own clue. Eligible stages without a completed
predecessor can offer their own clue when discovery out of sequence is enabled.
The first stage uses its own clue when provided. The road keeps its defined order.

Available clues appear in tabs numbered by stage, and spatial hints guide players
toward the nearest eligible stage. The final outgoing clue becomes the closing
message, and attempt popups retain the relevant clue for reviewing the journey.

## Explore with 3D players

**Cesium 3D (experimental)** adds a globe with imagery selection, optional terrain
and buildings, and location search. **Mapbox GL 3D (experimental)** provides an
alternative 3D map experience. Both use an animated hiker pegman that searches,
walks, points toward a hinted destination and reacts to the camera view.

The players support the same individual/team game flow: clues, questions, QR,
GPS or map-selected positions, attempt history and road previews. Stage areas and
attempt markers remain part of the map, and initial flights frame the stage area.
Geographic custom maps work with both players. Maps using local image coordinates
open in Bootstrap/OpenLayers.

Administrators choose the available player styles; teachers select one for each
activity. Managers can also use `playertype=bootstrap`, `playertype=cesium` or
`playertype=mapbox` in a player URL to test another view.

## Build stages from OpenData

Search **DBpedia, Wikidata and Europeana** around the map using categories and an
optional search term. Results show source progress, support further pages and
remain pending until the teacher adds them to the map or replaces its OpenData
layer. The reference layer is remembered locally for that activity.

Item cards present the available descriptions, images, names, subjects, types,
links and geometries. The **Data collector** lets authors explore related records
and attach useful resources before transferring the collection to a new stage.
The generated stage contains an editable own-clue draft and a geographic area
derived from an available polygon or point. Teachers can review the material and
adapt it into an educational clue before using it.

## Georeference custom images

The activity settings now include a map dialog for positioning an uploaded image.
Drag its body to move it and adjust its corners to scale, rotate or skew it.
Transparency and **Fit to view** help align the image with a basemap; Shift-drag
lets you navigate through the image.

Saved corner coordinates preserve the alignment in the editors and players.
Geographic custom images remain visible as overlays when switching basemaps in
Cesium or Mapbox, below 3D buildings. SVG rendering adapts to zoom in the 3D
players to retain detail when moving closer.

## More room to author and monitor

- The Bootstrap editor has floating tools, road tabs, a collapsible stage panel,
  fullscreen mode and remembered road selection. Stage cards preview both clues
  and summarize QR, restrictions and discovery options.
- Native stage reordering, improved autocomplete, road validation feedback and
  quick previews simplify editing. **Copy stages** can append or replace stages
  from another road.
- Experimental `edit2.php` supports OpenLayers, Cesium and Mapbox for editing
  stage geometry, with native map controls and animated 3D pegman support.
- **Show other students progress** enables a live bar in all players, above road
  progress tables and in the tracking viewer. Photos or initials identify players
  and teams; colours show stage status, and a highlighted border marks the current
  participant or team.
- Activity feature cards explain play modes and show QR, road validation and team
  assignment diagnostics, with tooltips usable by touch or mouse.
- Teachers can preview each valid road without joining its group, inspect recorded
  itineraries with an adjustable refresh interval, and reset one participant/team
  sequence, one road or the whole activity.
- The Moodle complete user report now includes stage attempts, discovery dates
  and team progress while respecting hidden grades.

## Reliability, compatibility and documentation

The game-state service now reuses cached road and participant/team state and uses
attempt IDs to distinguish updates reliably. Initialization loads the current
state without announcing old attempts again. Editing locks renew while the editor
is open, and stage/road operations check ownership and permissions.

Other fixes cover geometry saving, initial team responses, embedded images, QR
scanner loading, communication-error recovery, loading overlays, touch gestures,
dialog focus, custom-map projection and stage camera framing. Moodle 4.5 and 5.3
have separate Bootstrap compatibility paths, and the Bootstrap player avoids
Moodle 5.3's obstructing sticky activity footer.

The release includes expanded automated regression tests, Moodle Plugin CI,
VSCode coding-standard tasks, updated Spanish and Greek strings, and a bilingual
website with detailed teacher tutorials and student GPS/camera troubleshooting.
See the [tutorials and manuals](https://juacas.github.io/moodle-mod_treasurehunt/).

## Upgrading from v2.0.3

1. Back up the Moodle database, files and plugin before upgrading.
2. Replace `mod/treasurehunt` with this release and run Moodle's normal plugin
   upgrade. The upgrade adds own-clue and out-of-sequence fields, a tracking index,
   migrates earlier experimental player names and refreshes relevant caches.
3. Review the available/default player styles in the plugin's site settings.
   Configure a Europeana API key to use that provider and a Mapbox public access
   token to use Mapbox-hosted imagery. Cesium is bundled locally; Mapbox GL JS
   loads from its versioned CDN.
4. Keep the existing sequential design or enable discovery out of sequence on
   selected stages. Preview each road after making changes to clues or maps.
5. Enable **Show other students progress** where the live participant bar is
   wanted. OpenData items kept only in the browser's reference layer are not
   shared with players until used to create stages.

The 3D players and `edit2.php` are **experimental** and need suitable
WebGL support. External imagery and OpenData services require network access and
remain subject to provider availability and terms. GPS and QR camera access need
HTTPS and browser permission.

For integrations calling `mod_treasurehunt_user_progress`, the current protocol
uses `attemptid` instead of the former attempt timestamp cursor. Initialize with
`attemptid=0`, `roadtimestamp=0`, `initialize=true`, then send the returned cursors
on subsequent polls. The bundled web players follow this protocol; custom clients
must update with the server.

The complete list of changes since v2.0.3 is in [CHANGELOG.md](CHANGELOG.md).
