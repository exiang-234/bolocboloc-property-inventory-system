(function () {
    const chooseBtn = document.getElementById('profilePhotoChooseBtn');
    const input = document.getElementById('profilePhotoInput');
    const preview = document.getElementById('profileAvatarPreview');
    const nameEl = document.getElementById('profilePhotoFilename');
    const overlay = document.getElementById('profileCropOverlay');
    const cropImg = document.getElementById('profileCropImage');
    const cancelBtn = document.getElementById('profileCropCancel');
    const applyBtn = document.getElementById('profileCropApply');

    if (!input || !overlay || !cropImg || typeof Cropper === 'undefined') {
        return;
    }

    let cropper = null;
    let objectUrl = null;

    function revokeObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        revokeObjectUrl();
        cropImg.removeAttribute('src');
    }

    function closeCropModal() {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('profile-crop-open');
        destroyCropper();
    }

    function openCropModal(file) {
        destroyCropper();
        objectUrl = URL.createObjectURL(file);
        cropImg.src = objectUrl;
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('profile-crop-open');

        cropImg.onload = function () {
            cropImg.onload = null;
            cropper = new Cropper(cropImg, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                responsive: true,
                background: false,
            });
        };
    }

    function setInputFile(file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
    }

    if (chooseBtn) {
        chooseBtn.addEventListener('click', function () {
            input.click();
        });
    }

    input.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
            return;
        }
        if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
            this.value = '';
            if (nameEl) {
                nameEl.textContent = 'Please choose JPG, PNG, or WebP';
            }
            return;
        }
        openCropModal(file);
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            input.value = '';
            closeCropModal();
            if (nameEl) {
                nameEl.textContent = 'Using current photo';
            }
        });
    }

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            if (!cropper) {
                return;
            }
            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            if (!canvas) {
                return;
            }
            canvas.toBlob(
                function (blob) {
                    if (!blob) {
                        return;
                    }
                    const base = (input.files[0] && input.files[0].name) || 'profile.jpg';
                    const stem = base.replace(/\.[^.]+$/, '') || 'profile';
                    const cropped = new File([blob], stem + '.jpg', { type: 'image/jpeg' });
                    setInputFile(cropped);
                    if (preview) {
                        preview.src = URL.createObjectURL(cropped);
                    }
                    if (nameEl) {
                        nameEl.textContent = cropped.name + ' (cropped)';
                    }
                    closeCropModal();
                },
                'image/jpeg',
                0.92
            );
        });
    }

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            input.value = '';
            closeCropModal();
            if (nameEl) {
                nameEl.textContent = 'Using current photo';
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            input.value = '';
            closeCropModal();
            if (nameEl) {
                nameEl.textContent = 'Using current photo';
            }
        }
    });
})();
