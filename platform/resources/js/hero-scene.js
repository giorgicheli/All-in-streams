const instances = new WeakMap();

export function initializeHeroScene(root = document) {
    instances.get(root)?.();

    const elements = [...root.querySelectorAll('[data-hero-scene]')];
    if (root instanceof Element && root.matches('[data-hero-scene]')) elements.unshift(root);
    if (!elements.length) return () => {};

    let disposed = false;
    const cleanups = [];

    import('three').then(THREE => {
        if (disposed) return;
        elements.forEach(element => cleanups.push(createHeroScene(THREE, element)));
    }).catch(() => {
        elements.forEach(element => { element.dataset.sceneState = 'fallback'; });
    });

    const cleanup = () => {
        disposed = true;
        cleanups.forEach(dispose => dispose());
    };
    instances.set(root, cleanup);
    return cleanup;
}

function createRibbonGeometry(THREE, points, width, thickness, segments, profileSegments, twists = 0) {
    const curve = new THREE.CatmullRomCurve3(points, true, 'centripetal');
    const frames = curve.computeFrenetFrames(segments, true);
    const positions = [];
    const indices = [];
    const normal = new THREE.Vector3();
    const binormal = new THREE.Vector3();
    const vertex = new THREE.Vector3();

    for (let step = 0; step <= segments; step++) {
        const progress = step / segments;
        const center = curve.getPointAt(progress);
        const twist = progress * Math.PI * 2 * twists;
        normal.copy(frames.normals[step]).multiplyScalar(Math.cos(twist))
            .addScaledVector(frames.binormals[step], Math.sin(twist));
        binormal.copy(frames.binormals[step]).multiplyScalar(Math.cos(twist))
            .addScaledVector(frames.normals[step], -Math.sin(twist));

        for (let side = 0; side <= profileSegments; side++) {
            const angle = side / profileSegments * Math.PI * 2;
            const cosine = Math.cos(angle);
            const sine = Math.sin(angle);
            // A rounded rectangular profile gives the ribbon broad reflective faces.
            const across = Math.sign(cosine) * Math.pow(Math.abs(cosine), 0.55) * width / 2;
            const depth = Math.sign(sine) * Math.pow(Math.abs(sine), 0.55) * thickness / 2;
            vertex.copy(center).addScaledVector(normal, across).addScaledVector(binormal, depth);
            positions.push(vertex.x, vertex.y, vertex.z);

            if (step < segments && side < profileSegments) {
                const first = step * (profileSegments + 1) + side;
                const next = first + profileSegments + 1;
                indices.push(first, first + 1, next, next, first + 1, next + 1);
            }
        }
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setIndex(indices);
    geometry.computeVertexNormals();
    const normals = geometry.getAttribute('normal');
    function joinNormals(first, second) {
        normal.fromBufferAttribute(normals, first);
        vertex.fromBufferAttribute(normals, second);
        normal.add(vertex).normalize();
        normals.setXYZ(first, normal.x, normal.y, normal.z);
        normals.setXYZ(second, normal.x, normal.y, normal.z);
    }
    for (let step = 0; step <= segments; step++) {
        joinNormals(step * (profileSegments + 1), step * (profileSegments + 1) + profileSegments);
    }
    for (let side = 0; side <= profileSegments; side++) {
        joinNormals(side, segments * (profileSegments + 1) + side);
    }
    return geometry;
}

function createStudioEnvironment(THREE, renderer, compact) {
    const canvas = document.createElement('canvas');
    canvas.width = compact ? 512 : 1024;
    canvas.height = canvas.width / 2;
    const context = canvas.getContext('2d');
    if (!context) return null;

    const width = canvas.width;
    const height = canvas.height;
    const background = context.createLinearGradient(0, 0, 0, height);
    background.addColorStop(0, '#27353f');
    background.addColorStop(0.48, '#111a23');
    background.addColorStop(0.72, '#060a10');
    background.addColorStop(1, '#202b38');
    context.fillStyle = background;
    context.fillRect(0, 0, width, height);

    for (const [left, breadth, color] of [
        [0.12, 0.12, 'rgba(126,243,243,0.95)'],
        [0.52, 0.17, 'rgba(231,242,255,0.98)'],
        [0.84, 0.06, 'rgba(179,184,255,0.82)'],
    ]) {
        const light = context.createLinearGradient(width * left, 0, width * (left + breadth), 0);
        light.addColorStop(0, 'rgba(255,255,255,0)');
        light.addColorStop(0.22, color);
        light.addColorStop(0.78, color);
        light.addColorStop(1, 'rgba(255,255,255,0)');
        context.fillStyle = light;
        context.fillRect(width * left, height * 0.08, width * breadth, height * 0.7);
    }
    context.fillStyle = '#070e16';
    context.fillRect(width * 0.36, height * 0.25, width * 0.045, height * 0.5);

    const source = new THREE.CanvasTexture(canvas);
    source.mapping = THREE.EquirectangularReflectionMapping;
    source.colorSpace = THREE.SRGBColorSpace;
    const generator = new THREE.PMREMGenerator(renderer);
    const environment = generator.fromEquirectangular(source);
    source.dispose();
    generator.dispose();
    return environment;
}

function createShadowTexture(THREE) {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 128;
    const context = canvas.getContext('2d');
    if (!context) return null;
    const gradient = context.createRadialGradient(64, 64, 8, 64, 64, 64);
    gradient.addColorStop(0, 'rgba(36,203,216,0.10)');
    gradient.addColorStop(0.45, 'rgba(20,150,166,0.04)');
    gradient.addColorStop(1, 'rgba(20,150,166,0)');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 128, 128);
    return new THREE.CanvasTexture(canvas);
}

