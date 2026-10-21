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
    let stream = null, timer = null, detector = null, canvas = null, busy = false;

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

    async function tick() {
        if (!stream || video.readyState < 2) return;
        try {
            if (detector) {
                const found = await detector.detect(video);
                if (found.length) send(found[0].rawValue);
                return;
            }
            canvas = canvas || document.createElement('canvas');
            const w = video.videoWidth, h = video.videoHeight;
            if (!w) return;
            const scale = Math.min(1, 640 / w);
            canvas.width = w * scale; canvas.height = h * scale;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const res = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            if (res && res.data) send(res.data);
        } catch (e) { /* keep trying */ }
    }

    async function start() {
        try {
            if ('BarcodeDetector' in window) {
                const formats = await window.BarcodeDetector.getSupportedFormats();
                if (formats.includes('qr_code')) detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            }
            if (!detector) await loadJsQR();
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            await video.play();
            box.classList.add('scan-on');
            timer = setInterval(tick, 250);
            try { localStorage.setItem('cluk-camera', '1'); } catch (e) {}
        } catch (e) {
            note.hidden = false;
        }
    }

    function stop() {
        clearInterval(timer);
        if (stream) stream.getTracks().forEach(t => t.stop());
        stream = null;
        box.classList.remove('scan-on');
    }

    startBtn && startBtn.addEventListener('click', start);
    stopBtn && stopBtn.addEventListener('click', () => { stop(); try { localStorage.setItem('cluk-camera', '0'); } catch (e) {} });
    // A label opened with the phone's own camera app lands here with ?scanned=…
    if (box.dataset.scanned) { input.value = box.dataset.scanned; form.submit(); return; }
    // Camera was on last time: turn it on again.
    try { if (localStorage.getItem('cluk-camera') === '1' && navigator.mediaDevices) start(); } catch (e) {}
    window.addEventListener('pagehide', stop);
})();

// Photo fields send their form as soon as pictures are chosen (when asked to).
document.querySelectorAll('input[type=file][data-autosend]').forEach(el => el.addEventListener('change', () => {
    const only = el.form.querySelector('[data-photo-only]');
    if (only) only.value = '1';
    el.form.submit();
}));
