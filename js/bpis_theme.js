/**
 * BPIS Theme Engine - Dark Mode & Light Mode Manager
 * Barangay Bolocboloc Property Inventory System
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'bpis_theme';
  var THEME_DARK = 'dark';
  var THEME_LIGHT = 'light';

  function getSystemPreference() {
    if (global.matchMedia && global.matchMedia('(prefers-color-scheme: dark)').matches) {
      return THEME_DARK;
    }
    return THEME_LIGHT;
  }

  function getSavedTheme() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === THEME_DARK || saved === THEME_LIGHT) {
        return saved;
      }
    } catch (e) { console.warn('BPIS theme: unable to read saved theme', e); }
    return null;
  }

  function getCurrentTheme() {
    var saved = getSavedTheme();
    if (saved) return saved;
    return document.documentElement.getAttribute('data-theme') === THEME_DARK ? THEME_DARK : THEME_LIGHT;
  }

  function enforceCardVisibility(isDark) {
    try {
      // Whole stat card + number colors per theme — persist across refresh (Image1 light / Image2 dark)
      if (isDark) {
        try {
          document.documentElement.style.setProperty('background', '#0b1329', 'important');
          document.documentElement.style.setProperty('background-color', '#0b1329', 'important');
          if (document.body) {
            document.body.style.setProperty('background', '#0b1329', 'important');
            document.body.style.setProperty('background-color', '#0b1329', 'important');
          }
        } catch(e) {}
      } else {
        try {
          document.documentElement.style.removeProperty('background');
          document.documentElement.style.removeProperty('background-color');
          if (document.body) {
            document.body.style.removeProperty('background');
            document.body.style.removeProperty('background-color');
          }
        } catch(e) {}
      }
      // Card number / label colours are owned by CSS (--stat-* and
      // --text-secondary), which resolves per theme and also covers cards added
      // to the DOM later. This used to stamp the light palette inline with
      // !important, which outranked every stylesheet and left the numbers dark
      // on the dark card background. Clear any such leftovers so CSS applies.
      var nums = document.querySelectorAll('.card-number, .cards-container .card-number');
      for (var i = 0; i < nums.length; i++) {
        nums[i].style.removeProperty('color');
        nums[i].style.removeProperty('opacity');
        nums[i].style.removeProperty('visibility');
      }
      var labels = document.querySelectorAll('.cards-container > .card p');
      for (var j = 0; j < labels.length; j++) {
        labels[j].style.removeProperty('color');
      }
    } catch (e) {}
  }

  function applyTheme(theme, save) {
    if (theme !== THEME_DARK && theme !== THEME_LIGHT) {
      theme = THEME_LIGHT;
    }

    var isDark = theme === THEME_DARK;
    var root = document.documentElement;
    var body = document.body;

    if (isDark) {
      root.classList.add('dark');
      root.setAttribute('data-theme', THEME_DARK);
      if (body) {
        body.classList.add('dark-mode');
        body.classList.add('dark');
      }
    } else {
      root.classList.remove('dark');
      root.setAttribute('data-theme', THEME_LIGHT);
      if (body) {
        body.classList.remove('dark-mode');
        body.classList.remove('dark');
      }
    }

    if (save !== false) {
      try {
        localStorage.setItem(STORAGE_KEY, theme);
      } catch (e) { console.warn('BPIS theme: unable to save theme', e); }
    }

    updateToggleButtons(isDark);
    enforceCardVisibility(isDark);

    try {
      var event = new CustomEvent('bpis-theme-change', { detail: { theme: theme, isDark: isDark } });
      global.dispatchEvent(event);
    } catch (e) { console.warn('BPIS theme: unable to dispatch theme change event', e); }
  }

  function updateToggleButtons(isDark) {
    // Update header toggle buttons
    var headerToggles = document.querySelectorAll('.bpis-theme-toggle, [data-bpis-theme-toggle]');
    headerToggles.forEach(function (btn) {
      btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      btn.setAttribute('title', isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode');
      var icon = btn.querySelector('i');
      if (icon) {
        if (isDark) {
          icon.className = 'fa-solid fa-sun bpis-theme-icon-sun';
        } else {
          icon.className = 'fa-solid fa-moon bpis-theme-icon-moon';
        }
      }
    });

    // Update dropdown toggles
    var dropdownToggles = document.querySelectorAll('.bpis-theme-toggle-item, #dropdownThemeToggle');
    dropdownToggles.forEach(function (item) {
      var track = item.querySelector('.theme-switch-track');
      var text = item.querySelector('.theme-switch-text');
      if (track) {
        if (isDark) {
          track.classList.add('is-active');
        } else {
          track.classList.remove('is-active');
        }
      }
      if (text) {
        text.textContent = isDark ? 'Light Mode' : 'Dark Mode';
      }
      var icon = item.querySelector('i');
      if (icon) {
        icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
      }
    });

    // Update floating toggles (login, register, borrower flow)
    var floatingToggles = document.querySelectorAll('.borrow-theme-toggle');
    floatingToggles.forEach(function (btn) {
      btn.setAttribute('aria-label', isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode');
      btn.setAttribute('title', isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode');
      var icon = btn.querySelector('i');
      if (icon) {
        icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
      }
    });
  }

  function toggleTheme() {
    var current = getCurrentTheme();
    var next = current === THEME_DARK ? THEME_LIGHT : THEME_DARK;
    applyTheme(next, true);
    return next;
  }

  function ensureFloatingToggle() {
    // Ensure at least one toggle exists on every page (for standalone pages without header)
    if (document.querySelector('.bpis-theme-toggle, .borrow-theme-toggle, #dropdownThemeToggle, .bpis-theme-toggle-item')) return;
    try {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'borrow-theme-toggle';
      btn.setAttribute('aria-label', 'Toggle theme');
      btn.setAttribute('title', 'Toggle theme (Dark / Light)');
      btn.innerHTML = '<i class="fa-solid fa-moon" aria-hidden="true"></i>';
      // Place as fixed floating button if no header toggle present
      btn.style.position = 'fixed';
      btn.style.top = '18px';
      btn.style.right = '18px';
      btn.style.zIndex = '1000';
      document.body.appendChild(btn);
      updateToggleButtons(getCurrentTheme() === THEME_DARK);
    } catch (e) {}
  }

  function initTheme() {
    var saved = getSavedTheme();
    var initial = saved ? saved : getSystemPreference();
    applyTheme(initial, false);
    // Re-enforce after DOM is ready (cards may be rendered late)
    function reEnforce() {
      enforceCardVisibility(getCurrentTheme() === THEME_DARK);
      ensureFloatingToggle();
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        reEnforce();
        // Observe late-added cards (e.g., secretary dashboard values injected)
        try {
          var mo = new MutationObserver(reEnforce);
          mo.observe(document.documentElement, { childList: true, subtree: true });
          setTimeout(function(){ mo.disconnect(); }, 5000);
        } catch(e) {}
      });
    } else {
      reEnforce();
      setTimeout(reEnforce, 500);
      try {
        var mo2 = new MutationObserver(reEnforce);
        mo2.observe(document.documentElement, { childList: true, subtree: true });
        setTimeout(function(){ mo2.disconnect(); }, 5000);
      } catch(e) {}
    }

    // Bind event listeners
    document.addEventListener('click', function (e) {
      var target = e.target.closest('.bpis-theme-toggle, [data-bpis-theme-toggle], .borrow-theme-toggle, #dropdownThemeToggle, .bpis-theme-toggle-item');
      if (target) {
        e.preventDefault();
        e.stopPropagation();
        toggleTheme();
      }
    });

    // Listen for storage changes across browser tabs
    global.addEventListener('storage', function (e) {
      if (e.key === STORAGE_KEY && (e.newValue === THEME_DARK || e.newValue === THEME_LIGHT)) {
        applyTheme(e.newValue, false);
      }
    });

    // Also enforce on theme change event
    global.addEventListener('bpis-theme-change', function(e) {
      try { enforceCardVisibility(!!(e.detail && e.detail.isDark)); } catch(err) {}
    });
  }

  // Pre-apply theme immediately if documentElement is available
  var preTheme = getSavedTheme() || getSystemPreference();
  if (preTheme === THEME_DARK) {
    document.documentElement.classList.add('dark');
    document.documentElement.setAttribute('data-theme', THEME_DARK);
  } else {
    document.documentElement.classList.remove('dark');
    document.documentElement.setAttribute('data-theme', THEME_LIGHT);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
  } else {
    initTheme();
  }

  // Expose global API
  global.bpisGetTheme = getCurrentTheme;
  global.bpisSetTheme = function (theme) { applyTheme(theme, true); };
  global.bpisToggleTheme = toggleTheme;
  global.bpisUpdateThemeUI = function () { updateToggleButtons(getCurrentTheme() === THEME_DARK); };

})(typeof window !== 'undefined' ? window : this);
