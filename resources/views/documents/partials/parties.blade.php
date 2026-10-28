{{-- Who the document is for, and its facts - dates, references, the
     exchange rate - beside them. A customer's tax number always shows. --}}
<section class="parties">
    <div>
        <div class="label">{{ __($partyLabel, [], $lang) }}</div>
        @if ($customer)
            <div class="customer-name">{{ $customer['name'] }}</div>
            @foreach ($customer['billing_address'] ?? [] as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
            @if (in_array('customer_contact', $template['visible_fields'], true))
                @foreach (array_filter([$customer['email'] ?? null, $customer['phone'] ?? null]) as $line)
                    <div class="muted">{{ $line }}</div>
                @endforeach
            @endif
            @if (! empty($customer['tax_number']))
                <div class="muted">{{ __('Tax No.', [], $lang) }}: {{ $customer['tax_number'] }}</div>
            @endif
        @endif
    </div>
    <table class="facts">
        @foreach ($facts as [$name, $value])
            <tr>
                <td class="muted">{{ __($name, [], $lang) }}</td>
                <td class="num"><strong>{{ $value }}</strong></td>
            </tr>
        @endforeach
    </table>
</section>
