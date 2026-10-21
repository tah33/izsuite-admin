@extends('layouts.admin')
@section('title', __('Menu Builder'))

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <p class="text-sm flex-1 min-w-[240px] text-[var(--text-secondary)]">
            {{ __('The business sidebar: Core Workspace, the pages inside each app, and the bottom links. An app\'s own entry - its name, icon and place - comes from Apps. Drag to reorder; businesses get the change on their next page load. A new item needs no deploy, but the page it opens must exist in the frontend.') }}
        </p>
        <a href="{{ route('admin.menu-items.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Add Item') }}
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">
        @include('admin.menu-items._group', [
            'title' => __('Core Workspace'), 'subtitle' => __('Always shown, whatever apps are on'),
            'rows' => $core, 'createParams' => ['section' => 'core'],
        ])

        @include('admin.menu-items._group', [
            'title' => __('Bottom'), 'subtitle' => __('Below the apps'),
            'rows' => $bottom, 'createParams' => ['section' => 'bottom'],
        ])

        @foreach($families->filter(fn ($family) => $family['items']->isNotEmpty()) as $family)
            @include('admin.menu-items._group', [
                'title' => $family['name'], 'subtitle' => __('Inside the app').' · '.$family['key'],
                'rows' => $family['items'], 'createParams' => ['section' => 'apps', 'family' => $family['key']],
            ])
        @endforeach

        @php($empty = $families->filter(fn ($family) => $family['items']->isEmpty()))
        @if($empty->isNotEmpty())
            <div class="card">
                <h2 class="section-title mb-1">{{ __('Other apps') }}</h2>
                <p class="text-sm mb-3 text-[var(--text-secondary)]">{{ __('These apps have no pages in the sidebar yet.') }}</p>
                <form action="{{ route('admin.menu-items.create') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="section" value="apps">
                    <div class="flex-1 min-w-[200px]">
                        <select name="family" class="form-select" aria-label="{{ __('App') }}">
                            @foreach($empty as $family)
                                <option value="{{ $family['key'] }}">{{ $family['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Add a page') }}
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    // Each list reorders on its own; dropping saves that list's order.
    document.querySelectorAll('.menu-sortable').forEach(function (list) {
        Sortable.create(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                var ids = Array.prototype.map.call(list.children, function (li) { return li.dataset.id; });

                $.ajax({
                    url: list.dataset.reorderUrl,
                    type: 'PUT',
                    data: JSON.stringify({ ids: ids }),
                    contentType: 'application/json',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                })
                    .done(function (response) { window.AdminToast && window.AdminToast.success(response.message || 'Order saved.'); })
                    .fail(function () { window.AdminToast && window.AdminToast.error('The order could not be saved. Reload and try again.'); });
            },
        });
    });
</script>
@endpush
