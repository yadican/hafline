@php
    use App\Enums\ReportColumnType;
    use App\Enums\ReportRowKind;
    use App\Services\Reports\Export\ReportCell;

    $columns = $document->columns;
    $columnCount = max(1, count($columns));
    $alignClass = fn ($column): string => match ($column->type) {
        ReportColumnType::Text => '',
        ReportColumnType::Date => 'is-center',
        default => 'is-end',
    };
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 12mm 11mm 16mm 11mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { margin: 0; color: #2a2e32; font-size: 8pt; line-height: 1.35; }

        .doc-footer { position: fixed; left: 0; right: 0; bottom: -10mm; height: 7mm; border-top: 0.6pt solid #d5d8de; padding-top: 1.5mm; font-size: 6.8pt; color: #6b7280; }
        .doc-footer table { width: 100%; border-collapse: collapse; }
        .doc-footer td { padding: 0; }

        .doc-head { width: 100%; border-collapse: collapse; border-bottom: 1.6pt solid #c4892e; margin-bottom: 3mm; }
        .doc-head td { vertical-align: bottom; padding: 0 0 2.5mm 0; }
        .doc-logo { width: 34mm; }
        .doc-logo img { height: 15mm; }
        .doc-title { font-size: 15pt; font-weight: bold; color: #0e1114; }
        .doc-subtitle { font-size: 9.5pt; color: #2a2e32; margin-top: 1mm; }
        .doc-meta { text-align: right; font-size: 7pt; color: #6b7280; }
        .doc-meta div { margin-top: 0.6mm; }
        .doc-meta strong { color: #2a2e32; }

        .section-head { background: #f3ebe0; color: #6b4515; font-size: 9pt; font-weight: bold; padding: 4px 6px; text-align: left; }

        table.report { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        table.report thead th { background: #2a2e32; color: #ffffff; font-weight: bold; font-size: 7.6pt; padding: 5px 5px; text-align: left; border-bottom: 1.4pt solid #c4892e; }
        table.report td { padding: 3.5px 5px; border-bottom: 0.5pt solid #eceef2; vertical-align: top; }
        table.report tr.is-zebra td { background: #fafafb; }
        table.report tr.is-opening td { background: #f3f4f6; font-style: italic; border-bottom-color: #d5d8de; }
        table.report tr.is-total td { background: #f3ebe0; font-weight: bold; color: #0e1114; border-top: 1pt solid #2a2e32; border-bottom: 0.5pt solid #d5d8de; }
        table.report tr.is-note td { color: #6b7280; font-style: italic; text-align: center; padding: 8px 5px; }
        table.report .is-end { text-align: right; white-space: nowrap; }
        table.report .is-center { text-align: center; white-space: nowrap; }
        .is-debit { color: #a2452f; }
        .is-credit { color: #3d6340; }

        .summary { width: 45%; margin-left: 55%; border-collapse: collapse; border-top: 1.4pt solid #c4892e; page-break-inside: avoid; }
        .summary td { padding: 4px 6px; border-bottom: 0.5pt solid #eceef2; font-size: 8.4pt; }
        .summary td.label { color: #6b7280; font-weight: bold; text-align: right; }
        .summary td.value { font-weight: bold; text-align: right; white-space: nowrap; color: #0e1114; }
        .summary tr:last-child td { background: #f3ebe0; }
    </style>
</head>
<body>
    <div class="doc-footer">
        <table>
            <tr>
                <td>{{ config('app.name') }} · {{ $document->title }}</td>
                <td style="text-align: center;">{{ $stamp }}</td>
                <td style="width: 30mm;"></td>
            </tr>
        </table>
    </div>

    <table class="doc-head">
        <tr>
            @if ($logo !== null)
                <td class="doc-logo"><img src="{{ $logo }}" alt="{{ config('app.name') }}"></td>
            @endif
            <td>
                <div class="doc-title">{{ $document->title }}</div>
                <div class="doc-subtitle">{{ $document->subtitle }}</div>
            </td>
            <td class="doc-meta">
                @foreach ($document->details as $label => $value)
                    <div>{{ $label }}: <strong>{{ $value }}</strong></div>
                @endforeach
            </td>
        </tr>
    </table>

    @foreach ($document->sections as $section)
        <table class="report">
            <thead>
                @if ($section->heading !== null)
                    <tr>
                        <th colspan="{{ $columnCount }}" class="section-head">{{ $section->heading }}</th>
                    </tr>
                @endif
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ $alignClass($column) }}">{{ $column->label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $zebra = false;
                @endphp
                @foreach ($section->displayRows($document->emptyMessage) as $row)
                    @php
                        $span = $row->labelSpan($columns);
                        $rowClass = match ($row->kind) {
                            ReportRowKind::Opening => 'is-opening',
                            ReportRowKind::Total => 'is-total',
                            ReportRowKind::Note => 'is-note',
                            ReportRowKind::Data => $zebra ? 'is-zebra' : '',
                        };

                        if ($row->kind === ReportRowKind::Data) {
                            $zebra = ! $zebra;
                        }
                    @endphp
                    <tr class="{{ $rowClass }}">
                        @if ($span > 0)
                            <td colspan="{{ $span }}">{{ $row->label }}</td>
                        @endif
                        @foreach ($columns as $index => $column)
                            @continue($index < $span)
                            @php
                                $value = $row->cell($column->key);
                                $toneClass = '';

                                if ($column->type === ReportColumnType::Balance && $value !== null) {
                                    $amount = round($column->numericValue($value), 2);
                                    $toneClass = $amount > 0 ? 'is-debit' : ($amount < 0 ? 'is-credit' : '');
                                }
                            @endphp
                            <td class="{{ $alignClass($column) }} {{ $toneClass }}">{{ $column->display($value) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($document->summary !== [])
        <table class="summary">
            @foreach ($document->summary as $label => $value)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td class="value">{{ $value instanceof ReportCell ? $value->display() : $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
