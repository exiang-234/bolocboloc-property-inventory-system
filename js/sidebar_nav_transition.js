(function () {
    var STORAGE_KEY = "bpis_snav_v1";
    var LEAVE_MS = 260;

    function sameDocumentLocation(href) {
        try {
            var u = new URL(href, window.location.href);
            return (
                u.origin === window.location.origin &&
                u.pathname === window.location.pathname &&
                u.search === window.location.search
            );
        } catch (e) {
            return false;
        }
    }

    if (sessionStorage.getItem(STORAGE_KEY) === "1") {
        document.documentElement.classList.add("bpis-snav-from-sidebar");
        sessionStorage.removeItem(STORAGE_KEY);
    }

    function runEnter() {
        var mc = document.querySelector(".main-content");
        if (!document.documentElement.classList.contains("bpis-snav-from-sidebar") || !mc) {
            return;
        }
        void mc.offsetWidth;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                document.documentElement.classList.add("bpis-snav-from-sidebar-active");
            });
        });
        var tid = window.setTimeout(cleanup, 450);
        function cleanup() {
            window.clearTimeout(tid);
            document.documentElement.classList.remove(
                "bpis-snav-from-sidebar",
                "bpis-snav-from-sidebar-active"
            );
            mc.removeEventListener("transitionend", onEnd);
        }
        function onEnd(ev) {
            if (ev.target !== mc || ev.propertyName !== "opacity") {
                return;
            }
            cleanup();
        }
        mc.addEventListener("transitionend", onEnd);
    }

    function onClick(e) {
        var a = e.target.closest(".sidebar .sidebar-nav a[href]");
        if (!a) {
            return;
        }
        if (a.getAttribute("href") === "#" || a.getAttribute("href") === "") {
            return;
        }
        if (a.target === "_blank" || a.getAttribute("download")) {
            return;
        }
        if (e.defaultPrevented || e.button !== 0) {
            return;
        }
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var href = a.href;
        if (!href || sameDocumentLocation(href)) {
            return;
        }
        var mc = document.querySelector(".main-content");
        if (!mc) {
            window.location.href = href;
            return;
        }
        e.preventDefault();
        sessionStorage.setItem(STORAGE_KEY, "1");
        document.body.classList.add("bpis-snav-leaving");
        window.setTimeout(function () {
            window.location.href = href;
        }, LEAVE_MS);
    }

    document.addEventListener("click", onClick, false);

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", runEnter);
    } else {
        runEnter();
    }
})();
