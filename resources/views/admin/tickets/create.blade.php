@extends('layouts.admin')
@section('title', __('Create Ticket'))

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
@endpush

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ $business ? route('admin.businesses.show', $business->id) : route('admin.tickets.index') }}" class="hover:underline">← {{ $business ? $business->name : __('Back to tickets') }}</a>
        </div>
        <h1 class="page-title">{{ __('Create Ticket') }}</h1>
    </div>

    <form action="{{ route('admin.tickets.store') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Form --}}
            <div class="lg:col-span-2 space-y-5">
                {{-- Subject --}}
                <div>
                    <label for="subject" class="form-label">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                           class="form-input w-full" placeholder="{{ __('Brief description of the issue') }}" required>
                    @error('subject')
                        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Message --}}
                <div>
                    <label class="form-label">{{ __('Message') }} <span class="text-red-500">*</span></label>
                    <textarea id="summernote-create" name="message">{{ old('message') }}</textarea>
                    @error('message')
                        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Sidebar Options --}}
            <div class="space-y-5">
                {{-- From a business's page, the case is about that business and for someone in it, its owner first (Doc 15 section 15.22). --}}
                @if($business)
                    <input type="hidden" name="workspace_id" value="{{ $business->id }}">
                    <div>
                        <span class="form-label">{{ __('Business') }}</span>
                        <p class="text-sm font-medium text-[var(--text-primary)]">{{ $business->name }}</p>
                    </div>
                @endif

                {{-- User --}}
                <div>
                    <label for="user_id" class="form-label">{{ __('User') }} <span class="text-red-500">*</span></label>
                    <select name="user_id" id="user_id" class="form-select w-full" required>
                        <option value="">{{ __('Select user...') }}</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ (int) old('user_id', $business?->user_id) === $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="form-label">{{ __('Priority') }}</label>
                    <select name="priority" id="priority" class="form-select w-full">
                        <option value="low" {{ old('priority', 'low') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                        <option value="medium" {{ old('priority') === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                    </select>
                </div>

                {{-- Assign to Staff (nullable) --}}
                <div>
                    <label for="assigned_to" class="form-label">{{ __('Assign to Staff') }} <span class="text-xs text-gray-400">({{ __('optional') }})</span></label>
                    <select name="assigned_to" id="assigned_to" class="form-select w-full">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" {{ old('assigned_to') == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn btn-primary w-full">
                    <i data-lucide="plus" class="w-4 h-4 me-2"></i> {{ __('Create Ticket') }}
                </button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script>
        $(function() {
            $('#summernote-create').summernote({
                height: 200,
                placeholder: '{{ __("Describe the issue in detail...") }}',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'strikethrough']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link']],
                    ['view', ['codeview']],
                ],
            });
        });
    </script>
@endpush



