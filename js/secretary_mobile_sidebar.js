(function () {
    const toggle = document.getElementById('mobileNavToggle');
    const mobileClose = document.getElementById('mobileNavClose');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    function setExpanded(value) {
        if (toggle) toggle.setAttribute('aria-expanded', value ? 'true' : 'false');
        if (overlay) overlay.setAttribute('aria-hidden', value ? 'false' : 'true');
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        setExpanded(false);
    }

    function openSidebar() {
        body.classList.add('sidebar-open');
        setExpanded(true);
    }

    closeSidebar();

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) closeSidebar();
    });

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (body.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) overlay.addEventListener('click', closeSidebar);
    if (mobileClose) mobileClose.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSidebar();
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('.sidebar .sidebar-nav a[href]');
        if (link && window.innerWidth <= 768) closeSidebar();
    });
})();
