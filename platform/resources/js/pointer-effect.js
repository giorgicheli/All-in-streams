const instances = new WeakMap();

const vertexSource = `#version 300 es
precision highp float;
out vec2 vUv;
void main() {
    vec2 corner = vec2(float((gl_VertexID << 1) & 2), float(gl_VertexID & 2));
    vUv = corner;
    gl_Position = vec4(corner * 2.0 - 1.0, 0.0, 1.0);
}`;

const fragmentHeader = `#version 300 es
precision highp float;
precision highp sampler2D;
in vec2 vUv;
out vec4 result;
`;

const fragmentSources = {
    splat: `
        uniform sampler2D uSource;
        uniform vec2 uPoint;
        uniform vec3 uValue;
        uniform float uAspect;
        uniform float uRadius;
        void main() {
            vec2 offset = vUv - uPoint;
            offset.x *= uAspect;
            float amount = exp(-dot(offset, offset) / uRadius);
            result = vec4(texture(uSource, vUv).rgb + uValue * amount, 1.0);
        }`,
    advect: `
        uniform sampler2D uSource;
        uniform sampler2D uVelocity;
        uniform vec2 uTexel;
        uniform float uDt;
        uniform float uDecay;
        void main() {
            vec2 flow = texture(uVelocity, vUv).xy;
            vec2 previous = clamp(vUv - flow * uTexel * uDt, uTexel * 0.5, 1.0 - uTexel * 0.5);
            result = texture(uSource, previous) * exp(-uDecay * uDt);
        }`,
    curl: `
        uniform sampler2D uVelocity;
        uniform vec2 uTexel;
        void main() {
            float left = texture(uVelocity, vUv - vec2(uTexel.x, 0.0)).y;
            float right = texture(uVelocity, vUv + vec2(uTexel.x, 0.0)).y;
            float bottom = texture(uVelocity, vUv - vec2(0.0, uTexel.y)).x;
            float top = texture(uVelocity, vUv + vec2(0.0, uTexel.y)).x;
            result = vec4(0.5 * (right - left - top + bottom), 0.0, 0.0, 1.0);
        }`,
    swirl: `
        uniform sampler2D uVelocity;
        uniform sampler2D uSource;
        uniform vec2 uTexel;
        uniform float uDt;
        void main() {
            float left = abs(texture(uSource, vUv - vec2(uTexel.x, 0.0)).r);
            float right = abs(texture(uSource, vUv + vec2(uTexel.x, 0.0)).r);
            float bottom = abs(texture(uSource, vUv - vec2(0.0, uTexel.y)).r);
            float top = abs(texture(uSource, vUv + vec2(0.0, uTexel.y)).r);
            vec2 gradient = vec2(top - bottom, right - left);
            gradient /= max(length(gradient), 0.0001);
            float rotation = texture(uSource, vUv).r;
            vec2 force = gradient * rotation * 12.0;
            force.y = -force.y;
            result = vec4(texture(uVelocity, vUv).xy + force * uDt, 0.0, 1.0);
        }`,
    divergence: `
        uniform sampler2D uVelocity;
        uniform vec2 uTexel;
        void main() {
            float left = texture(uVelocity, vUv - vec2(uTexel.x, 0.0)).x;
            float right = texture(uVelocity, vUv + vec2(uTexel.x, 0.0)).x;
            float bottom = texture(uVelocity, vUv - vec2(0.0, uTexel.y)).y;
            float top = texture(uVelocity, vUv + vec2(0.0, uTexel.y)).y;
            result = vec4(0.5 * (right - left + top - bottom), 0.0, 0.0, 1.0);
        }`,
    pressure: `
        uniform sampler2D uSource;
        uniform sampler2D uDivergence;
        uniform vec2 uTexel;
        void main() {
            float left = texture(uSource, vUv - vec2(uTexel.x, 0.0)).r;
            float right = texture(uSource, vUv + vec2(uTexel.x, 0.0)).r;
            float bottom = texture(uSource, vUv - vec2(0.0, uTexel.y)).r;
            float top = texture(uSource, vUv + vec2(0.0, uTexel.y)).r;
            float divergence = texture(uDivergence, vUv).r;
            result = vec4((left + right + bottom + top - divergence) * 0.25, 0.0, 0.0, 1.0);
        }`,
    project: `
        uniform sampler2D uSource;
        uniform sampler2D uVelocity;
        uniform vec2 uTexel;
        void main() {
            float left = texture(uSource, vUv - vec2(uTexel.x, 0.0)).r;
            float right = texture(uSource, vUv + vec2(uTexel.x, 0.0)).r;
            float bottom = texture(uSource, vUv - vec2(0.0, uTexel.y)).r;
            float top = texture(uSource, vUv + vec2(0.0, uTexel.y)).r;
            vec2 velocity = texture(uVelocity, vUv).xy - vec2(right - left, top - bottom) * 0.5;
            result = vec4(velocity, 0.0, 1.0);
        }`,
    display: `
        uniform sampler2D uSource;
        uniform vec2 uTexel;
        void main() {
            vec3 color = max(texture(uSource, vUv).rgb, vec3(0.0));
            float dx = length(texture(uSource, vUv + vec2(uTexel.x, 0.0)).rgb)
                - length(texture(uSource, vUv - vec2(uTexel.x, 0.0)).rgb);
            float dy = length(texture(uSource, vUv + vec2(0.0, uTexel.y)).rgb)
                - length(texture(uSource, vUv - vec2(0.0, uTexel.y)).rgb);
            color *= 0.78 + 0.22 / sqrt(1.0 + 48.0 * (dx * dx + dy * dy));
            float alpha = clamp(max(color.r, max(color.g, color.b)), 0.0, 1.0);
            result = vec4(color, alpha);
        }`,
};

