{{-- The totals - an issued invoice's payments and balance among them - then
     the texts the template keeps, and a signature or stamp image. --}}
<table class="totals">
    @foreach ($totals as [$name, $value, $strong])
        <tr @class(['strong' => $strong])>
            <td>{{ __($name, [], $lang) }}</td>
            <td class="num">{{ $value }}</td>
        </tr>
    @endforeach
</table>

@if ($texts)
    <section class="texts">
        @foreach ($texts as [$name, $text])
            <div class="text-block">
                <div class="label">{{ __($name, [], $lang) }}</div>{{ $text }}
            </div>
        @endforeach
    </section>
@endif

@if ($signature)
    <div class="signature">
        <img src="{{ $signature }}" alt="">
        <div class="muted">{{ __('Authorised Signature', [], $lang) }}</div>
    </div>
@endif
