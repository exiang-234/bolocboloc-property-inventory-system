/**
 * BPIS: filter table rows by data-row-date="YYYY-MM-DD" within [data-bpis-date-filter-scope].
 * Works with text search: set tr.dataset.bpisSearchHidden from your search script, then call bpisSyncRowDisplay(tr).
 */
(function () {
    'use strict';

    var mobilePicker = null;
    var activeDateInput = null;
    var activeMonthDate = null;
    var monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function isMobilePickerMode() {
        return window.matchMedia('(max-width: 768px), (pointer: coarse)').matches;
    }

    function parseYmd(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) {
            return null;
        }
        var year = Number(match[1]);
        var month = Number(match[2]) - 1;
        var day = Number(match[3]);
        var date = new Date(year, month, day);
        if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
            return null;
        }
        return date;
    }

    function formatYmd(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function getInputLabel(input) {
        var label = input.closest('label');
        if (!label) {
            return 'Select date';
        }
        var span = label.querySelector('span');
        return (span ? span.textContent : label.textContent).trim() || 'Select date';
    }

    function dispatchDateChange(input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function closeMobilePicker() {
        if (!mobilePicker) {
            return;
        }
        mobilePicker.classList.remove('active');
        mobilePicker.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bpis-date-picker-open');
        activeDateInput = null;
    }

    function selectMobileDate(date) {
        if (!activeDateInput) {
            return;
        }
        activeDateInput.value = formatYmd(date);
        dispatchDateChange(activeDateInput);
        closeMobilePicker();
    }

    function clearMobileDate() {
        if (!activeDateInput) {
            return;
        }
        activeDateInput.value = '';
        dispatchDateChange(activeDateInput);
        closeMobilePicker();
    }

    function renderMobilePicker() {
        if (!mobilePicker || !activeMonthDate || !activeDateInput) {
            return;
        }

        var selected = parseYmd(activeDateInput.value);
        var today = new Date();
        var year = activeMonthDate.getFullYear();
        var month = activeMonthDate.getMonth();
        var firstDay = new Date(year, month, 1);
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var grid = mobilePicker.querySelector('[data-bpis-calendar-grid]');
        var title = mobilePicker.querySelector('[data-bpis-calendar-title]');
        var subtitle = mobilePicker.querySelector('[data-bpis-calendar-subtitle]');

        title.textContent = monthNames[month] + ' ' + year;
        subtitle.textContent = getInputLabel(activeDateInput);
        grid.innerHTML = '';

        dayNames.forEach(function (dayName) {
            var cell = document.createElement('div');
            cell.className = 'bpis-mobile-calendar-weekday';
            cell.textContent = dayName;
            grid.appendChild(cell);
        });

        for (var blank = 0; blank < firstDay.getDay(); blank++) {
            var blankCell = document.createElement('div');
            blankCell.className = 'bpis-mobile-calendar-empty';
            grid.appendChild(blankCell);
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var date = new Date(year, month, day);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'bpis-mobile-calendar-day';
            button.textContent = String(day);
            button.dataset.date = formatYmd(date);

            if (selected && formatYmd(selected) === button.dataset.date) {
                button.classList.add('selected');
            }
            if (formatYmd(today) === button.dataset.date) {
                button.classList.add('today');
            }

            button.addEventListener('click', function () {
                var picked = parseYmd(this.dataset.date);
                if (picked) {
                    selectMobileDate(picked);
                }
            });
            grid.appendChild(button);
        }
    }

    function ensureMobilePicker() {
        if (mobilePicker) {
            return mobilePicker;
        }

        mobilePicker = document.createElement('div');
        mobilePicker.className = 'bpis-mobile-date-picker';
        mobilePicker.setAttribute('aria-hidden', 'true');
        mobilePicker.innerHTML = [
            '<div class="bpis-mobile-date-picker-backdrop" data-bpis-calendar-close></div>',
            '<section class="bpis-mobile-date-picker-modal" role="dialog" aria-modal="true" aria-label="Select date">',
                '<div class="bpis-mobile-date-picker-head">',
                    '<div>',
                        '<p data-bpis-calendar-subtitle>Select date</p>',
                        '<h3 data-bpis-calendar-title></h3>',
                    '</div>',
                    '<button type="button" class="bpis-mobile-date-picker-close" data-bpis-calendar-close aria-label="Close calendar">&times;</button>',
                '</div>',
                '<div class="bpis-mobile-date-picker-nav">',
                    '<button type="button" data-bpis-calendar-prev aria-label="Previous month">&lt;</button>',
                    '<button type="button" data-bpis-calendar-today>Today</button>',
                    '<button type="button" data-bpis-calendar-next aria-label="Next month">&gt;</button>',
                '</div>',
                '<div class="bpis-mobile-calendar-grid" data-bpis-calendar-grid></div>',
                '<div class="bpis-mobile-date-picker-actions">',
                    '<button type="button" data-bpis-calendar-clear>Clear</button>',
                    '<button type="button" data-bpis-calendar-close>Cancel</button>',
                '</div>',
            '</section>'
        ].join('');

        mobilePicker.addEventListener('click', function (event) {
            if (event.target.matches('[data-bpis-calendar-close]')) {
                closeMobilePicker();
            }
            if (event.target.matches('[data-bpis-calendar-clear]')) {
                clearMobileDate();
            }
            if (event.target.matches('[data-bpis-calendar-today]')) {
                selectMobileDate(new Date());
            }
            if (event.target.matches('[data-bpis-calendar-prev]')) {
                activeMonthDate = new Date(activeMonthDate.getFullYear(), activeMonthDate.getMonth() - 1, 1);
                renderMobilePicker();
            }
            if (event.target.matches('[data-bpis-calendar-next]')) {
                activeMonthDate = new Date(activeMonthDate.getFullYear(), activeMonthDate.getMonth() + 1, 1);
                renderMobilePicker();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMobilePicker();
            }
        });

        document.body.appendChild(mobilePicker);
        return mobilePicker;
    }

    function openMobilePicker(input) {
        if (!isMobilePickerMode()) {
            return;
        }
        activeDateInput = input;
        activeMonthDate = parseYmd(input.value) || new Date();
        activeMonthDate = new Date(activeMonthDate.getFullYear(), activeMonthDate.getMonth(), 1);
        ensureMobilePicker();
        renderMobilePicker();
        mobilePicker.classList.add('active');
        mobilePicker.setAttribute('aria-hidden', 'false');
        document.body.classList.add('bpis-date-picker-open');
    }

    function bindMobilePickerInput(input) {
        if (input.dataset.bpisMobileDatePickerBound === '1') {
            return;
        }
        input.dataset.bpisMobileDatePickerBound = '1';
        input.addEventListener('pointerdown', function (event) {
            if (isMobilePickerMode()) {
                event.preventDefault();
                event.stopPropagation();
                input.blur();
                openMobilePicker(input);
            }
        });
        input.addEventListener('focus', function () {
            if (isMobilePickerMode()) {
                input.blur();
                openMobilePicker(input);
            }
        });
        input.addEventListener('click', function (event) {
            if (isMobilePickerMode()) {
                event.preventDefault();
                event.stopPropagation();
                input.blur();
                openMobilePicker(input);
            }
        });
    }

    function syncMobilePickerInputs() {
        document.querySelectorAll('[data-bpis-date-filter-scope] input[type="date"]').forEach(function (input) {
            if (isMobilePickerMode()) {
                input.setAttribute('readonly', 'readonly');
            } else {
                input.removeAttribute('readonly');
            }
        });
    }

    function rowPassesDate(tr, from, to) {
        var d = tr.getAttribute('data-row-date');
        if (!from && !to) {
            return true;
        }
        if (!d) {
            return false;
        }
        if (from && d < from) {
            return false;
        }
        if (to && d > to) {
            return false;
        }
        return true;
    }

    function syncRowDisplay(tr) {
        var dh = tr.dataset.bpisDateHidden === '1';
        var sh = tr.dataset.bpisSearchHidden === '1';
        var hide = dh || sh;
        tr.style.display = hide ? 'none' : '';
        if (tr.classList) {
            tr.classList.toggle('hidden', hide);
        }
    }

    window.bpisSyncRowDisplay = syncRowDisplay;

    window.bpisRowPassesDateFilter = function (tr) {
        var scope = tr.closest('[data-bpis-date-filter-scope]');
        if (!scope) {
            return true;
        }
        var fromEl = scope.querySelector('[data-bpis-date-from]');
        var toEl = scope.querySelector('[data-bpis-date-to]');
        var from = fromEl ? fromEl.value : '';
        var to = toEl ? toEl.value : '';
        return rowPassesDate(tr, from, to);
    };

    function applyDateFilter(scope) {
        var fromEl = scope.querySelector('[data-bpis-date-from]');
        var toEl = scope.querySelector('[data-bpis-date-to]');
        var from = fromEl ? fromEl.value : '';
        var to = toEl ? toEl.value : '';
        var tbody = scope.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (!tr.hasAttribute('data-row-date')) {
                return;
            }
            tr.dataset.bpisDateHidden = rowPassesDate(tr, from, to) ? '' : '1';
            syncRowDisplay(tr);
        });
        scope.dispatchEvent(new CustomEvent('bpis-date-filter-changed', { bubbles: true }));
        updatePlaceholderRows(scope);
    }

    function updatePlaceholderRows(scope) {
        var tbody = scope.querySelector('tbody');
        if (!tbody) {
            return;
        }
        var dataRows = tbody.querySelectorAll('tr[data-row-date]');
        var visible = 0;
        dataRows.forEach(function (tr) {
            if (tr.style.display !== 'none') {
                visible++;
            }
        });
        var noMatch = scope.querySelector('#noMatchRow');
        var emptyRow = scope.querySelector('#emptyRow');
        if (noMatch) {
            if (emptyRow && emptyRow.offsetParent !== null) {
                noMatch.style.display = 'none';
                return;
            }
            noMatch.style.display = visible === 0 && dataRows.length ? '' : 'none';
        }
    }

    function bindScope(scope) {
        var fromEl = scope.querySelector('[data-bpis-date-from]');
        var toEl = scope.querySelector('[data-bpis-date-to]');
        var clearBtn = scope.querySelector('[data-bpis-date-clear]');
        function run() {
            applyDateFilter(scope);
        }
        if (fromEl) {
            bindMobilePickerInput(fromEl);
            fromEl.addEventListener('change', run);
            fromEl.addEventListener('input', run);
        }
        if (toEl) {
            bindMobilePickerInput(toEl);
            toEl.addEventListener('change', run);
            toEl.addEventListener('input', run);
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (fromEl) {
                    fromEl.value = '';
                }
                if (toEl) {
                    toEl.value = '';
                }
                applyDateFilter(scope);
            });
        }
        applyDateFilter(scope);
    }

    function init() {
        document.querySelectorAll('[data-bpis-date-filter-scope]').forEach(bindScope);
        syncMobilePickerInputs();
    }

    window.addEventListener('resize', syncMobilePickerInputs);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
