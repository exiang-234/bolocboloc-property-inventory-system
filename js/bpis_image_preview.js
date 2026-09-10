(function () {
    'use strict';

    function getOverlay() {
        return document.getElementById('bpisImagePreviewOverlay');
    }

    function openImagePreview(src, caption) {
        const overlay = getOverlay();
        const img = document.getElementById('bpisImagePreviewImg');
        const capEl = document.getElementById('bpisImagePreviewCaption');
        if (!overlay || !img || !src) {
            return;
        }
        img.src = src;
        img.alt = caption || 'Image preview';
        if (capEl) {
            capEl.textContent = caption || '';
            capEl.hidden = !caption;
        }
        overlay.classList.remove('hidden');
        document.body.classList.add('bpis-image-preview-open');
    }

    function closeImagePreview() {
        const overlay = getOverlay();
        const img = document.getElementById('bpisImagePreviewImg');
        if (!overlay) {
            return;
        }
        overlay.classList.add('hidden');
        if (img) {
            img.removeAttribute('src');
        }
        document.body.classList.remove('bpis-image-preview-open');
    }

    function bindAssetThumbs(root) {
        const scope = root || document;
        scope.querySelectorAll('img.bpis-asset-thumb').forEach(function (img) {
            if (img.dataset.bpisPreviewBound === '1') {
                return;
            }
            img.dataset.bpisPreviewBound = '1';
            img.setAttribute('title', 'Click to preview');
            img.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (img.src) {
                    openImagePreview(img.src, img.alt || 'Asset photo');
                }
            });
        });
    }

    function bindImagePreviews(root) {
        const scope = root || document;
        bindAssetThumbs(scope);
        scope.querySelectorAll('[data-bpis-image-preview]').forEach(function (el) {
            if (el.dataset.bpisPreviewBound === '1') {
                return;
            }
            el.dataset.bpisPreviewBound = '1';
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const src = el.getAttribute('data-preview-src')
                    || (el.querySelector('img') && el.querySelector('img').src)
                    || '';
                const caption = el.getAttribute('data-preview-caption')
                    || (el.querySelector('img') && el.querySelector('img').alt)
                    || '';
                if (src) {
                    openImagePreview(src, caption);
                }
            });
        });
    }

    window.bpisOpenImagePreview = openImagePreview;
    window.bpisCloseImagePreview = closeImagePreview;
    window.bpisBindImagePreviews = bindImagePreviews;

    document.addEventListener('DOMContentLoaded', function () {
        const overlay = getOverlay();
        const closeBtn = document.getElementById('bpisImagePreviewClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeImagePreview);
        }
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeImagePreview();
                }
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeImagePreview();
            }
        });
        bindImagePreviews(document);
    });
})();
