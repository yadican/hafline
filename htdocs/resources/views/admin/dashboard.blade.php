@extends('admin.layouts.app')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($modules as $module)
            <section class="border border-line border-t-[3px] border-t-ochre bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-ochre-deep">{{ $module['hint'] }}</p>
                <h2 class="mt-2 text-lg font-semibold text-soil">{{ $module['label'] }}</h2>
                <p class="mt-3 text-sm text-[#6a6560]">Henüz kayıt yok.</p>
            </section>
        @endforeach
    </div>
@endsection
