<aside
    x-data="{ open: false }"
    @toggle-sidebar.window="open = !open"
    class="contents"
>
    <div
        x-show="open"
        x-cloak
        @click="open = false"
        class="fixed inset-0 z-30 bg-steel/40 lg:hidden"
    ></div>

    <div
        class="fixed inset-y-0 left-0 z-40 w-[210px] -translate-x-full overflow-y-auto bg-side px-1 py-2 transition-transform lg:static lg:translate-x-0"
        :class="{ 'translate-x-0': open }"
    >
        <nav aria-label="Menü">
            <a
                href="{{ route('admin.dashboard') }}"
                @class([
                    'flex items-center gap-2 border border-l-[3px] px-2.5 py-2 text-[11px] font-semibold',
                    'border-line border-l-ochre-deep bg-paper text-soil-ink' => request()->routeIs('admin.dashboard'),
                    'border-line border-l-ochre bg-white text-ink hover:bg-ochre-soft hover:text-ochre-dark' => ! request()->routeIs('admin.dashboard'),
                ])
            >
                <i class="fa-solid fa-gauge w-3.5 text-center text-[11px] text-ochre" aria-hidden="true"></i>
                Gösterge paneli
            </a>
        </nav>
    </div>
</aside>
