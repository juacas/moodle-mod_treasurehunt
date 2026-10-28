(function() {
    'use strict';

    document.querySelectorAll('.hero-video-launch').forEach(function(button) {
        button.addEventListener('click', function() {
            var iframe = document.createElement('iframe');
            iframe.className = 'hero-video-frame';
            iframe.title = button.dataset.videoTitle;
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + button.dataset.videoId + '?autoplay=1';
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
            iframe.allowFullscreen = true;
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            button.parentElement.classList.add('is-playing');
            button.replaceWith(iframe);
            iframe.focus();
        });
    });

    if (!window.TreasurehuntThree || !window.WebGLRenderingContext) {
        return;
    }

    var THREE = window.TreasurehuntThree.THREE;
    var GLTFLoader = window.TreasurehuntThree.GLTFLoader;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var clipOrder = [
        'Caminando', 'Descansando', 'Apuntando', 'Desorientado',
        'Caminando_Apuntando', 'Caminando_Apuntando_Flecha', 'Apuntando_Flecha',
        'Caminando_Desorientado', 'buscando', 'buscando_brújula',
        'sentanse', 'Sentado saludando', 'Levantarse', 'sentarysaludar'
    ];
    var movingClips = new Set([
        'Caminando', 'Caminando_Apuntando', 'Caminando_Apuntando_Flecha',
        'Caminando_Desorientado', 'buscando_brújula'
    ]);

    document.querySelectorAll('[data-pegman-canvas]').forEach(function(canvas) {
        var inHeader = canvas.hasAttribute('data-pegman-header');
        var scene = new THREE.Scene();
        var headerWorld = inHeader ? new THREE.Group() : null;
        if (headerWorld) {
            scene.add(headerWorld);
        }
        var headerFov = 35;
        var referenceDepth = 6;
        var approachDepth = referenceDepth * 0.8 / 1.5;
        var camera = new THREE.PerspectiveCamera(inHeader ? headerFov : 36, 1, 0.1, inHeader ? 1000 : 100);
        if (inHeader) {
            var cameraHeight = referenceDepth * Math.tan((headerFov / 2 - 6) * Math.PI / 180);
            camera.position.set(0, cameraHeight, 0);
            camera.lookAt(0, cameraHeight + referenceDepth * Math.tan(6 * Math.PI / 180), -referenceDepth);
        } else {
            camera.position.set(3.5, 2.5, 6);
            camera.lookAt(0, 1.35, 0);
        }

        var renderer;
        try {
            renderer = new THREE.WebGLRenderer({canvas: canvas, alpha: true, antialias: true});
        } catch (error) {
            canvas.parentElement.classList.add('model-unavailable');
            return;
        }
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        if (THREE.SRGBColorSpace) {
            renderer.outputColorSpace = THREE.SRGBColorSpace;
        }
        scene.add(new THREE.HemisphereLight(0xffffff, 0xd2dfca, 3.2));
        var key = new THREE.DirectionalLight(0xffe8bc, 2.5);
        key.position.set(3, 6, 5);
        scene.add(key);
        var fill = new THREE.DirectionalLight(0xd6eeea, 1.7);
        fill.position.set(-4, 2, -3);
        scene.add(fill);
        if (inHeader) {
            scene.fog = new THREE.Fog(0x315342, 42, 150);
            var groundGrid = new THREE.GridHelper(255, 51, 0xd3e8bb, 0x9abc9f);
            groundGrid.position.set(0, 0, -112.5);
            var gridMaterials = Array.isArray(groundGrid.material) ? groundGrid.material : [groundGrid.material];
            gridMaterials.forEach(function(material) {
                material.transparent = true;
                material.opacity = 0.55;
                material.depthWrite = false;
            });
            headerWorld.add(groundGrid);
        }

        var model;
        var baseModelHeight;
        var mixer;
        var actions = [];
        var currentAction;
        var clipIndex = 0;
        var clipTime = 0;
        var clipLength = 4;
        var visible = true;
        var travel = 0;
        var travelDirection = 1;
        var headerDepth = referenceDepth;
        var pathAngle = 0;
        var pathAngleTimer = 0;
        var approachTimer = 0;
        var approachState = 'idle';
        var approachProgress = 0;
        var approachStartDepth = referenceDepth;
        var approachStartX = 0.5;
        var lastHeaderX = 0.5;
        var viewYaw = 0;
        var clock = new THREE.Clock();

        if (inHeader) {
            var nav = canvas.parentElement.parentElement;
            var drag = null;
            nav.addEventListener('pointerdown', function(event) {
                if (event.button !== 0 || event.target.closest('a, button, input, .dropdown-menu')) {
                    return;
                }
                if (nav.querySelector('.navbar-collapse.show')) {
                    return;
                }
                drag = {pointerid: event.pointerId, startx: event.clientX, startyaw: viewYaw};
                if (nav.setPointerCapture) {
                    nav.setPointerCapture(event.pointerId);
                }
            });
            nav.addEventListener('pointermove', function(event) {
                if (!drag || drag.pointerid !== event.pointerId) {
                    return;
                }
                var width = canvas.parentElement.clientWidth || 1;
                var rotation = drag.startyaw + ((event.clientX - drag.startx) / width) * Math.PI / 3;
                viewYaw = Math.max(-Math.PI / 6, Math.min(Math.PI / 6, rotation));
                headerWorld.rotation.y = viewYaw;
            });
            var stopDrag = function(event) {
                if (drag && drag.pointerid === event.pointerId) {
                    drag = null;
                    if (nav.hasPointerCapture && nav.hasPointerCapture(event.pointerId)) {
                        nav.releasePointerCapture(event.pointerId);
                    }
                }
            };
            nav.addEventListener('pointerup', stopDrag);
            nav.addEventListener('pointercancel', stopDrag);
        }

        var resize = function() {
            var width = canvas.clientWidth || (inHeader ? 1140 : 400);
            var height = canvas.clientHeight || (inHeader ? 260 : 390);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height, false);
            if (model && inHeader && baseModelHeight > 0) {
                var clipHeight = canvas.parentElement.clientHeight || 94;
                model.scale.setScalar((3 * clipHeight / height) / baseModelHeight);
                model.position.y = 0;
                var bounds = new THREE.Box3().setFromObject(model);
                model.position.y = -bounds.min.y;
            }
        };
        window.addEventListener('resize', resize);
        resize();

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function(entries) {
                visible = entries[0].isIntersecting;
            }).observe(canvas);
        }

        var playClip = function(index) {
            if (!actions.length || reducedMotion.matches) {
                return;
            }
            clipIndex = index % actions.length;
            var next = actions[clipIndex];
            if (currentAction && currentAction !== next.action) {
                currentAction.fadeOut(0.35);
            }
            next.action.reset().fadeIn(0.35).play();
            currentAction = next.action;
            clipLength = Math.max(3.2, next.clip.duration * 1.05);
            clipTime = 0;
        };

        new GLTFLoader().load(canvas.dataset.model, function(gltf) {
            model = gltf.scene;
            var bounds = new THREE.Box3().setFromObject(model);
            var size = bounds.getSize(new THREE.Vector3());
            baseModelHeight = size.y;
            if (size.y > 0) {
                if (inHeader) {
                    var renderHeight = canvas.clientHeight || 260;
                    var clipHeight = canvas.parentElement.clientHeight || 94;
                    model.scale.setScalar((3 * clipHeight / renderHeight) / size.y);
                } else {
                    model.scale.setScalar(3 / size.y);
                }
            }
            bounds.setFromObject(model);
            model.position.y = -bounds.min.y;
            if (headerWorld) {
                headerWorld.add(model);
            } else {
                scene.add(model);
            }
            if (gltf.animations.length) {
                mixer = new THREE.AnimationMixer(model);
                clipOrder.forEach(function(name) {
                    var clip = gltf.animations.find(function(item) {
                        return item.name === name;
                    });
                    if (clip) {
                        actions.push({clip: clip, action: mixer.clipAction(clip)});
                    }
                });
                if (actions.length) {
                    playClip(inHeader ? 0 : 8);
                }
            }
        }, undefined, function() {
            canvas.parentElement.classList.add('model-unavailable');
        });

        var draw = function() {
            requestAnimationFrame(draw);
            var delta = Math.min(clock.getDelta(), 0.05);
            if (!visible || document.hidden) {
                return;
            }
            if (mixer && !reducedMotion.matches) {
                mixer.update(delta);
                clipTime += delta;
                if (clipTime >= clipLength) {
                    playClip(clipIndex + 1);
                }
            }
            if (model) {
                if (inHeader) {
                    var clipName = actions[clipIndex] ? actions[clipIndex].clip.name : '';
                    var isWalking = movingClips.has(clipName);
                    if (!reducedMotion.matches && isWalking) {
                        pathAngleTimer += delta;
                        approachTimer += delta;
                        if (pathAngleTimer >= 3) {
                            pathAngleTimer %= 3;
                            pathAngle = (Math.random() * 2 - 1) * Math.PI / 5;
                        }
                        if (approachState === 'idle' && (approachTimer >= 10 || headerDepth < referenceDepth * 0.5 || headerDepth > referenceDepth * 1.5)) {
                            approachTimer = 0;
                            approachState = 'inbound';
                            approachProgress = 0;
                            approachStartDepth = headerDepth;
                            approachStartX = travel;
                        }

                        if (approachState === 'inbound') {
                            approachProgress = Math.min(1, approachProgress + delta / 3);
                            headerDepth = approachStartDepth + (approachDepth - approachStartDepth) * approachProgress;
                            travel = approachStartX + (0.5 - approachStartX) * approachProgress;
                            if (approachProgress >= 1) {
                                approachState = 'hold';
                                approachProgress = 0;
                            }
                        } else if (approachState === 'hold') {
                            approachProgress += delta;
                            headerDepth = approachDepth;
                            travel = 0.5;
                            if (approachProgress >= 3) {
                                approachState = 'outbound';
                                approachProgress = 0;
                            }
                        } else if (approachState === 'outbound') {
                            approachProgress = Math.min(1, approachProgress + delta / 3);
                            headerDepth = approachDepth + (referenceDepth - approachDepth) * approachProgress;
                            travel = 0.5 + ((travelDirection > 0 ? 0.1 : 0.9) - 0.5) * approachProgress;
                            if (approachProgress >= 1) {
                                approachState = 'idle';
                                approachProgress = 0;
                                travelDirection *= -1;
                            }
                        } else {
                            var oldTravel = travel;
                            travel += travelDirection * delta / 26;
                            if (travel >= 0.9 || travel <= 0.1) {
                                travel = Math.max(0.1, Math.min(0.9, travel));
                                travelDirection *= -1;
                                pathAngle = (Math.random() * 2 - 1) * Math.PI / 5;
                            }
                            var horizontalChange = (travel - oldTravel) * 2 * headerDepth * Math.tan(headerFov * Math.PI / 360) * camera.aspect;
                            headerDepth = Math.max(referenceDepth * 0.42, Math.min(referenceDepth * 1.58,
                                headerDepth + horizontalChange * Math.tan(pathAngle)));
                        }
                    }
                    if (reducedMotion.matches) {
                        travel = 0.5;
                        headerDepth = referenceDepth;
                    }
                    var frustumWidth = 2 * headerDepth * Math.tan(headerFov * Math.PI / 360) * camera.aspect;
                    model.position.x = (travel - 0.5) * frustumWidth;
                    model.position.z = -headerDepth;
                    if (isWalking && Math.abs(travel - lastHeaderX) > 0.00001) {
                        model.rotation.y = travel > lastHeaderX ? Math.PI / 2 : -Math.PI / 2;
                    } else if (!isWalking) {
                        model.rotation.y = 0;
                    }
                    lastHeaderX = travel;
                } else if (!reducedMotion.matches) {
                    model.rotation.y += delta * 0.12;
                }
            }
            renderer.render(scene, camera);
        };
        draw();
    });
})();
