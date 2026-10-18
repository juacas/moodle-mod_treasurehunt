# Treasure Hunt v3.0.0 — Release summary

Treasure Hunt 3.0.0 adds new ways to build and play Moodle treasure hunts. It
introduces optional out-of-sequence discovery, experimental 3D game views,
OpenData-assisted stage creation, custom image georeferencing and a live
participant progress bar.

## Highlights

- **Out-of-sequence discovery:** allow selected stages to be found before their
  place in the road. Existing road order and completion tracking are preserved.
- **3D game views:** try the experimental Cesium globe or Mapbox player, with an
  animated hiker, map search and support for geographic custom maps.
- **OpenData:** search DBpedia, Wikidata and Europeana, review the results, then
  add them as map references or use their information to draft a stage.
- **Custom image maps:** align uploaded images with the map using move, scale,
  rotate and skew controls.
- **Improved editor and monitoring:** use fullscreen editing, copy stages between
  roads, preview valid roads, and show participant or team progress in the game.
- **Reliability:** improved team and road handling, progress caching, editor locks,
  QR scanning and error recovery.

## Upgrade information

- Version: **v3.0.0** · Moodle **4.5 or later** · Maturity: **Beta**.
- Upgrade the plugin through Moodle's normal administration flow. Existing hunts
  keep sequential play unless out-of-sequence discovery is enabled on stages.
- Europeana search needs a site API key. Mapbox-hosted maps need a Mapbox public
  access token. GPS and QR camera access need HTTPS and browser permission.
- Cesium, Mapbox and the geographic editor `edit2.php` are experimental features.

Integrations using `mod_treasurehunt_user_progress` must adopt the current cursor
protocol, which sends `attemptid` and `roadtimestamp` and initializes with
`initialize=true`.

[Read the complete v3.0.0 release notes](RELEASE_v3.0.0.md) for feature details,
upgrade notes and integration changes. See also the [changelog since v2.0.3](CHANGELOG.md).
