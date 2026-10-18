# Changelog

This file records changes to Treasure Hunt for Moodle. The v3.0.0 entry covers
changes since v2.0.3, including the authoring, OpenData, out-of-sequence and 3D
player work brought together in this release.

## v3.0.0 — 2026-10-18

Minimum Moodle version: **4.5**. Release maturity: **beta**.

### Out-of-sequence discovery (random mode)

- Added **Allow discovery out of sequence** to individual stages. A road can mix
  stages that follow its normal order with stages that can be discovered early.
- Added an HTML **Clue for finding this stage**, alongside the existing clue for
  the next stage. Either field may be empty, but at least one must contain content.
- Defined clue priority for each eligible, incomplete stage: its completed
  predecessor's next-stage clue takes priority; otherwise its own clue is used.
  A stage whose predecessor is incomplete can offer its own clue when discovery
  out of sequence is enabled. The first stage uses its own clue when provided.
  Empty clues do not create empty tabs.
- Added a clue viewer with tabs numbered by the target stage's position in the
  road. Its size accommodates the largest clue without resizing between tabs.
- Updated advancement, completion and grading to count distinct completed stages
  and skip stages already completed outside the sequence. Team members share the
  same completed stages and available clues.
- Updated spatial hints to consider eligible incomplete stages and use the
  nearest target for direction and distance guidance.
- Separated the final stage's outgoing clue from navigation clues: it is now the
  end-of-road message, displayed after the standard completion explanation.
- Included the relevant gameplay clue in attempt-marker popups and prevented
  automatic opening of the last-clue viewer after road completion.

### 3D game players

- Added **Cesium 3D (experimental)**, with a globe, imagery gallery, optional
  terrain and buildings, location search, and flights to locations and stages.
  Terrain starts disabled and can be enabled from the map controls.
- Added **Mapbox GL 3D (experimental)** as an alternative, with basemap selection,
  buildings controls, location search and the same game workflow.
- Added an animated 3D hiker pegman. Walking, resting, searching, pointing,
  disoriented and seated waving animations reflect the current view and hints.
  Direction hints orient the model toward the target; placement without a
  direction hint faces the model toward the camera.
- Added automatic pegman sizing and animated scale changes as the view changes.
- Supported clues, questions, GPS and map-selected positions, QR scanning, attempt
  history, team progress and teacher previews in both new players.
- Rendered stage areas, outlines and success/failure markers in their geographic
  positions, with initial framing based on stage geometry.
- Added support for geographic custom maps, including image overlays and TMS-style
  `{-y}` tile URLs. Activities with local, non-geographic image coordinates use
  the Bootstrap/OpenLayers player.
- Bundled CesiumJS and the 3D model support assets locally. Mapbox GL JS and its
  stylesheet use the versioned Mapbox CDN.
- Added a manager-only `playertype` URL override for testing a particular player.

### Map and road editor

- Rebuilt the editor layout with Bootstrap/Moodle controls, floating map tools,
  road tabs and a collapsible stage panel that preserves access to stage numbers
  and information buttons.
- Maximized usable map space and added fullscreen mode with locally remembered
  state. The selected road is remembered during the browser session.
- Grouped drawing and editing as related controls, kept navigation and deletion
  separate, and added usage tooltips. Editing is disabled for stages without
  geometry.
- Replaced stage reordering and address autocomplete dependencies on jQuery UI
  with native interactions; replaced its editor icons with Moodle icons.
- Fixed stage numbering after reordering and prevented page scrolling when
  changing roads or interacting with map controls.
- Added compact stage cards with own/next clue previews, links to full clue text,
  and visual indicators for QR, restrictions, discovery without movement and
  discovery out of sequence.
- Added **Copy stages** between roads in the same activity, with append or replace
  options and a summary before confirmation.
- Added quick road preview actions, highlighted invalid roads and stages, and
  moved feedback into tooltips and notifications.
- Renewed editing locks while the editor is open and recovered an expired lock
  for its owner when another editor has not acquired it. Lock messages now
  distinguish genuine editing conflicts.
- Added experimental `edit2.php`, which uses the configured OpenLayers, Cesium or
  Mapbox engine. The 3D editors support polygon drawing, outlines, vertex dragging,
  insertion from an edge preview, stage framing and pegman coordinate popups.

### OpenData search and stage composition

- Added an extensible OpenData source interface with **DBpedia, Wikidata and
  Europeana** providers, source-specific categories and optional text search.
- Searched the visible map area plus a surrounding buffer. Added pagination,
  per-source progress and error feedback, and support for keeping results while
  other sources are still searching.
- Kept search results pending until the author chooses **Add all to map** or
  **Replace**. Combined providers in one OpenData layer, skipped duplicates when
  adding items, and stored the layer locally per activity in the browser.
- Added thematic map symbols, hover labels and item cards with images,
  descriptions, alternative names, types, subjects, categories and source links.
- Added a **Data collector** for following related items, navigating back through
  explored records and attaching useful resources before composing a stage.
- Added cross-source navigation, including DBpedia/Wikidata `owl:sameAs` links,
  category searches and additional geometry retrieval.
- Created editable stages from collected resources, using available polygons or
  a small area around a point. The draft own clue includes available metadata,
  links and external resource images for the teacher to adapt.
- Added a site configuration field for the Europeana API key and actionable
  provider error reporting.

### Custom image georeferencing and overlays

