{{-- What the user has across all their businesses ($totals, from ClientService::totals()). --}}
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    @include('components.stat-card', ['label' => __('Workspaces'), 'value' => number_format($totals['businesses']), 'icon' => 'building-2', 'iconColor' => 'blue'])
    @include('components.stat-card', ['label' => __('Staff'), 'value' => number_format($totals['staff']), 'icon' => 'user-check', 'iconColor' => 'blue', 'trend' => __('Owner included')])
    @include('components.stat-card', ['label' => __('Locations'), 'value' => number_format($totals['locations']), 'icon' => 'map-pin', 'iconColor' => 'blue'])
    @include('components.stat-card', ['label' => __('Contacts'), 'value' => number_format($totals['contacts']), 'icon' => 'contact', 'iconColor' => 'blue'])
    @include('components.stat-card', ['label' => __('Items'), 'value' => number_format($totals['items']), 'icon' => 'package', 'iconColor' => 'blue'])
    @include('components.stat-card', ['label' => __('Storage used'), 'value' => \App\Support\Bytes::format($totals['storage']), 'icon' => 'hard-drive', 'iconColor' => 'blue'])
</div>
