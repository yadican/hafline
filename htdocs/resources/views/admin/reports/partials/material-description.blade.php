@if (! empty($row['invoice_id']))
    <a href="{{ route('admin.invoices.show', $row['invoice_id']) }}">{{ $row['description'] }}</a>
@else
    {{ $row['description'] }}
@endif
