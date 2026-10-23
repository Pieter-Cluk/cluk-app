/*
 * CLUK warehouse scanner: the phone camera (or a webcam at the test bench) reads
 * QR labels and sends the code with the form. Uses the browser's BarcodeDetector
 * when it has one, else jsQR (public/js/vendor/jsQR.js, Apache 2.0).
 * A hand scanner or typing the code in the field works too.
 */
(function () {
    const box = document.querySelector('[data-scanner]');
    if (!box) return;
    const form = box.closest('form');
    const input = form.querySelector('input[name="code"]');
    const video = box.querySelector('video');
    const startBtn = box.querySelector('[data-scan-start]');
    const stopBtn = box.querySelector('[data-scan-stop]');
    const note = box.querySelector('[data-scan-note]');
    const tools = box.querySelector('[data-scan-tools]');
    let stream = null, track = null, timer = null, detector = null, canvas = null, busy = false, torch = false;

    function send(code) {
        if (busy) return;
        busy = true;
        if (navigator.vibrate) navigator.vibrate(60);
        input.value = code;
        stop();
        form.submit();
    }

    async function loadJsQR() {
        if (window.jsQR) return;
        await new Promise((ok, fail) => { const s = document.createElement('script'); s.src = box.dataset.jsqr; s.onload = ok; s.onerror = fail; document.head.appendChild(s); });
    }

    // The middle of the picture at full sharpness (small labels lose their detail when the whole picture is shrunk).
    function grab(part) {
        const w = video.videoWidth, h = video.videoHeight;
        if (!w) return null;
        const side = Math.min(w, h) * part;
        const sx = (w - side) / 2, sy = (h - side) / 2;
        const size = Math.min(side, 1100);
        canvas = canvas || document.createElement('canvas');
        canvas.width = size; canvas.height = size;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(video, sx, sy, side, side, 0, 0, size, size);
        return ctx;
    }

    let turn = 0;
    async function tick() {
        if (!stream || busy || video.readyState < 2) return;
        try {
            if (detector) {
                const found = await detector.detect(video);
                if (found.length) return send(found[0].rawValue);
                // Small code far away: look again in the middle, enlarged.
                const ctx = grab(0.6);
                if (ctx) {
                    const again = await detector.detect(canvas);
                    if (again.length) send(again[0].rawValue);
                }
                return;
            }
            // jsQR (iPhone): alternate between the middle (small labels) and a wider part of the picture.
            turn = (turn + 1) % 3;
            const ctx = grab(turn === 2 ? 0.95 : 0.6);
            if (!ctx) return;
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const res = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            if (res && res.data) send(res.data);
        } catch (e) { /* keep trying */ }
    }

    // The main back camera: some phones give the ultra-wide (which cannot focus close by) for "environment".
    async function backCamera() {
        try {
            const cams = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput' && d.label);
            const back = cams.filter(d => /back|rear|achter|arrière|trasera|environment|facing back/i.test(d.label));
            const main = back.find(d => /^back camera$/i.test(d.label.trim()))
                || back.find(d => !/ultra|wide|tele|depth|macro|dual|triple/i.test(d.label))
                || null;
            return main ? main.deviceId : null;
        } catch (e) { return null; }
    }

    function constraints(deviceId) {
        const video = { width: { ideal: 1920 }, height: { ideal: 1080 }, advanced: [{ focusMode: 'continuous' }] };
        if (deviceId) video.deviceId = { exact: deviceId }; else video.facingMode = { ideal: 'environment' };
        return { video, audio: false };
    }

    function setupTools() {
        if (!tools || !track || !track.getCapabilities) return;
        const caps = track.getCapabilities();
        tools.innerHTML = '';
        if (caps.focusMode && caps.focusMode.includes('continuous')) track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
        if (caps.zoom && caps.zoom.max > 1) {
            const steps = [1, 2, 3].filter(z => z >= caps.zoom.min && z <= caps.zoom.max);
            steps.forEach(z => {
                const b = document.createElement('button');
                b.type = 'button'; b.textContent = z + '×'; b.className = 'ph-cam-tool';
                b.addEventListener('click', () => {
                    track.applyConstraints({ advanced: [{ zoom: z }] }).catch(() => {});
                    tools.querySelectorAll('.ph-cam-tool').forEach(x => x.classList.toggle('on', x === b));
                    try { localStorage.setItem('cluk-zoom', String(z)); } catch (e) {}
                });
                tools.appendChild(b);
            });
            let z = 1; try { z = parseFloat(localStorage.getItem('cluk-zoom')) || 1; } catch (e) {}
            const pick = [...tools.children].find(x => x.textContent === z + '×');
            if (pick) pick.click();
        }
        if (caps.torch) {
            const t = document.createElement('button');
            t.type = 'button'; t.textContent = '💡'; t.className = 'ph-cam-tool'; t.setAttribute('aria-label', 'Light');
            t.addEventListener('click', () => { torch = !torch; track.applyConstraints({ advanced: [{ torch }] }).catch(() => {}); t.classList.toggle('on', torch); });
            tools.appendChild(t);
        }
    }

    async function start() {
        try {
            if ('BarcodeDetector' in window) {
                const formats = await window.BarcodeDetector.getSupportedFormats();
                if (formats.includes('qr_code')) detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            }
            if (!detector) await loadJsQR();
            stream = await navigator.mediaDevices.getUserMedia(constraints(null));
            // With permission the camera names are known: switch to the main back camera when we got another one.
            const main = await backCamera();
            if (main && stream.getVideoTracks()[0].getSettings().deviceId !== main) {
                stream.getTracks().forEach(t => t.stop());
                stream = await navigator.mediaDevices.getUserMedia(constraints(main));
            }
            track = stream.getVideoTracks()[0];
            video.srcObject = stream;
            await video.play();
            box.classList.add('scan-on');
            setupTools();
            timer = setInterval(tick, 160);
            try { localStorage.setItem('cluk-camera', '1'); } catch (e) {}
        } catch (e) {
            note.hidden = false;
        }
    }

    function stop() {
        clearInterval(timer);
        if (stream) stream.getTracks().forEach(t => t.stop());
        stream = null; track = null;
        box.classList.remove('scan-on');
    }

    startBtn && startBtn.addEventListener('click', start);
    stopBtn && stopBtn.addEventListener('click', () => { stop(); try { localStorage.setItem('cluk-camera', '0'); } catch (e) {} });
    // A label opened with the phone's own camera app lands here with ?scanned=…
    if (box.dataset.scanned) { input.value = box.dataset.scanned; form.submit(); return; }
    // Turn the camera on by itself when it was on last time, or when the browser
    // already has permission for this site (so nobody has to tap "allow" again).
    (async function () {
        if (!navigator.mediaDevices) return;
        let wanted = false;
        try { wanted = localStorage.getItem('cluk-camera') === '1'; } catch (e) {}
        try {
            if (navigator.permissions) {
                const p = await navigator.permissions.query({ name: 'camera' });
                if (p.state === 'granted') { let off = false; try { off = localStorage.getItem('cluk-camera') === '0'; } catch (e) {} wanted = !off; }
                if (p.state === 'denied') { note.hidden = false; return; }
            }
        } catch (e) { /* Safari: no camera permission query */ }
        if (wanted) start();
    })();
    window.addEventListener('pagehide', stop);
})();

// Photo fields send their form as soon as pictures are chosen (when asked to).
document.querySelectorAll('input[type=file][data-autosend]').forEach(el => el.addEventListener('change', () => {
    const only = el.form.querySelector('[data-photo-only]');
    if (only) only.value = '1';
    el.form.submit();
}));
