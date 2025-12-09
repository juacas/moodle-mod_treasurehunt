<!--
Maintenance release: fixes a small bug in the course reset procedure.
Do not modify this comment except to update version/date and brief notes.
-->

# Release Notes

## Version 2.0.3 (Maintenance)

### Bug Fixes
- Fixed a small issue in the "Reset course" procedure: Treasure Hunt activity data (attempt records and related activity state) is now reliably cleared during a course reset. This prevents residual or orphaned treasurehunt_attempts and ensures the activity returns to a clean initial state after reset. Thanks to Luca Bellani for reporting this issue.

### Version Information
Compatibilty : Moodle 4.5 and later. Limited for limitation of maintenance efforts.

**Version Name:** v2.0.3
**Version Number:** 2025120900
**Release Date:** 2025-12-09
