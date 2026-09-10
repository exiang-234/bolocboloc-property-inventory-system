        </div>
    </div>
<script>
(function () {
    const profileTrigger = document.getElementById('profileTrigger');
    const profileMenu = document.getElementById('profileMenu');
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutOverlay = document.getElementById('logoutOverlay');
    const confirmLogoutAction = document.getElementById('confirmLogoutAction');
    const cancelLogoutAction = document.getElementById('cancelLogoutAction');

    if (profileTrigger && profileMenu) {
        profileTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            profileMenu.classList.toggle('active');
        });
        document.addEventListener('click', function (e) {
            if (!profileMenu.contains(e.target) && e.target !== profileTrigger) {
                profileMenu.classList.remove('active');
            }
        });
    }
    if (logoutBtn && logoutOverlay) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            logoutOverlay.style.display = 'flex';
            if (profileMenu) profileMenu.classList.remove('active');
        });
    }
    if (cancelLogoutAction && logoutOverlay) {
        cancelLogoutAction.addEventListener('click', function () {
            logoutOverlay.style.display = 'none';
        });
    }
    if (confirmLogoutAction) {
        confirmLogoutAction.addEventListener('click', function () {
            window.location.href = 'logout.php';
        });
    }
    if (logoutOverlay) {
        logoutOverlay.addEventListener('click', function (e) {
            if (e.target === logoutOverlay) logoutOverlay.style.display = 'none';
        });
    }
})();
</script>
<?= $bpis_layout_footer_scripts ?? '' ?>
<script src="<?= htmlspecialchars($bpis_mobile_sidebar_js ?? 'js/treasurer_mobile_sidebar.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="realtime_notifications.js"></script>
</body>
</html>