- Added a map dialog for placing an uploaded custom image geographically.
- Supported dragging the image body and adjusting corners to control position,
  scale, rotation and skew, with transparency and **Fit to view** controls.
  Holding Shift while dragging navigates the map through the image.
- Stored image-corner coordinates in the custom map configuration and used them
  across the georeferencer, editors and players to preserve alignment.
- Made geographic custom images persistent overlays above basemaps and below 3D
  buildings in Cesium and Mapbox, independent of basemap selection.
- Added SVG conversion and zoom-dependent rendering for the 3D players to improve
  detail at high zoom, and corrected projection, clipping and overlay placement.
- Increased the maximum supported map zoom in OpenLayers and the 3D players.

### Participant progress, monitoring and activity management

- Added an optional live participant progress bar to all game players, above road
  progress tables on the activity page, and in the tracking viewer. It follows
  the **Show other students progress** setting.
- Used profile photos or initials for individuals, coloured initials for teams,
  and a highlighted border for the current participant or team. Markers indicate
  failed, completed and restriction-pending stages; clicking or tapping identifies
  a participant or team.
- Added a separate cached `mod_treasurehunt_participant_progress` service and
  filtered player bars to their road, including manager previews.
- Reworked the tracking viewer with Bootstrap controls, more map space, a bounded
  OSM zoom level and an automatic-refresh slider with an off position.
- Added activity feature cards for GPS/map play, QR, team play, itinerary
  recording and sequential/out-of-sequence discovery. Tooltips support touch and
  click as well as hover.
- Added teacher diagnostics for invalid roads, unassigned groupings and students
  without suitable groups. Students see their own team-assignment errors.
- Added separate **Preview** actions for each road, without requiring a teacher
  to join its group. Invalid roads cannot be previewed.
- Moved tracking and reset actions into activity navigation.
- Expanded reset controls to an activity, one road, or one participant/team
  sequence, with scoped confirmations. Empty sequences have no delete action,
  and successful resets use Moodle notifications.
- Implemented the Moodle complete user report with road/stage attempts, discovery
  dates and team progress, respecting hidden grades.

### Reliability, security and performance

- Refactored `user_progress` into clearer road, actor, completion and clue flows,
  and reduced repeated queries with Moodle Universal Cache (MUC) definitions for
  road stages and participant/team progress, plus request-level reuse.
- Used attempt IDs to order updates reliably, including attempts created in the
  same second. Initial requests load complete state without replaying old attempts
  as new notifications.
- Added road revision checks and cache invalidation for editing, progress changes
  and resets, with isolation between individual users, teams and roads.
- Strengthened ownership and editing-lock checks for stage and road operations,
  transactional geometry updates, preview authorization and report output.
- Fixed invalid AJAX parameters when saving stage geometry, incomplete response
  structures, embedded clue/intro images, and first-load team game state.
- Improved recovery from communication errors and temporary connection loss.
  Loading overlays are cleared on completion, interruption and dialog opening.
- Corrected QR scanner loading, automatic device checks, hidden scanner panels
  and RequireJS integration with third-party libraries.
- Improved non-modal dialog focus and visibility, hover-only toolbar tooltips,
  touch gesture handling and 3D initial camera framing.
- Hid Moodle 5.3's sticky activity footer in the Bootstrap player to keep game
  controls accessible.

### Compatibility, development and documentation

- Added Moodle 4.5/5.3 Bootstrap compatibility paths and PHP compatibility fixes,
  including fixes for deprecated type hints and dynamic properties.
- Added GitHub Actions CI configured for Moodle 4.5 and 5.3 with PHP 8.3 and
  MariaDB, running syntax, coding-standard, plugin validation, savepoint and
  PHPUnit checks.
- Added regression tests for security, individual/team road isolation, caching,
  clue priority, completion, resets, OpenData, custom maps and player rendering.
- Applied Moodle coding-standard fixes and added VSCode PHPCS/PHPCBF tasks.
- Expanded Spanish and Greek translations, field help and editor tutorials.
- Added a bilingual static documentation website, with teacher tutorials, player
  guides, OpenData/georeferencing instructions and GPS/camera troubleshooting.
- Added reproducible Python tools for generating the animated hiker model.

### Upgrade and integration notes

- Run the Moodle plugin upgrade after replacing the plugin directory. It adds
  the stage own-clue fields, the out-of-sequence flag and a tracking index, and
  refreshes relevant caches. Out-of-sequence discovery is disabled by default.
- Earlier experimental player keys `cessium_player` and `mapbox_player` migrate
  to `cessium` and `mapbox`, including site player selections.
- Custom consumers of `mod_treasurehunt_user_progress` must use the current
  contract: start with `attemptid=0`, `roadtimestamp=0`, `initialize=true`, then
  return the server's cursors on later polls. `attemptid` replaces the former
  attempt timestamp cursor. Bundled web players use this contract.
- Europeana requires a site API key. Mapbox-hosted imagery requires a public
  access token. External map/data providers remain subject to their availability
  and terms. GPS and camera access require browser permission and HTTPS.
- The two 3D players and `edit2.php` are experimental and require a browser with
  suitable WebGL support. Bootstrap/OpenLayers remains available.

## v2.0.3 — 2025-12-09

### Fixed

- Corrected course reset cleanup so attempt records and related activity state
  are removed reliably, preventing residual or orphaned attempts. Thanks to Luca
  Bellani for reporting the issue.

For earlier releases, see the historical change log in [README.md](README.md#change-log).
