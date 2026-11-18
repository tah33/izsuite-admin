{{-- The audit log's filters, in one toolbar row, and the export of what they pick (Doc 15 section 15.26). --}}
<div class="flex flex-wrap items-center gap-3 mb-4">
    <form action="{{ route('admin.audit-log.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
        <div class="search-input-wrapper flex-1 min-w-[200px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('What was done, or a record\'s ID...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-44 shrink-0">
            <select name="user_id" class="form-select" aria-label="{{ __('Admin') }}">
                <option value="">{{ __('All admins') }}</option>
                @foreach($admins as $id => $name)
                    <option value="{{ $id }}" @selected((string) request('user_id') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-36 shrink-0">
            <select name="action" class="form-select" aria-label="{{ __('Action') }}">
                <option value="">{{ __('All actions') }}</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40 shrink-0">
            <select name="model_type" class="form-select" aria-label="{{ __('Record') }}">
                <option value="">{{ __('All records') }}</option>
                @foreach($recordTypes as $type)
                    <option value="{{ $type }}" @selected(request('model_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-36 shrink-0">
            <select name="result" class="form-select" aria-label="{{ __('Result') }}">
                <option value="">{{ __('Any result') }}</option>
                @foreach($results as $value => $label)
                    <option value="{{ $value }}" @selected(request('result') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        {{-- .form-input is full width, so each date sits in its own sized box. --}}
        <div class="w-40 shrink-0">
            <input type="date" name="from" value="{{ request('from') }}" class="form-input" aria-label="{{ __('From') }}" title="{{ __('From') }}">
        </div>
        <div class="w-40 shrink-0">
            <input type="date" name="to" value="{{ request('to') }}" class="form-input" aria-label="{{ __('To') }}" title="{{ __('To') }}">
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
        </button>
        <a href="{{ route('admin.audit-log.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
            <i data-lucide="x" class="w-4 h-4"></i>
        </a>
    </form>

    @if(auth()->user()->role?->hasPermission('admin.audit-log.export'))
        <a href="{{ route('admin.audit-log.export', request()->only(['search', 'user_id', 'action', 'model_type', 'result', 'from', 'to'])) }}" class="btn btn-primary">
            <i data-lucide="download" class="w-4 h-4"></i>{{ __('Export CSV') }}
        </a>
    @endif

    @if($errors->any())
        <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
    @endif
</div>
