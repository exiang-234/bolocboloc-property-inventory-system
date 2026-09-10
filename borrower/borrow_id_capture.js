(function () {
    'use strict';

    var IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';
    var UPLOAD_ACCEPT = IMAGE_ACCEPT + ',application/pdf,.pdf';

    var modal = document.getElementById('borrowIdCameraModal');
    var video = modal ? modal.querySelector('[data-camera-video]') : null;
    var canvas = modal ? modal.querySelector('[data-camera-canvas]') : null;
    var errorEl = modal ? modal.querySelector('[data-camera-error]') : null;
    var captureBtn = modal ? modal.querySelector('[data-camera-capture]') : null;
    var activeInput = null;
    var activeStream = null;

    document.querySelectorAll('[data-borrow-id-capture]').forEach(initBlock);

    if (modal) {
        modal.querySelectorAll('[data-camera-close]').forEach(function (el) {
            el.addEventListener('click', closeCamera);
        });
        if (captureBtn) {
            captureBtn.addEventListener('click', capturePhoto);
        }
    }

    function initBlock(block) {
        var input = block.querySelector('input[type="file"]');
        var previewWrap = block.querySelector('[data-preview-wrap]');
        var previewImg = block.querySelector('[data-preview-img]');
        var nameEl = block.querySelector('[data-file-name]');
        var cameraBtn = block.querySelector('[data-id-camera]');
        var uploadBtn = block.querySelector('[data-id-upload]');

        if (!input || !cameraBtn || !uploadBtn) {
            return;
        }

        uploadBtn.addEventListener('click', function () {
            input.setAttribute('accept', UPLOAD_ACCEPT);
            input.removeAttribute('capture');
            input.click();
        });

        cameraBtn.addEventListener('click', function () {
            openCamera(input);
        });

        input.addEventListener('change', function () {
            updatePreview(input, previewWrap, previewImg, nameEl, block);
        });
    }

    function updatePreview(input, previewWrap, previewImg, nameEl, block) {
        var file = input.files && input.files[0];

        block.classList.toggle('is-filled', !!file);

        if (!file) {
            if (previewWrap) previewWrap.hidden = true;
            if (nameEl) {
                nameEl.hidden = true;
                nameEl.textContent = '';
            }
            return;
        }

        if (nameEl) {
            nameEl.hidden = false;
            nameEl.textContent = file.name;
        }

        if (!previewWrap || !previewImg) {
            return;
        }

        if (file.type.indexOf('image/') === 0) {
            previewImg.onload = function () {
                URL.revokeObjectURL(previewImg.src);
            };
            previewImg.src = URL.createObjectURL(file);
            previewWrap.hidden = false;
        } else {
            previewWrap.hidden = true;
            previewImg.removeAttribute('src');
        }
    }

    function openCamera(input) {
        activeInput = input;

        if (!modal || !video || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            triggerNativeCamera(input);
            return;
        }

        showCameraError('');
        modal.hidden = false;
        document.body.classList.add('borrow-camera-open');

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false
        }).then(function (stream) {
            activeStream = stream;
            video.srcObject = stream;
            return video.play();
        }).catch(function () {
            closeCamera();
            triggerNativeCamera(input);
        });
    }

    function triggerNativeCamera(input) {
        input.setAttribute('accept', IMAGE_ACCEPT);
        input.setAttribute('capture', 'environment');
        input.click();
        input.removeAttribute('capture');
    }

    function capturePhoto() {
        if (!activeInput || !video || !canvas || !video.videoWidth) {
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        canvas.toBlob(function (blob) {
            if (!blob || !activeInput) {
                showCameraError('Could not capture photo. Try upload instead.');
                return;
            }

            var side = activeInput.id.indexOf('back') !== -1 ? 'back' : 'front';
            var file = new File([blob], 'valid-id-' + side + '.jpg', { type: 'image/jpeg' });
            var dt = new DataTransfer();
            dt.items.add(file);
            activeInput.files = dt.files;
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
            closeCamera();
        }, 'image/jpeg', 0.92);
    }

    function closeCamera() {
        if (activeStream) {
            activeStream.getTracks().forEach(function (track) {
                track.stop();
            });
            activeStream = null;
        }
        if (video) {
            video.srcObject = null;
        }
        if (modal) {
            modal.hidden = true;
        }
        document.body.classList.remove('borrow-camera-open');
        activeInput = null;
        showCameraError('');
    }

    function showCameraError(message) {
        if (!errorEl) {
            return;
        }
        if (!message) {
            errorEl.hidden = true;
            errorEl.textContent = '';
            return;
        }
        errorEl.hidden = false;
        errorEl.textContent = message;
    }
})();
