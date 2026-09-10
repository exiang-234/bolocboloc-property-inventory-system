(function () {
    function filterOptions(picker, query) {
        const q = (query || '').trim().toLowerCase();
        picker.querySelectorAll('.bpis-asset-picker-option').forEach(function (opt) {
            const name = (opt.dataset.name || '').toLowerCase();
            const desc = (opt.dataset.description || '').toLowerCase();
            const prop = (opt.dataset.property || '').toLowerCase();
            const match = !q || name.includes(q) || desc.includes(q) || prop.includes(q);
            opt.classList.toggle('is-hidden', !match);
        });
    }

    function applySelection(picker, opt) {
        const native = picker.querySelector('.bpis-asset-picker-native');
        const thumb = picker.querySelector('[data-picker-thumb]');
        const nameEl = picker.querySelector('[data-picker-name]');
        const descEl = picker.querySelector('[data-picker-desc]');
        const metaEl = picker.querySelector('[data-picker-meta]');
        const id = opt ? opt.dataset.id : '';

        if (native) {
            native.value = id || '';
            native.dispatchEvent(new Event('change', { bubbles: true }));
        }

        picker.querySelectorAll('.bpis-asset-picker-option').forEach(function (li) {
            const selected = li === opt;
            li.classList.toggle('is-selected', selected);
            li.setAttribute('aria-selected', selected ? 'true' : 'false');
        });

        if (opt) {
            picker.classList.remove('bpis-asset-picker-placeholder');
            if (thumb) thumb.src = opt.dataset.photo || thumb.src;
            if (nameEl) nameEl.textContent = opt.dataset.name || '';
            if (descEl) {
                descEl.textContent = opt.dataset.description
                    ? opt.dataset.description
                    : 'No description';
            }
            if (metaEl) metaEl.textContent = opt.dataset.property || '';
        } else {
            picker.classList.add('bpis-asset-picker-placeholder');
            if (nameEl) nameEl.textContent = 'Select an asset…';
            if (descEl) descEl.textContent = 'Choose from the list to see image and details';
            if (metaEl) metaEl.textContent = '';
        }

        picker.classList.remove('is-open');
        const trigger = picker.querySelector('.bpis-asset-picker-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }

    function initPicker(picker) {
        if (picker.dataset.bpisPickerInit === '1') return;
        picker.dataset.bpisPickerInit = '1';

        const trigger = picker.querySelector('.bpis-asset-picker-trigger');
        const search = picker.querySelector('[data-picker-search]');
        const native = picker.querySelector('.bpis-asset-picker-native');

        if (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                const open = picker.classList.toggle('is-open');
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && search) {
                    search.value = '';
                    filterOptions(picker, '');
                    search.focus();
                }
            });
        }

        if (search) {
            search.addEventListener('input', function () {
                filterOptions(picker, search.value);
            });
        }

        picker.querySelectorAll('.bpis-asset-picker-option').forEach(function (opt) {
            opt.addEventListener('click', function () {
                applySelection(picker, opt);
            });
        });

        if (native) {
            native.addEventListener('change', function () {
                const id = native.value;
                const opt = picker.querySelector('.bpis-asset-picker-option[data-id="' + id + '"]');
                if (opt) applySelection(picker, opt);
            });
        }

        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) {
                picker.classList.remove('is-open');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initAll() {
        document.querySelectorAll('[data-bpis-asset-picker]').forEach(initPicker);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