function createFluid(canvas) {
    const gl = canvas.getContext('webgl2', {
        alpha: true, depth: false, stencil: false, antialias: false,
        premultipliedAlpha: true, powerPreference: 'low-power',
    });
    if (!gl) return null;

    const textures = [];
    const framebuffers = [];
    const shaders = [];
    const programs = [];
    let velocity;
    let dye;
    let pressure;
    let divergence;
    let curl;
    let simulationWidth = 1;
    let simulationHeight = 1;
    let disposed = false;

    function releaseTargets() {
        textures.splice(0).forEach(texture => gl.deleteTexture(texture));
        framebuffers.splice(0).forEach(framebuffer => gl.deleteFramebuffer(framebuffer));
    }

    function dispose() {
        if (disposed) return;
        disposed = true;
        releaseTargets();
        programs.forEach(program => gl.deleteProgram(program));
        shaders.forEach(shader => gl.deleteShader(shader));
        gl.getExtension('WEBGL_lose_context')?.loseContext();
    }

    function shader(type, source) {
        const handle = gl.createShader(type);
        if (!handle) throw new Error('Pointer shader unavailable');
        shaders.push(handle);
        gl.shaderSource(handle, source);
        gl.compileShader(handle);
        if (!gl.getShaderParameter(handle, gl.COMPILE_STATUS)) throw new Error('Pointer shader unsupported');
        return handle;
    }

    function target(width, height) {
        const texture = gl.createTexture();
        const framebuffer = gl.createFramebuffer();
        if (texture) textures.push(texture);
        if (framebuffer) framebuffers.push(framebuffer);
        if (!texture || !framebuffer) throw new Error('Pointer buffer unavailable');
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA16F, width, height, 0, gl.RGBA, gl.HALF_FLOAT, null);
        gl.bindFramebuffer(gl.FRAMEBUFFER, framebuffer);
        gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texture, 0);
        if (gl.checkFramebufferStatus(gl.FRAMEBUFFER) !== gl.FRAMEBUFFER_COMPLETE) {
            throw new Error('Pointer float buffer unsupported');
        }
        gl.viewport(0, 0, width, height);
        gl.clear(gl.COLOR_BUFFER_BIT);
        return { texture, framebuffer, width, height };
    }

    function pair(width, height) {
        return {
            read: target(width, height),
            write: target(width, height),
            swap() { [this.read, this.write] = [this.write, this.read]; },
        };
    }

    function use(program, output) {
        gl.useProgram(program.handle);
        gl.bindFramebuffer(gl.FRAMEBUFFER, output?.framebuffer ?? null);
        gl.viewport(0, 0, output?.width ?? canvas.width, output?.height ?? canvas.height);
    }

    function sample(program, uniform, input, unit) {
        gl.activeTexture(gl.TEXTURE0 + unit);
        gl.bindTexture(gl.TEXTURE_2D, input.texture);
        gl.uniform1i(program[uniform], unit);
    }

    function draw() { gl.drawArrays(gl.TRIANGLES, 0, 3); }

    try {
        if (!gl.getExtension('EXT_color_buffer_float')) throw new Error('Pointer float rendering unsupported');
        gl.clearColor(0, 0, 0, 0);
        gl.disable(gl.BLEND);
        const vertex = shader(gl.VERTEX_SHADER, vertexSource);
        const passes = {};
        const uniformNames = ['uSource', 'uVelocity', 'uTexel', 'uDt', 'uDecay', 'uPoint', 'uValue', 'uAspect', 'uRadius', 'uDivergence'];
        for (const [name, source] of Object.entries(fragmentSources)) {
            const handle = gl.createProgram();
            if (!handle) throw new Error('Pointer program unavailable');
            programs.push(handle);
            gl.attachShader(handle, vertex);
            gl.attachShader(handle, shader(gl.FRAGMENT_SHADER, fragmentHeader + source));
            gl.linkProgram(handle);
            if (!gl.getProgramParameter(handle, gl.LINK_STATUS)) throw new Error('Pointer program unsupported');
            passes[name] = { handle };
            uniformNames.forEach(uniform => { passes[name][uniform] = gl.getUniformLocation(handle, uniform); });
        }

        function clear() {
            framebuffers.forEach(framebuffer => {
                gl.bindFramebuffer(gl.FRAMEBUFFER, framebuffer);
                gl.clear(gl.COLOR_BUFFER_BIT);
            });
            gl.bindFramebuffer(gl.FRAMEBUFFER, null);
            gl.clear(gl.COLOR_BUFFER_BIT);
        }

        function resize() {
            releaseTargets();
            const aspect = innerWidth / Math.max(1, innerHeight);
            const ratio = Math.min(devicePixelRatio || 1, 1.25, 1800 / Math.max(innerWidth, innerHeight));
            canvas.width = Math.max(1, Math.round(innerWidth * ratio));
            canvas.height = Math.max(1, Math.round(innerHeight * ratio));
            simulationWidth = Math.round(128 * Math.max(1, aspect));
            simulationHeight = Math.round(128 / Math.min(1, aspect));
            const dyeWidth = Math.round(384 * Math.max(1, aspect));
            const dyeHeight = Math.round(384 / Math.min(1, aspect));
            velocity = pair(simulationWidth, simulationHeight);
            pressure = pair(simulationWidth, simulationHeight);
            dye = pair(dyeWidth, dyeHeight);
            divergence = target(simulationWidth, simulationHeight);
            curl = target(simulationWidth, simulationHeight);
            clear();
        }

        function splat(x, y, dx, dy, color, strength = 1) {
            const program = passes.splat;
            const aspect = innerWidth / innerHeight;
            gl.useProgram(program.handle);
            gl.uniform2f(program.uPoint, x / innerWidth, 1 - y / innerHeight);
            gl.uniform1f(program.uAspect, aspect);
            gl.uniform1f(program.uRadius, 0.0018 * Math.max(1, aspect));
            use(program, velocity.write);
            sample(program, 'uSource', velocity.read, 0);
            gl.uniform3f(program.uValue, dx / innerWidth * 2600, -dy / innerHeight * 2600, 0);
            draw();
            velocity.swap();
            use(program, dye.write);
            sample(program, 'uSource', dye.read, 0);
            gl.uniform3f(program.uValue, color[0] * strength, color[1] * strength, color[2] * strength);
            draw();
            dye.swap();
        }

        function step(dt) {
            let program = passes.advect;
            use(program, velocity.write);
            sample(program, 'uSource', velocity.read, 0);
            sample(program, 'uVelocity', velocity.read, 1);
            gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
            gl.uniform1f(program.uDt, dt);
            gl.uniform1f(program.uDecay, 2.02);
            draw();
            velocity.swap();

            program = passes.curl;
            use(program, curl);
            sample(program, 'uVelocity', velocity.read, 0);
            gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
            draw();

            program = passes.swirl;
            use(program, velocity.write);
            sample(program, 'uVelocity', velocity.read, 0);
            sample(program, 'uSource', curl, 1);
            gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
            gl.uniform1f(program.uDt, dt);
            draw();
            velocity.swap();

            program = passes.divergence;
            use(program, divergence);
            sample(program, 'uVelocity', velocity.read, 0);
            gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
            draw();

            program = passes.pressure;
            for (let iteration = 0; iteration < 8; iteration++) {
                use(program, pressure.write);
                sample(program, 'uSource', pressure.read, 0);
                sample(program, 'uDivergence', divergence, 1);
                gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
                draw();
                pressure.swap();
            }

            program = passes.project;
            use(program, velocity.write);
            sample(program, 'uSource', pressure.read, 0);
            sample(program, 'uVelocity', velocity.read, 1);
            gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
            draw();
            velocity.swap();

            program = passes.advect;
            use(program, dye.write);
            sample(program, 'uSource', dye.read, 0);
            sample(program, 'uVelocity', velocity.read, 1);
            gl.uniform2f(program.uTexel, 1 / simulationWidth, 1 / simulationHeight);
            gl.uniform1f(program.uDt, dt);
            gl.uniform1f(program.uDecay, 2.02);
            draw();
            dye.swap();

            program = passes.display;
            use(program, null);
            sample(program, 'uSource', dye.read, 0);
            gl.uniform2f(program.uTexel, 1 / dye.read.width, 1 / dye.read.height);
            draw();
        }

        resize();
        return { splat, step, clear, resize, dispose };
    } catch {
        dispose();
        return null;
    }
}

