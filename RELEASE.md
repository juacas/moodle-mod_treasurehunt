# Treasure Hunt v3.0.1 — Release summary

Treasure Hunt 3.0.1 improves the GPX tracking viewer with track replay and
faster heatmap rendering. It also adds visual feedback for successful and
unsuccessful stage events and corrects the Cesium player identifier.

| Release information | Value |
| --- | --- |
| Version | v3.0.1 |
| Date | 2026-10-26 |
| Maturity | Beta |
| Minimum Moodle version | 4.5 |

## Highlights

- **Replay recorded tracks:** play or pause GPX recordings and seek through
  them on a shared timeline. Individual and team progress, including successful
  stage completions, is shown alongside the moving tracks. The timeline supports
  pointer and keyboard navigation.
- **More responsive heatmaps:** a bounded tile cache reuses rendered heatmap
  tiles and invalidates them when the recorded points change.
- **Stage-event animations:** players show short success or failure animations
  when a stage is unlocked or an answer or location attempt fails. Teachers can
  preview the animations in the activity setting.
- **Cesium compatibility:** the player identifier is now consistently spelled
  `cesium`; existing site player and basemap selections using the former
  `cessium` identifier are migrated.

## Upgrade information

Upgrade the plugin through Moodle's normal administration process. Existing
site-level Cesium player and basemap selections are migrated automatically.
The complete list of changes since v3.0.0 is in [CHANGELOG.md](CHANGELOG.md).
