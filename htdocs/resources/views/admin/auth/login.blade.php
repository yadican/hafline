<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HafLine — Giriş</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['resources/css/admin.css'])
</head>
<body class="h-full bg-steel font-sans text-[#e6ebe8] antialiased">
    <div class="grid min-h-full grid-cols-1 lg:grid-cols-[1.15fr_minmax(320px,420px)]">
        <section class="relative flex min-h-[42vh] flex-col justify-end overflow-hidden px-6 py-8 sm:px-10 lg:min-h-full lg:px-14 lg:py-14" aria-label="HafLine">
            <div class="absolute inset-0 bg-[url('/img/login-santiye-bg.jpg')] bg-cover bg-center"></div>
            <div class="absolute inset-0 bg-linear-to-r from-steel/15 via-steel/55 to-steel/90 max-lg:bg-linear-to-b max-lg:from-steel/25 max-lg:via-steel/55 max-lg:to-steel/95"></div>
            <div class="relative z-10 max-w-[340px]">
                <img src="{{ asset('img/logo.png') }}" alt="HafLine" class="h-auto w-[min(320px,72vw)] lg:w-[min(320px,72vw)]">
                <p class="mt-4 max-w-[26ch] text-[1.05rem] font-medium leading-snug text-[#e6ebe8]/70">Şantiye ve hafriyat hattını tek noktadan yönetin.</p>
                <div class="mt-7 flex items-center gap-3 text-[0.72rem] font-semibold tracking-[0.16em] text-[#e6ebe8]/45 uppercase" aria-hidden="true">
                    <span>Operasyon hattı</span>
                    <span class="h-0.5 w-[120px] bg-linear-to-r from-ochre to-transparent"></span>
                </div>
            </div>
        </section>

        <aside class="flex flex-col justify-center border-t border-ochre/40 bg-steel/80 px-6 py-8 backdrop-blur-lg sm:px-10 lg:border-t-0 lg:border-l lg:px-10 lg:py-12">
            <div class="mb-7">
                <h2 class="font-[Syne] text-[1.55rem] font-bold tracking-tight text-white">Giriş yap</h2>
                <p class="mt-2 text-sm text-[#e6ebe8]/70">Yetkili hesabınızla devam edin.</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 border-l-[3px] border-[#e07070] bg-[#782424]/30 px-3 py-2.5 text-sm text-[#ffd0d0]" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" autocomplete="on">
                @csrf
                <div class="mb-4 flex flex-col gap-1.5">
                    <label for="email" class="text-[0.72rem] font-bold tracking-[0.08em] text-[#e6ebe8]/55 uppercase">Kullanıcı Adı</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="kullanıcı adınız" autofocus autocomplete="username" required class="h-12 border border-white/15 bg-white/5 px-3.5 text-[15px] font-medium text-white outline-none placeholder:text-[#e6ebe8]/30 focus:border-ochre focus:bg-ochre/10">
                </div>
                <div class="mb-4 flex flex-col gap-1.5">
                    <label for="password" class="text-[0.72rem] font-bold tracking-[0.08em] text-[#e6ebe8]/55 uppercase">Şifre</label>
                    <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required class="h-12 border border-white/15 bg-white/5 px-3.5 text-[15px] font-medium text-white outline-none placeholder:text-[#e6ebe8]/30 focus:border-ochre focus:bg-ochre/10">
                </div>
                <button type="submit" class="mt-1.5 flex h-[50px] w-full items-center justify-center gap-2.5 bg-ochre text-[0.95rem] font-bold tracking-wide text-[#1a140c] uppercase hover:bg-[#d49a3c]">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Giriş Yap
                </button>
            </form>

            <div class="mt-7 border-t border-white/10 pt-4 text-[0.7rem] font-semibold tracking-[0.12em] text-[#e6ebe8]/35 uppercase">HafLine · Hafriyat Yönetim</div>
        </aside>
    </div>
</body>
</html>
