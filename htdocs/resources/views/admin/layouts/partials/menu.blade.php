<script>
(function () {
    var nav = document.getElementById('um-nav');
    if (!nav) return;

    function setOpen(sec, open) {
        if (!sec) return;
        sec.className = open ? 'um-section' : 'um-section um-collapsed';
        try { localStorage.setItem('um:' + sec.id, open ? '1' : '0'); } catch (e) {}
    }

    function isOpen(sec) {
        return sec.className.indexOf('um-collapsed') === -1;
    }

    function closeOthers(except) {
        for (var i = 0; i < sections.length; i++) {
            if (sections[i] === except) continue;
            setOpen(sections[i], false);
        }
    }

    var sections = nav.getElementsByClassName('um-section');

    nav.onclick = function (e) {
        var t = e.target || e.srcElement;
        while (t && t !== nav && !(t.getAttribute && t.getAttribute('data-um-toggle'))) {
            t = t.parentNode;
        }
        if (!t || t === nav) return;
        var id = t.getAttribute('data-um-toggle');
        var sec = document.getElementById(id);
        if (!sec) return;
        var willOpen = !isOpen(sec);
        if (willOpen) closeOthers(sec);
        setOpen(sec, willOpen);
        return false;
    };

    var activeSec = null;
    for (var i = 0; i < sections.length; i++) {
        if (sections[i].getElementsByClassName('um-active').length) {
            activeSec = sections[i];
            break;
        }
    }

    if (activeSec) {
        for (var j = 0; j < sections.length; j++) {
            setOpen(sections[j], sections[j] === activeSec);
        }
    }

    var menuBtn = document.getElementById('app-menu-btn');
    var toggle = document.getElementById('um-toggle');
    var backdrop = document.getElementById('app-menu-backdrop');
    var mq = window.matchMedia('(max-width: 860px)');
    var SIDEBAR_KEY = 'um:sidebar-collapsed';

    function isMobile() {
        return mq.matches;
    }

    function syncMobileBtn(open) {
        if (!menuBtn) return;
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuBtn.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
        var icon = menuBtn.querySelector('i');
        if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
    }

    function syncToggle(collapsed) {
        if (!toggle) return;
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', collapsed ? 'Menüyü aç' : 'Menüyü kapat');
        toggle.title = collapsed ? 'Menüyü göster' : 'Menüyü gizle';
    }

    function setMobileOpen(open) {
        document.body.classList.toggle('menu-open', open);
        if (backdrop) backdrop.hidden = !open;
        syncMobileBtn(open);
    }

    function setDesktopCollapsed(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        try { localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); } catch (e) {}
        syncToggle(collapsed);
    }

    try {
        if (localStorage.getItem(SIDEBAR_KEY) === '1') {
            document.body.classList.add('sidebar-collapsed');
        }
    } catch (e) {}

    syncToggle(document.body.classList.contains('sidebar-collapsed'));
    syncMobileBtn(false);

    if (menuBtn) {
        menuBtn.addEventListener('click', function () {
            if (isMobile()) {
                setMobileOpen(!document.body.classList.contains('menu-open'));
            }
        });
    }

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (isMobile()) return;
            setDesktopCollapsed(!document.body.classList.contains('sidebar-collapsed'));
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () { setMobileOpen(false); });
    }

    nav.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a') : null;
        if (link && isMobile()) setMobileOpen(false);
    });

    function onViewportChange() {
        setMobileOpen(false);
        syncMobileBtn(false);
        syncToggle(document.body.classList.contains('sidebar-collapsed'));
    }

    if (mq.addEventListener) {
        mq.addEventListener('change', onViewportChange);
    } else if (mq.addListener) {
        mq.addListener(onViewportChange);
    }
})();
</script>
