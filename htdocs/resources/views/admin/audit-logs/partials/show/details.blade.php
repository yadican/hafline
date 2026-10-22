@php
    $json = static fn (?array $value): string => $value === null || $value === []
        ? '—'
        : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="space-y-6">
    <dl class="grid gap-4 md:grid-cols-2">
        <div>
            <dt class="text-sm text-slate-500">Kanal</dt>
            <dd>{{ $channelLabel }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">Zaman</dt>
            <dd>{{ $log->created_at?->format('d.m.Y H:i:s') ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">Kullanıcı</dt>
            <dd>{{ $log->user?->name ? $log->user->name.' ('.$log->user->username.')' : '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">İşlem</dt>
            <dd>{{ $log->actionLabel() }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">Method</dt>
            <dd>{{ $log->method ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">Status</dt>
            <dd>{{ $log->status ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">IP</dt>
            <dd>{{ $log->ip_address ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-slate-500">Route</dt>
            <dd>{{ $log->route ?: '—' }}</dd>
        </div>
        <div class="md:col-span-2">
            <dt class="text-sm text-slate-500">URL</dt>
            <dd class="break-all">{{ $log->url ?: '—' }}</dd>
        </div>
        <div class="md:col-span-2">
            <dt class="text-sm text-slate-500">User-Agent</dt>
            <dd class="break-all">{{ $log->user_agent ?: '—' }}</dd>
        </div>
    </dl>

    <div>
        <h3 class="mb-2 font-medium">Payload (request)</h3>
        <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-50 p-3 text-sm">{{ $json($log->payload) }}</pre>
    </div>
    <div>
        <h3 class="mb-2 font-medium">Response</h3>
        <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-50 p-3 text-sm">{{ $json($log->response) }}</pre>
    </div>
    <div>
        <h3 class="mb-2 font-medium">Önceki değerler</h3>
        <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-50 p-3 text-sm">{{ $json($log->old_values) }}</pre>
    </div>
    <div>
        <h3 class="mb-2 font-medium">Sonraki değerler</h3>
        <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-50 p-3 text-sm">{{ $json($log->new_values) }}</pre>
    </div>
    <div>
        <h3 class="mb-2 font-medium">Context</h3>
        <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-50 p-3 text-sm">{{ $json($log->context) }}</pre>
    </div>

    <div>
        <x-ui.button icon="fa-solid fa-arrow-left" :href="route($indexRoute)">Listeye dön</x-ui.button>
    </div>
</div>