function mountPointerEffect() {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-effect';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.dataset.pointerEffect = '';
    const fluid = createFluid(canvas);
    if (!fluid) return () => {};

    document.body.appendChild(canvas);
    const events = new AbortController();
    const options = { signal: events.signal, passive: true };
    let frame = 0;
    let resizeFrame = 0;
    let previousTime = 0;
    let lastMotion = 0;
    let lastPoint = null;
    let pending = null;
    let color = [0.02, 0.16, 0.14];
    let colorChanged = 0;
    let disposed = false;

    const isEditable = element => element instanceof Element
        && Boolean(element.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])'));

    function stop() {
        cancelAnimationFrame(frame);
        frame = previousTime = 0;
        lastPoint = pending = null;
        canvas.classList.remove('is-active');
        canvas.dataset.pointerState = 'sleeping';
        fluid.clear();
    }

    function nextColor(now) {
        if (now - colorChanged < 125) return color;
        colorChanged = now;
        const hue = Math.random();
        color = [0, 2 / 3, 1 / 3].map(offset => {
            const angle = ((hue + offset) % 1) * 6;
            return Math.max(0, Math.min(1, Math.abs(angle - 3) - 1)) * 0.16;
        });
        return color;
    }

    function draw(now) {
        frame = 0;
        if (disposed || document.hidden || isEditable(document.activeElement) || now - lastMotion > 3200) {
            stop();
            return;
        }
        const dt = previousTime ? Math.min((now - previousTime) / 1000, 1 / 30) : 1 / 60;
        previousTime = now;
        if (pending) {
            const stroke = pending;
            pending = null;
            const parts = Math.min(3, Math.max(1, Math.ceil(Math.hypot(stroke.dx, stroke.dy) / 30)));
            for (let part = 1; part <= parts; part++) {
                const progress = part / parts;
                fluid.splat(stroke.x - stroke.dx * (1 - progress), stroke.y - stroke.dy * (1 - progress),
                    stroke.dx / parts, stroke.dy / parts, stroke.color, stroke.strength);
            }
        }
        fluid.step(dt);
        frame = requestAnimationFrame(draw);
    }

    function wake() {
        canvas.classList.add('is-active');
        canvas.dataset.pointerState = 'active';
        if (!frame) frame = requestAnimationFrame(draw);
    }

    document.addEventListener('pointermove', event => {
        if (event.pointerType !== 'mouse' || !event.isPrimary || document.hidden
            || isEditable(event.target) || isEditable(document.activeElement)) return;
        const now = performance.now();
        if (lastPoint && now - lastMotion < 250) {
            pending = {
                x: event.clientX, y: event.clientY,
                dx: (pending?.dx || 0) + event.clientX - lastPoint.x,
                dy: (pending?.dy || 0) + event.clientY - lastPoint.y,
                color: nextColor(now), strength: 1,
            };
        }
        lastPoint = { x: event.clientX, y: event.clientY };
        lastMotion = now;
        wake();
    }, options);
    document.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || !event.isPrimary || event.button !== 0
            || isEditable(event.target) || isEditable(document.activeElement)) return;
        const now = performance.now();
        pending = { x: event.clientX, y: event.clientY, dx: (Math.random() - 0.5) * 20,
            dy: (Math.random() - 0.5) * 20, color: nextColor(now), strength: 8 };
        lastPoint = { x: event.clientX, y: event.clientY };
        lastMotion = now;
        wake();
    }, options);
    document.addEventListener('focusin', event => { if (isEditable(event.target)) stop(); }, options);
    document.addEventListener('keydown', event => {
        if (event.key === 'Tab' || event.key === 'Escape') stop();
    }, { signal: events.signal });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); }, { signal: events.signal });
    document.addEventListener('pointerout', event => {
        if (!event.relatedTarget) lastPoint = null;
    }, options);
    window.addEventListener('resize', () => {
        stop();
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => { resizeFrame = 0; fluid.resize(); });
    }, options);
    canvas.addEventListener('webglcontextlost', event => {
        event.preventDefault();
        cleanup();
    }, { signal: events.signal });

    function cleanup() {
        if (disposed) return;
        disposed = true;
        events.abort();
        cancelAnimationFrame(frame);
        cancelAnimationFrame(resizeFrame);
        fluid.dispose();
        canvas.remove();
    }

    canvas.dataset.pointerState = 'sleeping';
    return cleanup;
}

export function initializePointerEffect(root = document) {
    instances.get(root)?.();
    if (!document.documentElement.classList.contains('site-redesign')) return () => {};

    const events = new AbortController();
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let disposeEffect = () => {};

    function configure() {
        disposeEffect();
        disposeEffect = () => {};
        if (finePointer.matches && !reducedMotion.matches) disposeEffect = mountPointerEffect();
    }

    finePointer.addEventListener('change', configure, { signal: events.signal });
    reducedMotion.addEventListener('change', configure, { signal: events.signal });
    configure();

    const cleanup = () => { events.abort(); disposeEffect(); };
    instances.set(root, cleanup);
    return cleanup;
}
