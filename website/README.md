# Treasure Hunt website

Static, bilingual documentation and presentation for the Moodle Treasure Hunt activity. English pages live in this folder; Spanish pages with the same filenames live in `es/`.

## Build and check

Run from this folder:

```sh
python3 build.py
python3 check.py
```

The generated HTML works from a static web server without a build service. Bootstrap, the page images, the chest video, and the Pegman viewer scripts and model are served from `assets/`; the site does not load a JavaScript or CSS CDN at runtime. External links lead to project or source pages only when a visitor selects them.

Edit page content in `build.py`, then regenerate both languages. Add each new page in English and Spanish, keeping its filename the same. The navigation and footer are generated from the same page definitions.

## Images and assets

- `main-1100x733.jpg`, `users-800x533.jpg`, `user-progress-800x533.png` and the animated tutorial examples are bundled as local images.
- `captures/` contains current screenshots taken from a local Moodle instance with headless Chromium. The captures are cropped to the relevant controls; the English text explains that this instance used a Spanish interface. Player captures show test activity data only.
- `logo-cuatricromia-negativo.png` and `eduvalab-logo.png` come from `mod/quest/website/assets/images`.
- `treasurehunt_chest.mp4`, `pegman_hiker_all_animations_matte.glb` and the local Three.js viewer bundle come from this Moodle plugin.

The Pegman model runs inside the main navigation bar on every page, which acts as a small window into a 3D scene. Walking clips move along a horizontal route and periodically approach the viewer; static clips hold their position. Drag in an empty part of the navigation to rotate the scene up to 30 degrees in either direction. A perspective floor grid fades toward the horizon. The renderer pauses when outside the viewport and respects reduced-motion preferences.

The tutorial captures are illustrative. Moodle theme, language, plugin settings and version may change individual labels or control positions.
