(function () {
  function bpisAppBase() {
    var meta = document.querySelector('meta[name="bpis-base"]');
    if (meta && meta.content) {
      return String(meta.content).replace(/\/$/, '');
    }
    var parts = window.location.pathname.split('/').filter(Boolean);
    for (var i = parts.length - 1; i >= 0; i--) {
      if (parts[i].toLowerCase() === 'bpis') {
        return '/' + parts.slice(0, i + 1).join('/');
      }
    }
    return '/BPIS';
  }

  function bpisResolveApiFile(fileName) {
    fileName = String(fileName || '').replace(/^\/+/, '');
    if (window.BPIS_API) {
      if (fileName === 'notifications_feed.php' && window.BPIS_API.feed) {
        return String(window.BPIS_API.feed);
      }
      if (fileName === 'mark_notification_read.php' && window.BPIS_API.markRead) {
        return String(window.BPIS_API.markRead);
      }
      if (fileName === 'mark_all_notifications_read.php' && window.BPIS_API.markAll) {
        return String(window.BPIS_API.markAll);
      }
    }
    try {
      var path = (window.location.pathname || '').toLowerCase();
      if (path.indexOf('/secretary/') !== -1 || path.indexOf('/treasurer/') !== -1 || path.indexOf('/captain/') !== -1) {
        return new URL('../' + fileName, window.location.href).href;
      }
      var match = path.match(/^(.*\/bpis\/)/i);
      if (match) {
        return match[1] + fileName;
      }
    } catch (e) { console.warn('BPIS API URL resolve failed:', e); }
    return bpisAppBase() + '/' + fileName;
  }

  function bpisMetaUrl(name, fallbackPath) {
    var fileName = String(fallbackPath || '').replace(/^\/+/, '');
    return bpisResolveApiFile(fileName);
  }

  function initRealtimeNotifications() {
    var headerIcons = document.querySelector('.header-icons');
    var trigger = headerIcons ? headerIcons.querySelector('.bpis-notif-trigger') : null;
    var icon = trigger ? trigger.querySelector('.icon-box') : (headerIcons ? headerIcons.querySelector('.icon-box') : null);
    if (!trigger || !icon || !headerIcons) return;
    if (headerIcons.dataset.bpisNotifInit === '1') return;
    headerIcons.dataset.bpisNotifInit = '1';

    var feedUrl = bpisMetaUrl('bpis-notifications-feed', 'notifications_feed.php');
    var markReadUrl = bpisMetaUrl('bpis-mark-notification-read', 'mark_notification_read.php');
    var markAllUrl = bpisMetaUrl('bpis-mark-all-notifications-read', 'mark_all_notifications_read.php');

    var base = bpisAppBase();
    var lastUnread = -1;
    var hasFetchedOnce = false;
    var toastTimer = null;

    var portal = document.getElementById('bpis-notif-portal');
    if (!portal) {
      portal = document.createElement('div');
      portal.id = 'bpis-notif-portal';
      portal.innerHTML = '<div class="bpis-notif-backdrop" id="bpis-notif-backdrop"></div>';
      document.body.appendChild(portal);
    }

    var backdrop = portal.querySelector('#bpis-notif-backdrop');
    var panel = portal.querySelector('.bpis-notif-panel');
    if (!panel) {
      panel = document.createElement('div');
      panel.className = 'bpis-notif-panel';
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', 'Notifications');
      portal.appendChild(panel);
    }

    var toast = document.getElementById('bpis-notif-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'bpis-notif-toast';
      toast.className = 'bpis-notif-toast';
      portal.appendChild(toast);
    }

    var badge = trigger.querySelector('.bpis-notif-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'bpis-notif-badge';
      trigger.appendChild(badge);
    }

    function escapeHtml(str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function normalizeLink(link) {
      var raw = String(link || '').trim();
      if (!raw) return '';
      if (raw.indexOf('http://') === 0 || raw.indexOf('https://') === 0 || raw.indexOf('/') === 0) {
        return raw;
      }
      return base + '/' + raw.replace(/^\/+/, '');
    }

    var RELATIONSHIP_LABELS = {
      request_approved: 'Request approved',
      request_rejected: 'Request rejected',
      new_borrow_request: 'New borrow request',
      item_received: 'Item received',
      overdue_alert: 'Overdue'
    };

    function formatRelationship(code) {
      var raw = String(code || '').trim();
      if (!raw) return '';
      if (Object.prototype.hasOwnProperty.call(RELATIONSHIP_LABELS, raw)) return RELATIONSHIP_LABELS[raw];
      return raw.replace(/_/g, ' ');
    }

    function positionPanel() {
      var rect = trigger.getBoundingClientRect();
      var panelWidth = Math.min(320, window.innerWidth - 24);
      var left = rect.right - panelWidth;
      if (left < 12) left = 12;
      if (left + panelWidth > window.innerWidth - 12) {
        left = window.innerWidth - panelWidth - 12;
      }
      panel.style.top = Math.round(rect.bottom + 10) + 'px';
      panel.style.left = Math.round(left) + 'px';
      panel.style.right = 'auto';
      panel.style.width = panelWidth + 'px';
    }

    function setPanelLoading() {
      panel.innerHTML = '<div class="bpis-notif-panel-loading">Loading notifications…</div>';
    }

    function openPanel() {
      setPanelLoading();
      positionPanel();
      backdrop.classList.add('is-open');
      panel.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      fetchNotifications();
    }

    function closePanel() {
      backdrop.classList.remove('is-open');
      panel.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    }

    function togglePanel() {
      if (panel.classList.contains('is-open')) {
        closePanel();
      } else {
        openPanel();
      }
    }

    function hideToast() {
      toast.classList.remove('is-open');
      toast.innerHTML = '';
      if (toastTimer) {
        clearTimeout(toastTimer);
        toastTimer = null;
      }
    }

    function showToast(title, message) {
      if (!String(title || message).trim()) return;
      toast.innerHTML =
        '<div class="bpis-notif-toast-title">' + escapeHtml(title) + '</div>' +
        '<div class="bpis-notif-toast-message">' + escapeHtml(message) + '</div>';
      toast.classList.add('is-open');
      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(hideToast, 6000);
    }

    function renderNotifications(notifications) {
      if (!notifications.length) {
        panel.innerHTML =
          '<div class="notif-header-bar" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 12px;">' +
            '<button type="button" class="notif-mark-all-read" style="font-size:10px;padding:4px 8px;border-radius:999px;cursor:pointer;">Mark all as read</button>' +
          '</div>' +
          '<div class="bpis-notif-panel-empty">No notifications yet.</div>';
        return;
      }

      var listHtml = notifications.map(function (n) {
        var isUnread = Number(n.is_read) === 0;
        var unreadClass = isUnread ? ' notif-item--unread' : '';
        var actionLabel = isUnread ? 'Mark as read' : 'Mark as unread';
        var nextReadValue = isUnread ? 1 : 0;
        var itemLink = normalizeLink(n.link);
        var relLabel = formatRelationship(n.relationship);
        var relHtml = relLabel
          ? '<span class="notif-rel-chip" style="display:inline-block;margin-top:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:999px;">' + escapeHtml(relLabel) + '</span>'
          : '';
        var reqId = n.borrowing_request_id != null && n.borrowing_request_id !== '' ? parseInt(n.borrowing_request_id, 10) : 0;
        var borId = n.borrower_id != null && n.borrower_id !== '' ? parseInt(n.borrower_id, 10) : 0;
        if (isNaN(reqId)) reqId = 0;
        if (isNaN(borId)) borId = 0;
        var refParts = [];
        if (reqId > 0) refParts.push('Borrowing request #' + reqId);
        if (borId > 0) refParts.push('Borrower #' + borId);
        var refHtml = refParts.length
          ? '<div class="notif-ref-text" style="font-size:10px;margin-top:4px;">' + escapeHtml(refParts.join(' · ')) + '</div>'
          : '';
        return (
          '<div class="notif-item' + unreadClass + '" data-id="' + n.id + '" data-link="' + escapeHtml(itemLink) + '" style="padding:10px 12px;cursor:pointer;">' +
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">' +
              '<div style="flex:1;">' +
                '<div class="notif-title" style="font-size:12px;font-weight:700;">' + escapeHtml(n.title) + '</div>' +
                relHtml +
                refHtml +
              '</div>' +
              '<button type="button" class="notif-toggle-read" data-id="' + n.id + '" data-next-read="' + nextReadValue + '" style="font-size:10px;padding:3px 6px;border-radius:999px;cursor:pointer;">' + actionLabel + '</button>' +
            '</div>' +
            '<div class="notif-message" style="font-size:12px;margin-top:3px;">' + escapeHtml(n.message) + '</div>' +
            '<div class="notif-time" style="font-size:11px;margin-top:4px;">' + escapeHtml(n.created_at) + '</div>' +
          '</div>'
        );
      }).join('');

      panel.innerHTML =
        '<div class="notif-header-bar" style="position:sticky;top:0;z-index:1;display:flex;justify-content:flex-end;gap:6px;padding:10px 12px;">' +
          '<button type="button" class="notif-mark-all-read" style="font-size:10px;padding:4px 8px;border-radius:999px;cursor:pointer;">Mark all as read</button>' +
        '</div>' + listHtml;
    }

    async function fetchNotifications() {
      try {
        var res = await fetch(feedUrl, { credentials: 'same-origin' });
        var raw = await res.text();
        var data = null;
        try {
          data = JSON.parse(raw);
        } catch (parseErr) {
          console.warn('BPIS notifications JSON parse failed:', parseErr, raw ? raw.substring(0, 200) : '');
          if (panel.classList.contains('is-open')) {
            panel.innerHTML = '<div class="bpis-notif-panel-empty">Could not load notifications. Please refresh the page.</div>';
          }
          return;
        }

        if (!res.ok || !data || !data.success) {
          if (panel.classList.contains('is-open')) {
            var errMsg = 'Could not load notifications.';
            if (data && data.error === 'login_required') {
              errMsg = 'Session expired. Please sign in again.';
            } else if (data && data.error === 'wrong_role') {
              errMsg = 'Your account cannot view these notifications.';
            } else if (data && data.error === 'db_error') {
              errMsg = 'Notification database error. Contact admin.';
            }
            panel.innerHTML = '<div class="bpis-notif-panel-empty">' + errMsg + '</div>';
          }
          return;
        }

        var unread = Number(data.unread_count || 0);
        var notifications = data.notifications || [];

        if (unread > 0) {
          badge.style.display = 'flex';
          badge.textContent = unread > 99 ? '99+' : String(unread);
        } else {
          badge.style.display = 'none';
        }

        if (hasFetchedOnce && unread > lastUnread) {
          var newest = notifications.find(function (n) { return Number(n.is_read) === 0; }) || notifications[0];
          if (newest) {
            showToast(newest.title || 'New notification', newest.message || '');
          }
        }

        lastUnread = unread;
        hasFetchedOnce = true;
        renderNotifications(notifications);
      } catch (e) {
        console.warn('BPIS notifications fetch failed:', e);
        if (panel.classList.contains('is-open')) {
          panel.innerHTML = '<div class="bpis-notif-panel-empty">Could not load notifications.</div>';
        }
      }
    }

    function isNotificationTarget(target) {
      return trigger.contains(target) || panel.contains(target);
    }

    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      togglePanel();
    });

    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        togglePanel();
      }
      if (e.key === 'Escape') {
        closePanel();
      }
    });

    backdrop.addEventListener('click', function () {
      closePanel();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel.classList.contains('is-open')) {
        closePanel();
      }
    });

    window.addEventListener('resize', function () {
      if (panel.classList.contains('is-open')) positionPanel();
    });

    toast.addEventListener('click', function () {
      hideToast();
      openPanel();
    });

    async function setReadState(id, isRead) {
      var form = new URLSearchParams();
      form.append('id', id);
      form.append('is_read', isRead);
      await fetch(markReadUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString(),
        credentials: 'same-origin'
      });
    }

    async function setAllReadState(isRead) {
      var form = new URLSearchParams();
      form.append('is_read', isRead);
      await fetch(markAllUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString(),
        credentials: 'same-origin'
      });
    }

    panel.addEventListener('click', async function (e) {
      var toggleBtn = e.target.closest('.notif-toggle-read');
      if (toggleBtn) {
        e.stopPropagation();
        var id = toggleBtn.getAttribute('data-id');
        var nextRead = toggleBtn.getAttribute('data-next-read');
        await setReadState(id, nextRead);
        fetchNotifications();
        return;
      }

      var markAllReadBtn = e.target.closest('.notif-mark-all-read');
      if (markAllReadBtn) {
        e.stopPropagation();
        await setAllReadState(1);
        fetchNotifications();
        return;
      }

      var item = e.target.closest('.notif-item');
      if (!item) return;
      var itemId = item.getAttribute('data-id');
      var link = item.getAttribute('data-link');
      await setReadState(itemId, 1);
      fetchNotifications();

      if (link) {
        window.location.href = link;
      }
    });

    fetchNotifications();
    setInterval(fetchNotifications, 8000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRealtimeNotifications);
  } else {
    initRealtimeNotifications();
  }
})();
