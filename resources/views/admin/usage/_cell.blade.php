{{-- One limit's usage in a cell (Doc 15 section 15.18): used of the limit, the share used, and a bar coloured by its state. Expects $line (used, limit, percent, state) and $format, which writes an amount. --}}
@php
    $colour = ['ok' => 'bg-[var(--primary)]', 'warning' => 'bg-[var(--warning)]', 'full' => 'bg-[var(--danger)]', 'over' => 'bg-[var(--danger)]'][$line['state']] ?? 'bg-[var(--primary)]';
@endphp
<div class="min-w-[120px]">
    <span class="text-sm whitespace-nowrap {{ $line['state'] === 'ok' ? 'text-[var(--text-primary)]' : 'text-[var(--danger)] font-medium' }}">
        {{ $format($line['used']) }}{{ $line['limit'] !== null ? ' / '.$format($line['limit']) : '' }}
    </span>
    @if($line['limit'] !== null)
        <div class="h-1.5 mt-1 rounded-full bg-[var(--bg-secondary)] overflow-hidden">
            <div class="h-full rounded-full {{ $colour }}" style="width: {{ min(100, (float) $line['percent']) }}%"></div>
        </div>
        <span class="text-xs text-[var(--text-muted)]">{{ rtrim(rtrim(number_format((float) $line['percent'], 1), '0'), '.') }}%</span>
    @else
        <span class="block text-xs text-[var(--text-muted)]">{{ __('No limit') }}</span>
    @endif
</div>