function createHeroScene(THREE, element) {
    const canvas = element.querySelector('canvas');
    if (!canvas) return () => {};

    const compact = matchMedia('(max-width: 700px)').matches;
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const events = new AbortController();
    const options = { signal: events.signal, passive: true };
    const geometries = [];
    const materials = [];
    let renderer;
    let environment;
    let shadowTexture;
    let visibilityObserver;
    let sizeObserver;
    let frame = 0;
    let lastTime = 0;
    let elapsed = 0;
    let inView = true;
    let contextLost = false;
    let disposed = false;

    function showFallback() {
        element.classList.remove('is-rendered');
        element.dataset.sceneState = 'fallback';
    }

    function cleanup() {
        if (disposed) return;
        disposed = true;
        cancelAnimationFrame(frame);
        events.abort();
        visibilityObserver?.disconnect();
        sizeObserver?.disconnect();
        geometries.forEach(geometry => geometry.dispose());
        materials.forEach(material => material.dispose());
        shadowTexture?.dispose();
        environment?.dispose();
        renderer?.dispose();
        renderer?.forceContextLoss();
        canvas.width = canvas.height = 1;
        if (canvas.parentNode === element) canvas.replaceWith(canvas.cloneNode(false));
        showFallback();
    }

    try {
        const context = canvas.getContext('webgl2', {
            alpha: true,
            antialias: !compact,
            powerPreference: 'low-power',
            premultipliedAlpha: true,
        });
        if (!context) {
            showFallback();
            return cleanup;
        }

        renderer = new THREE.WebGLRenderer({ canvas, context, alpha: true, antialias: !compact });
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.12;
        renderer.setClearColor(0x000000, 0);

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 40);
        environment = createStudioEnvironment(THREE, renderer, compact);
        if (environment) scene.environment = environment.texture;
        scene.add(new THREE.HemisphereLight(0xd9eef7, 0x101923, 1.1));
        const keyLight = new THREE.DirectionalLight(0xedfaff, 3);
        keyLight.position.set(-3, 5, 6);
        scene.add(keyLight);
        const rimLight = new THREE.DirectionalLight(0xb9abff, 2);
        rimLight.position.set(4, 1, -2);
        scene.add(rimLight);

        const sculpture = new THREE.Group();
        const baseRotation = new THREE.Vector3(0.36, -0.28, -0.28);
        sculpture.rotation.set(baseRotation.x, baseRotation.y, baseRotation.z);
        sculpture.position.y = 0.12;
        scene.add(sculpture);

        const knotPoints = [];
        const linkPoints = [];
        for (let step = 0; step < 96; step++) {
            const angle = step / 96 * Math.PI * 2;
            const radius = 0.5 * (2 + Math.cos(3 * angle));
            knotPoints.push(new THREE.Vector3(
                radius * Math.cos(2 * angle),
                radius * Math.sin(2 * angle),
                0.5 * Math.sin(3 * angle),
            ));
            linkPoints.push(new THREE.Vector3(
                1.43 * Math.cos(angle),
                0.92 * Math.sin(angle),
                0.55 * Math.sin(angle),
            ));
        }

        const emeraldGeometry = createRibbonGeometry(THREE, knotPoints, 0.5, 0.15,
            compact ? 108 : 180, compact ? 12 : 16, 1);
        const emeraldMaterial = new THREE.MeshPhysicalMaterial({
            color: 0x148f9e, metalness: 0.64, roughness: 0.25,
            clearcoat: 1, clearcoatRoughness: 0.13, envMapIntensity: 1.25,
        });
        geometries.push(emeraldGeometry);
        materials.push(emeraldMaterial);
        sculpture.add(new THREE.Mesh(emeraldGeometry, emeraldMaterial));

        const limeGeometry = createRibbonGeometry(THREE, linkPoints, 0.28, 0.11,
            compact ? 80 : 128, 12);
        const limeMaterial = new THREE.MeshPhysicalMaterial({
            color: 0xa9b5ff, metalness: 0.38, roughness: 0.28,
            clearcoat: 1, clearcoatRoughness: 0.1, envMapIntensity: 0.9,
        });
        geometries.push(limeGeometry);
        materials.push(limeMaterial);
        const limeLink = new THREE.Mesh(limeGeometry, limeMaterial);
        limeLink.rotation.set(0.16, 0.32, -0.18);
        limeLink.position.set(0.12, -0.08, 0.12);
        sculpture.add(limeLink);

        shadowTexture = createShadowTexture(THREE);
        if (shadowTexture) {
            const shadowGeometry = new THREE.PlaneGeometry(3.8, 2.1);
            const shadowMaterial = new THREE.MeshBasicMaterial({
                map: shadowTexture, transparent: true, depthWrite: false,
            });
            geometries.push(shadowGeometry);
            materials.push(shadowMaterial);
            const shadow = new THREE.Mesh(shadowGeometry, shadowMaterial);
            shadow.rotation.x = -Math.PI / 2;
            shadow.position.set(0, -1.68, -0.15);
            scene.add(shadow);
        }

        const pointer = new THREE.Vector2();
        const pointerTarget = new THREE.Vector2();
        let scrollProgress = 0;
        const bounds = new THREE.Box3().setFromObject(sculpture);
        const sculptureSize = bounds.getSize(new THREE.Vector3());

        function requestFrame() {
            if (!frame && !disposed && !contextLost && inView && !document.hidden) {
                frame = requestAnimationFrame(render);
            }
        }

        function render(time) {
            frame = 0;
            if (disposed || contextLost || !inView || document.hidden) return;
            const delta = lastTime ? Math.min((time - lastTime) / 1000, 0.05) : 1 / 60;
            lastTime = time;
            if (!reducedMotion.matches) {
                elapsed += delta;
                pointer.lerp(pointerTarget, 1 - Math.exp(-delta * 6));
                sculpture.rotation.set(
                    baseRotation.x + Math.sin(elapsed * 0.35) * 0.045 + pointer.y * 0.045,
                    baseRotation.y + Math.sin(elapsed * 0.23) * 0.12 + pointer.x * 0.09,
                    baseRotation.z + Math.sin(elapsed * 0.27) * 0.025 + scrollProgress * 0.045,
                );
            } else {
                sculpture.rotation.set(baseRotation.x, baseRotation.y, baseRotation.z);
            }

            try {
                renderer.render(scene, camera);
                element.classList.add('is-rendered');
                element.dataset.sceneState = 'ready';
            } catch {
                contextLost = true;
                showFallback();
                return;
            }
            if (!reducedMotion.matches) requestFrame();
        }

        function resize() {
            const width = element.clientWidth;
            const height = element.clientHeight;
            if (!width || !height) return;
            const maxRatio = innerWidth <= 700 ? 1.25 : 1.5;
            renderer.setPixelRatio(Math.min(devicePixelRatio || 1, maxRatio));
            renderer.setSize(width, height, false);
            camera.aspect = width / height;
            const framing = innerWidth <= 700 ? 1.2 : 1.1;
            const distance = Math.max(sculptureSize.y, sculptureSize.x / camera.aspect)
                / (2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2))) * framing;
            camera.position.set(0, 0.05, distance);
            camera.lookAt(0, 0, 0);
            camera.updateProjectionMatrix();
            requestFrame();
        }

        document.addEventListener('pointermove', event => {
            if (reducedMotion.matches || event.pointerType !== 'mouse') return;
            pointerTarget.set(event.clientX / innerWidth * 2 - 1, event.clientY / innerHeight * 2 - 1);
        }, options);
        window.addEventListener('scroll', () => {
            if (reducedMotion.matches) return;
            scrollProgress = Math.min(1, Math.max(0, scrollY / innerHeight));
        }, options);
        document.addEventListener('visibilitychange', () => {
            cancelAnimationFrame(frame);
            frame = lastTime = 0;
            requestFrame();
        }, { signal: events.signal });
        reducedMotion.addEventListener('change', () => {
            cancelAnimationFrame(frame);
            frame = lastTime = 0;
            pointer.set(0, 0);
            pointerTarget.set(0, 0);
            requestFrame();
        }, { signal: events.signal });
        canvas.addEventListener('webglcontextlost', event => {
            event.preventDefault();
            contextLost = true;
            cancelAnimationFrame(frame);
            frame = lastTime = 0;
            showFallback();
        }, { signal: events.signal });
        canvas.addEventListener('webglcontextrestored', () => {
            contextLost = false;
            renderer.resetState();
            resize();
        }, { signal: events.signal });

        visibilityObserver = new IntersectionObserver(entries => {
            inView = entries[0].isIntersecting;
            if (!inView) {
                cancelAnimationFrame(frame);
                frame = lastTime = 0;
            } else requestFrame();
        }, { threshold: 0.01 });
        visibilityObserver.observe(element);
        sizeObserver = new ResizeObserver(resize);
        sizeObserver.observe(element);
        resize();
    } catch {
        cleanup();
    }

    return cleanup;
}
