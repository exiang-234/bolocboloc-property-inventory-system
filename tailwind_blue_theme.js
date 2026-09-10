document.addEventListener('DOMContentLoaded', function () {
  (function injectGlobalMotionStyles() {
    if (document.getElementById('bpis-global-motion-style')) return;
    var style = document.createElement('style');
    style.id = 'bpis-global-motion-style';
    style.textContent =
      '@keyframes bpisFadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}' +
      '@keyframes bpisSoftPop{from{opacity:0;transform:scale(.98)}to{opacity:1;transform:scale(1)}}' +
      'body{animation:bpisFadeUp .35s ease-out;}' +
      '.header,.table-section,.card{animation:bpisSoftPop .28s ease-out;}' +
      '.card{transition:transform .18s ease, box-shadow .18s ease;}' +
      '.card:hover{transform:translateY(-2px);}' +
      '.nav-item,.btn-confirm,.btn-cancel,.btn-receive,.asset-btn,.edit-asset-btn,.delete-asset-btn{transition:all .18s ease-in-out !important;}';
    document.head.appendChild(style);
  })();

  (function applyGlobalFont() {
    if (!document.querySelector('link[data-bpis-font="inter"]')) {
      var fontLink = document.createElement('link');
      fontLink.rel = 'stylesheet';
      fontLink.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';
      fontLink.setAttribute('data-bpis-font', 'inter');
      document.head.appendChild(fontLink);
    }

    if (!document.getElementById('bpis-global-font-style')) {
      var style = document.createElement('style');
      style.id = 'bpis-global-font-style';
      style.textContent =
        ':root{--bpis-font:"Inter","Segoe UI",Roboto,Arial,sans-serif;}' +
        'body,button,input,select,textarea,th,td,h1,h2,h3,h4,h5,h6,p,a,span,div,label{font-family:var(--bpis-font) !important;}' +
        'code,pre,kbd,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace !important;}' +
        '.fa,.fas,.far,.fal,.fab,[class*=" fa-"],[class^="fa-"]{font-family:"Font Awesome 6 Free","Font Awesome 6 Brands" !important;}';
      document.head.appendChild(style);
    }
  })();

  function addClasses(selector, classes) {
    document.querySelectorAll(selector).forEach(function (el) {
      el.classList.add.apply(el.classList, classes);
    });
  }
  function setStyle(selector, styleMap) {
    document.querySelectorAll(selector).forEach(function (el) {
      Object.keys(styleMap).forEach(function (key) {
        el.style.setProperty(key, styleMap[key], 'important');
      });
    });
  }
  function enhanceLogoutModals() {
    document.querySelectorAll('.logout-modal').forEach(function (modal) {
      if (modal.classList.contains('bpis-logout-modal')) return;
      if (modal.getAttribute('data-enhanced-logout') === '1') return;
      modal.setAttribute('data-enhanced-logout', '1');

      var title = modal.querySelector('h2');
      if (title) {
        title.classList.add('text-slate-900', 'text-2xl', 'font-extrabold');
        title.style.setProperty('margin-bottom', '8px', 'important');
        if (!title.querySelector('.logout-title-wrap')) {
          var currentTitle = title.textContent || 'Logout Confirmation';
          title.textContent = '';

          var wrap = document.createElement('div');
          wrap.className = 'logout-title-wrap';
          wrap.style.display = 'flex';
          wrap.style.alignItems = 'center';
          wrap.style.justifyContent = 'center';
          wrap.style.gap = '10px';

          var iconBadge = document.createElement('span');
          iconBadge.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i>';
          iconBadge.style.width = '34px';
          iconBadge.style.height = '34px';
          iconBadge.style.display = 'inline-flex';
          iconBadge.style.alignItems = 'center';
          iconBadge.style.justifyContent = 'center';
          iconBadge.style.borderRadius = '9999px';
          iconBadge.style.background = 'linear-gradient(135deg,#1d4ed8,#2563eb)';
          iconBadge.style.color = '#fff';
          iconBadge.style.fontSize = '14px';
          iconBadge.style.boxShadow = '0 8px 18px rgba(37,99,235,.35)';

          var titleText = document.createElement('span');
          titleText.textContent = currentTitle;

          wrap.appendChild(iconBadge);
          wrap.appendChild(titleText);
          title.appendChild(wrap);
        }
      }

      var bodyText = modal.querySelector('p');
      if (bodyText) {
        bodyText.classList.add('text-slate-600');
        bodyText.style.setProperty('margin-bottom', '20px', 'important');
      }
    });
  }
  function enhanceProfileLinks() {
    var overlay = document.getElementById('globalProfileInfoOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'globalProfileInfoOverlay';
      overlay.className = 'logout-overlay';
      overlay.style.display = 'none';
      overlay.innerHTML =
        '<div class="logout-modal" style="max-width:440px;">' +
          '<h2 style="margin-bottom:10px;">Profile Information</h2>' +
          '<div style="display:flex;justify-content:center;margin-bottom:12px;">' +
            '<img id="globalProfileAvatar" src="../images/profile_logo.png" alt="Profile" style="width:72px;height:72px;border-radius:9999px;border:2px solid #dbeafe;object-fit:cover;">' +
          '</div>' +
          '<div style="text-align:left;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;">' +
            '<div style="font-size:12px;color:#64748b;margin-bottom:4px;">Full Name</div>' +
            '<div id="globalProfileName" style="font-weight:700;color:#0f172a;margin-bottom:10px;">-</div>' +
            '<div style="font-size:12px;color:#64748b;margin-bottom:4px;">Role</div>' +
            '<div id="globalProfileRole" style="font-weight:600;color:#1e293b;">-</div>' +
          '</div>' +
          '<div class="logout-buttons" style="margin-top:16px;border-top:none;padding:0;background:transparent;">' +
            '<button type="button" class="btn-confirm" id="closeGlobalProfileInfo">Close</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(overlay);
    }

    function openProfileModal(fromMenu) {
      var nameEl = fromMenu.querySelector('.profile-user-info h4');
      var roleEl = fromMenu.querySelector('.profile-user-info p');
      var avatarEl = fromMenu.querySelector('.profile-user-info img');
      var modalName = document.getElementById('globalProfileName');
      var modalRole = document.getElementById('globalProfileRole');
      var modalAvatar = document.getElementById('globalProfileAvatar');

      if (modalName) modalName.textContent = nameEl ? nameEl.textContent.trim() : '-';
      if (modalRole) modalRole.textContent = roleEl ? roleEl.textContent.trim() : '-';
      if (modalAvatar && avatarEl && avatarEl.getAttribute('src')) modalAvatar.src = avatarEl.getAttribute('src');
      overlay.style.display = 'flex';
    }

    document.querySelectorAll('.profile-links a').forEach(function (link) {
      var txt = (link.textContent || '').trim().toLowerCase();
      var href = (link.getAttribute('href') || '').toLowerCase();
      if (txt === 'settings' || href.indexOf('settings.php') !== -1) {
        link.textContent = 'Profile';
        link.setAttribute('href', '#');
        link.classList.add('profile-link-trigger');
        link.addEventListener('click', function (e) {
          e.preventDefault();
          var menu = link.closest('.profile-dropdown');
          if (menu) {
            menu.classList.remove('active');
            openProfileModal(menu);
          }
        });
      }
    });

    var closeBtn = document.getElementById('closeGlobalProfileInfo');
    if (closeBtn) {
      closeBtn.onclick = function () {
        overlay.style.display = 'none';
      };
    }
    overlay.onclick = function (e) {
      if (e.target === overlay) {
        overlay.style.display = 'none';
      }
    };
  }

  addClasses('body', ['bg-gradient-to-br', 'from-slate-100', 'via-blue-50', 'to-slate-200', 'text-slate-800', 'antialiased']);
  addClasses('.sidebar', [
    'bg-gradient-to-b',
    'from-slate-900',
    'via-blue-900',
    'to-slate-800',
    'text-white',
    'shadow-2xl',
    'rounded-l-3xl',
    'rounded-r-3xl',
    'border-r',
    'border-slate-700',
    'ml-4',
    'mt-5',
    'mb-5'
  ]);
  setStyle('.sidebar', { height: 'calc(100vh - 2.5rem)' });
  addClasses('.sidebar-top', ['px-2', 'pt-2']);
  addClasses('.sidebar h2', ['text-blue-50', 'font-semibold', 'leading-tight', 'mb-6']);
  addClasses('.sidebar-nav', ['gap-3', 'px-1']);
  setStyle('.sidebar-nav', { textAlign: 'left' });
  addClasses('.sidebar-bottom', ['px-2', 'pb-2', 'mt-6', 'mb-1', 'text-blue-100', 'text-[11px]', 'leading-relaxed']);
  addClasses('.logo-container', ['mb-4']);
  addClasses('.logo-img', ['ring-2', 'ring-white/30', 'shadow-md']);
  addClasses('.nav-item', [
    'rounded-lg',
    'px-3',
    'py-2.5',
    'mx-1',
    'bg-transparent',
    'border-0',
    'text-blue-50',
    'font-semibold',
    'transition',
    'duration-150',
    'flex',
    'items-center',
    'justify-start',
    'text-left'
  ]);
  setStyle('.sidebar-nav .nav-item', { textAlign: 'left', justifyContent: 'flex-start' });
  addClasses('.nav-item:hover', ['bg-white/10']);
  addClasses('.nav-item.active', ['bg-white/15', 'text-white']);
  addClasses('.main-content', ['bg-slate-50']);
  addClasses('.header', [
    'bg-gradient-to-r',
    'from-slate-900',
    'via-blue-900',
    'to-slate-800',
    'rounded-2xl',
    'p-6',
    'shadow-lg',
    'border',
    'border-slate-700',
    'mb-5'
  ]);
  addClasses('.header h1', ['text-white', 'font-bold', 'tracking-tight']);
  addClasses('.header p', ['text-blue-100']);
  setStyle('.header h1', { color: '#ffffff' });
  setStyle('.header p', { color: '#dbeafe' });
  setStyle('.header .icon-box', { filter: 'brightness(0) invert(1)' });
  setStyle('.header .icon-circle', { borderColor: 'rgba(255,255,255,0.55)' });

  addClasses('.cards-container', ['gap-4']);
  addClasses('.card', ['rounded-2xl', 'border', 'border-blue-100', 'shadow-md', 'bg-gradient-to-br', 'from-white', 'to-blue-50']);
  addClasses('.card p', ['text-slate-600', 'font-semibold']);
  // Card number colours come from CSS (--stat-blue/-yellow/-green/-red), which
  // resolves per theme. Stamping them inline here forced the light palette onto
  // the dark cards and outranked every stylesheet, so this only clears leftovers.
  function applyCardNumberColors() {
    document.querySelectorAll('.card .card-number').forEach(function (el) {
      el.style.removeProperty('color');
      el.style.removeProperty('opacity');
      el.style.removeProperty('visibility');
    });
  }
  var isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark' || document.documentElement.classList.contains('dark');
  if (!isDarkTheme) {
    setStyle('.card p', { color: '#334155' });
  } else {
    setStyle('.card p', { color: '#cbd5e1' });
  }
  applyCardNumberColors();

  window.addEventListener('bpis-theme-change', function (e) {
    var isDark = e.detail && e.detail.isDark;
    document.querySelectorAll('.card p').forEach(function (el) {
      if (isDark) {
        el.style.setProperty('color', '#cbd5e1', 'important');
      } else {
        el.style.setProperty('color', '#334155', 'important');
      }
    });
    applyCardNumberColors();
  });
  addClasses('.card-blue', ['from-blue-50', 'to-blue-100', 'border-blue-200']);
  addClasses('.card-yellow', ['from-amber-50', 'to-yellow-100', 'border-amber-200']);
  addClasses('.card-green', ['from-emerald-50', 'to-green-100', 'border-emerald-200']);
  addClasses('.card-red', ['from-rose-50', 'to-red-100', 'border-rose-200']);

  addClasses('.table-section', ['bg-white', 'rounded-2xl', 'shadow-sm', 'border', 'border-slate-200', 'overflow-hidden']);
  addClasses('table', ['min-w-full', 'divide-y', 'divide-slate-200']);
  addClasses('table thead', ['bg-slate-50']);
  addClasses('table thead th', ['px-4', 'py-3', 'text-slate-600', 'uppercase', 'tracking-wide', 'text-xs', 'font-semibold']);
  addClasses('table tbody tr', ['hover:bg-slate-50', 'transition-colors']);
  addClasses('table tbody td', ['px-4', 'py-3', 'text-slate-700', 'border-t', 'border-slate-100']);

  addClasses('.search-container input, #searchInput, .filter-select', ['rounded-xl', 'border-slate-300', 'focus:ring-2', 'focus:ring-blue-500', 'focus:border-blue-500']);
  addClasses('.nav-link.active', ['text-blue-700']);
  addClasses('.nav-item.active', ['bg-blue-600', 'text-white']);

  addClasses('button', ['transition', 'duration-150', 'ease-in-out']);
  addClasses('.asset-btn', ['inline-flex', 'items-center', 'justify-center', 'rounded-lg', 'px-4', 'py-2', 'text-sm', 'font-semibold']);
  addClasses('.asset-btn-primary', ['bg-blue-600', 'text-white', 'hover:bg-blue-700', 'shadow-sm']);
  addClasses('.asset-btn-secondary', ['bg-white', 'text-slate-700', 'border', 'border-slate-300', 'hover:bg-slate-50']);
  addClasses('.asset-btn-danger', ['bg-red-600', 'text-white', 'hover:bg-red-700', 'shadow-sm']);
  addClasses('.edit-asset-btn', ['!bg-amber-500', 'hover:!bg-amber-600', '!text-white', '!rounded-lg', 'shadow-sm']);
  addClasses('.delete-asset-btn', ['!bg-red-500', 'hover:!bg-red-600', '!text-white', '!rounded-lg', 'shadow-sm']);
  addClasses('.btn-receive', ['!bg-emerald-600', 'hover:!bg-emerald-700', 'shadow-sm']);
  addClasses('.btn-confirm', ['!bg-blue-600', 'hover:!bg-blue-700', '!text-white', '!rounded-lg', 'px-4', 'py-2']);
  addClasses('.btn-cancel', ['!border-blue-600', '!text-blue-600', 'hover:!bg-blue-50', '!rounded-lg', 'px-4', 'py-2']);

  addClasses('.logout-overlay, .asset-modal-overlay', ['fixed', 'inset-0', 'bg-black/40', 'backdrop-blur-sm', 'z-[2000]']);
  addClasses('.logout-modal, .asset-modal', ['bg-white', 'rounded-2xl', 'shadow-2xl', 'border', 'border-slate-200']);
  addClasses('.logout-modal', ['w-full', 'max-w-md', 'p-6']);
  addClasses('.asset-modal', ['w-full', 'max-w-3xl']);
  addClasses('.asset-modal-header', ['px-6', 'py-4', 'border-b', 'border-slate-200', 'bg-slate-50', 'rounded-t-2xl']);
  addClasses('.asset-modal-body', ['px-6', 'py-5']);
  addClasses('.asset-modal-footer, .logout-buttons', ['px-6', 'py-4', 'border-t', 'border-slate-200', 'bg-slate-50', 'rounded-b-2xl', 'flex', 'justify-end', 'gap-3']);
  addClasses('.asset-modal-label', ['block', 'text-sm', 'font-semibold', 'text-slate-700', 'mb-1']);
  addClasses('.asset-modal-input', ['w-full', 'rounded-lg', 'border', 'border-slate-300', 'px-3', 'py-2', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500', 'focus:border-blue-500']);

  enhanceLogoutModals();
  enhanceProfileLinks();
});
