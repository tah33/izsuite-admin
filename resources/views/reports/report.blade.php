{{-- A report as a PDF (Doc 07 section 4): the business, the report and its
     period, its headline figures, the table with its totals - the heading
     repeated on every page - and its notes. Dates, numbers and money are
     written as the business's invoice template writes them. --}}
@php
    $cell = function (array $column, array $row) use ($format): string {
        $value = $row[$column['key']] ?? null;

        if ($value === null || $value === '') {
            return '';
        }

        return match ($column['type']) {
            'money'  => $format->money((string) $value, $column['currency'] ?? ($row['currency'] ?? '')),
            'number' => $format->quantity((string) $value),
            'date'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? $format->date((string) $value) : (string) $value,
            default  => (string) $value,
        };
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title }}</title>
    <style>
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: 'Noto Sans', 'Liberation Sans', Arial, sans-serif; font-size: 8.5pt; line-height: 1.4; color: #1f2937; }
        .head { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; padding-bottom: 10px; border-bottom: 3px solid {{ $color }}; }
        .business { font-size: 10pt; font-weight: 700; }
        h1 { margin: 2px 0 0; font-size: 15pt; color: {{ $color }}; }
        .period { color: #6b7280; text-align: right; }
        .summary { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 0; }
        .figure { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .figure span { display: block; font-size: 6.5pt; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6b7280; }
        .figure strong { font-size: 10pt; }
        table { width: 100%; margin-top: 12px; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { padding: 5px 6px; background: {{ $color }}; color: #fff; font-size: 6.5pt; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { border-top: 1.5px solid #9ca3af; border-bottom: 0; font-weight: 700; }
        .empty { padding: 24px 0; text-align: center; color: #6b7280; }
        .notes { margin-top: 14px; font-size: 7.5pt; color: #6b7280; }
        .notes p { margin: 0 0 3px; }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <div class="business">{{ $business }}</div>
            <h1>{{ $table->title }}</h1>
        </div>
        <div class="period">{{ $table->period }}</div>
    </div>

    @if ($table->summary)
        <div class="summary">
            @foreach ($table->summary as $figure)
                <div class="figure"><span>{{ $figure['label'] }}</span><strong>{{ $format->money($figure['value'], $figure['currency']) }}</strong></div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($table->columns as $column)
                    <th @class(['num' => in_array($column['type'], ['money', 'number'], true)])>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($table->rows as $row)
                <tr>
                    @foreach ($table->columns as $column)
                        <td @class(['num' => in_array($column['type'], ['money', 'number'], true)])>{{ $cell($column, $row) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($table->columns) }}" class="empty">Nothing to show for these filters.</td></tr>
            @endforelse
        </tbody>
        @if ($table->totals && $table->rows)
            <tfoot>
                <tr>
                    @foreach ($table->columns as $column)
                        <td @class(['num' => in_array($column['type'], ['money', 'number'], true)])>{{ $cell($column, $table->totals) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($table->notes)
        <div class="notes">
            @foreach ($table->notes as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif
</body>
</html>
