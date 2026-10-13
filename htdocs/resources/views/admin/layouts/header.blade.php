<header class="sticky top-0 z-20 border-b-[3px] border-ochre bg-steel">
    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" class="text-[#edd9b0] lg:hidden" @click="$dispatch('toggle-sidebar')" aria-label="Menüyü aç">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="min-w-0">
                <h1 class="truncate font-[Syne] text-xl font-bold tracking-tight text-white">{{ $title ?? 'Backoffice' }}</h1>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#edd9b0]/70">Hafriyat yönetim</p>
            </div>
        </div>

        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-[#edd9b0] hover:bg-white/5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-soil text-sm font-semibold text-[#edd9b0]">
                    {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                </span>
                <span class="hidden md:block">{{ Auth::user()->name }}</span>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-44 border border-line bg-white py-1 shadow-lg">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-ink hover:bg-ochre-soft hover:text-soil-dark">
                        Çıkış
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
