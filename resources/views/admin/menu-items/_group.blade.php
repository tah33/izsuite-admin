{{-- One Menu Builder list: $title, $subtitle, $rows (items with their children), $createParams --}}
<div class="card">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <h2 class="section-title">{{ $title }}</h2>
            <p class="text-xs text-[var(--text-muted)]">{{ $subtitle }}</p>
        </div>
        <a href="{{ route('admin.menu-items.create', $createParams) }}" class="btn btn-xs btn-secondary">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> {{ __('Add') }}
        </a>
    </div>

    @if($rows->isEmpty())
        <p class="text-sm text-[var(--text-muted)]">{{ __('No items yet.') }}</p>
    @else
        <ul class="menu-sortable space-y-1.5" data-reorder-url="{{ route('admin.menu-items.reorder') }}">
            @foreach($rows as $row)
                <li data-id="{{ $row['item']->id }}" class="rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)]">
                    @include('admin.menu-items._row', ['item' => $row['item']])

                    @if($row['children']->isNotEmpty())
                        <ul class="menu-sortable space-y-1 ms-8 me-2 mb-2" data-reorder-url="{{ route('admin.menu-items.reorder') }}">
                            @foreach($row['children'] as $child)
                                <li data-id="{{ $child->id }}" class="rounded-md border border-dashed border-[var(--card-border)]">
                                    @include('admin.menu-items._row', ['item' => $child])
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
