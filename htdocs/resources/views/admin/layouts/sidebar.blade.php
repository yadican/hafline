<div class="um-nav" id="um-nav">
    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.dashboard')]) id="um-sec-home">
        <button type="button" class="um-title" data-um-toggle="um-sec-home">
            <span class="um-title-text">
                <i class="fa-solid fa-gauge um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Genel</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.dashboard') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.dashboard')])>Gösterge paneli</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.examples.*')]) id="um-sec-examples">
        <button type="button" class="um-title" data-um-toggle="um-sec-examples">
            <span class="um-title-text">
                <i class="fa-solid fa-layer-group um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Örnek kayıtlar</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.examples.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.examples.*')])>Kayıt listesi</a>
        </div>
    </div>
</div>
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
    var backdrop = document.getElementById('app-menu-backdrop');

    function setMenu(open) {
        document.body.classList.toggle('menu-open', open);
        if (menuBtn) menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (backdrop) backdrop.hidden = !open;
    }

    if (menuBtn) {
        menuBtn.onclick = function () {
            setMenu(!document.body.classList.contains('menu-open'));
        };
    }
    if (backdrop) backdrop.onclick = function () { setMenu(false); };
    nav.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a') : null;
        if (link) setMenu(false);
    });
})();
</script>
