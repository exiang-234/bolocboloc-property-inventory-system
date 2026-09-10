(function () {
    const readerEl = document.getElementById('reader');
    const tagInput = document.getElementById('tagInput');
    const statusEl = document.getElementById('scanStatus');
    const btnStart = document.getElementById('btnStartCamera');
    const btnStop = document.getElementById('btnStopCamera');
    const btnSwitch = document.getElementById('btnSwitchCamera');
    const fileInput = document.getElementById('qrImageFile');
    const lookupForm = document.getElementById('tagLookupForm');

    if (!readerEl || typeof Html5Qrcode === 'undefined') {
        if (statusEl) {
            statusEl.textContent = 'Scanner library failed to load. Enter the tag manually or refresh the page.';
            statusEl.className = 'scan-status scan-status-error';
        }
        return;
    }

    let scanner = null;
    let running = false;
    let cameraList = [];
    let cameraIndex = 0;

    function setStatus(msg, type) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.className = 'scan-status' + (type ? ' scan-status-' + type : '');
        statusEl.classList.remove('hidden');
    }

    function hideStatus() {
        if (statusEl) statusEl.classList.add('hidden');
    }

    function isSecureEnough() {
        return window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    }

    function friendlyError(err) {
        const name = (err && err.name) || '';
        const msg = (err && err.message) ? String(err.message) : String(err || '');
        if (name === 'NotAllowedError' || /permission/i.test(msg)) {
            return 'Camera permission denied. Click “Allow” when the browser asks, or use “Upload QR image” below.';
        }
        if (name === 'NotFoundError' || /not found|no device/i.test(msg)) {
            return 'No camera found on this device. Use “Upload QR image” or enter the tag manually.';
        }
        if (!isSecureEnough()) {
            return 'Camera requires HTTPS or localhost. Open the site as http://localhost/... or use “Upload QR image”.';
        }
        return 'Could not start camera: ' + (msg || 'unknown error') + '. Try another camera or upload a photo of the QR code.';
    }

    function normalizeScannedTag(decoded) {
        var t = String(decoded || '').trim();
        var pipe = t.indexOf('|');
        if (pipe >= 0) {
            t = t.substring(0, pipe).trim();
        }
        return t;
    }

    function onScanSuccess(decoded) {
        if (!tagInput || !decoded) return;
        tagInput.value = normalizeScannedTag(decoded);
        stopScanner().then(function () {
            if (lookupForm) lookupForm.submit();
        });
    }

    function qrConfig() {
        return {
            fps: 10,
            aspectRatio: 1.333334,
            qrbox: function (viewfinderWidth, viewfinderHeight) {
                const edge = Math.min(viewfinderWidth, viewfinderHeight) * 0.75;
                return { width: Math.floor(edge), height: Math.floor(edge) };
            },
            disableFlip: false
        };
    }

    function pickBackCameraIndex(list) {
        if (!list || !list.length) return 0;
        const backIdx = list.findIndex(function (c) {
            return /back|rear|environment|world/i.test(c.label || '');
        });
        return backIdx >= 0 ? backIdx : 0;
    }

    async function loadCameras() {
        try {
            cameraList = await Html5Qrcode.getCameras();
        } catch (e) {
            cameraList = [];
        }
        if (btnSwitch) {
            btnSwitch.classList.toggle('hidden', cameraList.length < 2);
        }
        return cameraList;
    }

    async function startWithDeviceId(deviceId) {
        await scanner.start(deviceId, qrConfig(), onScanSuccess, function () {});
    }

    async function startWithFacingMode(mode) {
        await scanner.start({ facingMode: mode }, qrConfig(), onScanSuccess, function () {});
    }

    async function resetScannerInstance() {
        if (scanner) {
            try {
                if (running) await scanner.stop();
            } catch (e) { /* ignore */ }
            try {
                await scanner.clear();
            } catch (e) { /* ignore */ }
        }
        running = false;
        scanner = new Html5Qrcode('reader');
    }

    async function startScanner() {
        if (running) return;

        if (!isSecureEnough()) {
            setStatus(
                'Camera access needs a secure connection. Use http://localhost/BPIS/... or upload a QR image.',
                'warn'
            );
            return;
        }

        setStatus('Starting camera…', 'info');
        if (btnStart) btnStart.disabled = true;
        await resetScannerInstance();
        await loadCameras();

        const attempts = [];

        if (cameraList.length > 0) {
            cameraIndex = pickBackCameraIndex(cameraList);
            attempts.push(function () {
                return startWithDeviceId(cameraList[cameraIndex].id);
            });
            cameraList.forEach(function (cam, idx) {
                if (idx !== cameraIndex) {
                    attempts.push(function () {
                        cameraIndex = idx;
                        return startWithDeviceId(cam.id);
                    });
                }
            });
        }

        attempts.push(function () { return startWithFacingMode('environment'); });
        attempts.push(function () { return startWithFacingMode('user'); });

        let lastErr = null;
        for (let i = 0; i < attempts.length; i++) {
            try {
                await attempts[i]();
                running = true;
                if (btnStop) btnStop.disabled = false;
                if (btnStart) btnStart.disabled = true;
                setStatus('Point the camera at the property QR tag.', 'ok');
                return;
            } catch (err) {
                lastErr = err;
                try {
                    await scanner.stop();
                } catch (stopErr) { /* ignore */ }
                try {
                    await scanner.clear();
                } catch (clearErr) { /* ignore */ }
            }
        }

        running = false;
        if (btnStart) btnStart.disabled = false;
        if (btnStop) btnStop.disabled = true;
        setStatus(friendlyError(lastErr), 'error');
    }

    async function stopScanner() {
        if (!scanner || !running) return;
        try {
            await scanner.stop();
            await scanner.clear();
        } catch (e) { /* ignore */ }
        running = false;
        if (btnStart) btnStart.disabled = false;
        if (btnStop) btnStop.disabled = true;
    }

    async function switchCamera() {
        if (!running || cameraList.length < 2) return;
        cameraIndex = (cameraIndex + 1) % cameraList.length;
        setStatus('Switching to: ' + (cameraList[cameraIndex].label || 'Camera ' + (cameraIndex + 1)), 'info');
        try {
            await scanner.stop();
            await scanner.clear();
            await startWithDeviceId(cameraList[cameraIndex].id);
            setStatus('Using: ' + (cameraList[cameraIndex].label || 'Camera'), 'ok');
        } catch (err) {
            setStatus(friendlyError(err), 'error');
        }
    }

    async function scanFromFile(file) {
        if (!file) return;
        const tempId = 'readerFileScan';
        let temp = document.getElementById(tempId);
        if (!temp) {
            temp = document.createElement('div');
            temp.id = tempId;
            temp.style.display = 'none';
            document.body.appendChild(temp);
        }
        const fileScanner = new Html5Qrcode(tempId);
        setStatus('Reading QR from image…', 'info');
        try {
            const text = await fileScanner.scanFile(file, true);
            onScanSuccess(text);
        } catch (err) {
            setStatus('No QR code found in that image. Try a clearer photo or enter the tag manually.', 'error');
        } finally {
            try {
                fileScanner.clear();
            } catch (e) { /* ignore */ }
            if (fileInput) fileInput.value = '';
        }
    }

    if (btnStart) {
        btnStart.addEventListener('click', function () {
            startScanner();
        });
    }
    if (btnStop) {
        btnStop.disabled = true;
        btnStop.addEventListener('click', function () {
            stopScanner();
            setStatus('Camera stopped. Click “Start camera” to scan again.', 'info');
        });
    }
    if (btnSwitch) {
        btnSwitch.addEventListener('click', switchCamera);
    }
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                scanFromFile(fileInput.files[0]);
            }
        });
    }

    if (tagInput && lookupForm) {
        lookupForm.addEventListener('submit', function () {
            tagInput.value = normalizeScannedTag(tagInput.value);
        });
    }

    window.addEventListener('beforeunload', function () {
        if (running && scanner) {
            scanner.stop().catch(function (err) { console.warn('Scanner stop on unload failed:', err); });
        }
    });

    setStatus('Click “Start camera” and allow access when prompted. On desktop, your webcam is used if no rear camera exists.', 'info');
})();
