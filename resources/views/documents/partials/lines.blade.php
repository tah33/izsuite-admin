{{-- The lines, with only the columns the template keeps and the lines use -
     no tax column on a document without tax (Doc 06 section 16). The heading
     repeats at the top of every page. --}}
<table class="lines">
    <thead>
        <tr>
            <th>{{ __('Item', [], $lang) }}</th>
            @if ($columns['quantity'])
                <th class="num">{{ __('Qty', [], $lang) }}</th>
            @endif
            @if ($columns['unit_price'])
                <th class="num">{{ __('Price', [], $lang) }}</th>
            @endif
            @if ($columns['discount'])
                <th class="num">{{ __('Discount', [], $lang) }}</th>
            @endif
            @if ($columns['tax'])
                <th>{{ __('Tax', [], $lang) }}</th>
            @endif
            <th class="num">{{ __('Amount', [], $lang) }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>
                    <div class="line-name">{{ $line['name'] }}</div>
                    @foreach ($line['notes'] as $note)
                        <div class="line-note">{{ $note }}</div>
                    @endforeach
                </td>
                @if ($columns['quantity'])
                    <td class="num">{{ $line['quantity'] }}</td>
                @endif
                @if ($columns['unit_price'])
                    <td class="num">{{ $line['unit_price'] }}</td>
                @endif
                @if ($columns['discount'])
                    <td class="num">{{ $line['discount'] }}</td>
                @endif
                @if ($columns['tax'])
                    <td>{{ $line['tax'] }}</td>
                @endif
                <td class="num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
