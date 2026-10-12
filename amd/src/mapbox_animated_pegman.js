// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Animated glTF marker rendered in Mapbox's shared WebGL context.
 *
 * @module mod_treasurehunt/mapbox_animated_pegman
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const HIKER_HEIGHT_METRES = 1.7;
const HIKER_HEIGHT_PIXELS = 100;
const ANIMATION_FRAME_MS = 33;
let libraryPromise;

/**
 * Load the locally bundled Three.js and glTF loader without registering an AMD module.
 *
 * @param {string} url Versioned URL of the local bundle.
 * @returns {Promise<Object>} Three.js and GLTFLoader.
 */
export const loadLibrary = (url) => {
    if (window.TreasurehuntThree) {
        return Promise.resolve(window.TreasurehuntThree);
    }
    if (!libraryPromise) {
        libraryPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.onload = () => window.TreasurehuntThree ? resolve(window.TreasurehuntThree) :
                reject(new Error('Three.js pegman bundle did not load'));
            script.onerror = () => reject(new Error('Three.js pegman bundle could not be loaded'));
            document.head.appendChild(script);
        }).catch((error) => {
            libraryPromise = null;
            throw error;
        });
    }
    return libraryPromise;
};

/**
 * @param {number} metresPerPixel Map scale at the marker.
 * @returns {number} Model height in metres for a 100-pixel on-screen hiker.
 */
export const sizeForMapScale = (metresPerPixel) =>
    HIKER_HEIGHT_PIXELS * metresPerPixel / HIKER_HEIGHT_METRES;

/**
 * Create a Mapbox custom layer because its native model layer does not play glTF animation clips.
 *
 * @param {Object} mapboxgl Mapbox GL JS namespace.
 * @param {Object} library Three.js and GLTFLoader namespace.
 * @param {string} url URL of the seven-animation model.
 * @param {Function} onError Asset loading error callback.
 * @returns {Object} Animated Mapbox custom layer.
 */
export const createLayer = (mapboxgl, library, url, onError) => {
    const {THREE, GLTFLoader} = library;
    return {
        id: 'pegman-model',
        type: 'custom',
        renderingMode: '3d',
        position: null,
        bearing: null,
        scale: 1,
        clipName: 'Descansando',
        model: null,
        mixer: null,
        clips: {},
        currentAction: null,
        active: false,

        /**
         * @param {Object} map Mapbox map.
         * @param {WebGLRenderingContext} gl Shared context.
         */
        onAdd(map, gl) {
            this.map = map;
            this.active = true;
            this.camera = new THREE.Camera();
            this.scene = new THREE.Scene();
            this.root = new THREE.Group();
            this.scene.add(this.root);
            this.scene.add(new THREE.AmbientLight(0xffffff, 1.7));
            this.scene.add(new THREE.HemisphereLight(0xffffff, 0xb9c8d0, 0.6));
            const sunlight = new THREE.DirectionalLight(0xffffff, 2.5);
            sunlight.position.set(0, 1, 2);
            this.scene.add(sunlight);
            this.renderer = new THREE.WebGLRenderer({canvas: map.getCanvas(), context: gl, antialias: true});
            this.renderer.autoClear = false;
            this.renderer.outputColorSpace = THREE.SRGBColorSpace;
            const loader = new GLTFLoader();
            loader.load(url, (gltf) => {
                if (!this.active) {
                    return;
                }
                gltf.scene.traverse((part) => {
                    if (part.isMesh) {
                        part.frustumCulled = false;
                    }
                });
                this.root.add(gltf.scene);
                this.model = gltf.scene;
                this.mixer = new THREE.AnimationMixer(gltf.scene);
                this.clips = Object.fromEntries(gltf.animations.map((clip) => [clip.name, clip]));
                this.playClip();
                map.triggerRepaint();
            }, undefined, onError);
        },

        /** Activate exactly one named clip, with a short transition between poses. */
        playClip() {
            if (!this.mixer || !this.clips[this.clipName]) {
                return;
            }
            const next = this.mixer.clipAction(this.clips[this.clipName]);
            if (next === this.currentAction) {
                return;
            }
            next.reset().play();
            if (this.currentAction) {
                next.crossFadeFrom(this.currentAction, 0.2, true);
            }
            this.currentAction = next;
            this.map?.triggerRepaint();
        },

        /** @param {string} name Name of a clip in the GLB. */
        setClip(name) {
            if (name !== this.clipName) {
                this.clipName = name;
                this.playClip();
            }
        },

        /** Release the renderer when Mapbox replaces its style. */
        onRemove() {
            this.active = false;
            window.clearTimeout(this.repaintTimer);
            this.renderer?.dispose();
            this.map = null;
        },

        /** @param {Array<number>|null} coordinates Marker location. */
        setPosition(coordinates) {
            this.position = coordinates;
            this.map?.triggerRepaint();
        },

        /** @param {number|null} bearing Direction clockwise from north, or null for the default orientation. */
        setBearing(bearing) {
            this.bearing = bearing;
            this.map?.triggerRepaint();
        },

        /** @param {number} size Model scale in physical metres. */
        setScale(size) {
            this.scale = size;
            this.map?.triggerRepaint();
        },

        /**
         * @param {WebGLRenderingContext} gl Shared context.
         * @param {Array<number>} matrix Map projection matrix.
         */
        render(gl, matrix) {
            if (!this.active || !this.position) {
                return;
            }
            if (!this.model) {
                return;
            }
            const location = mapboxgl.MercatorCoordinate.fromLngLat(this.position, 0);
            const scale = location.meterInMercatorCoordinateUnits() * this.scale;
            const transform = new THREE.Matrix4().makeTranslation(location.x, location.y, location.z)
                .scale(new THREE.Vector3(scale, -scale, scale))
                .multiply(new THREE.Matrix4().makeRotationX(Math.PI / 2));
            this.root.rotation.y = this.bearing === null ? Math.PI : Math.PI - this.bearing * Math.PI / 180;
            this.camera.projectionMatrix = new THREE.Matrix4().fromArray(matrix).multiply(transform);
            const now = performance.now();
            const elapsed = this.lastFrame ? Math.min(0.1, (now - this.lastFrame) / 1000) : 0;
            this.lastFrame = now;
            this.mixer?.update(elapsed);
            this.renderer.resetState();
            this.renderer.render(this.scene, this.camera);
            if (!this.repaintTimer) {
                this.repaintTimer = window.setTimeout(() => {
                    this.repaintTimer = null;
                    this.map?.triggerRepaint();
                }, ANIMATION_FRAME_MS);
            }
        },
    };
};
