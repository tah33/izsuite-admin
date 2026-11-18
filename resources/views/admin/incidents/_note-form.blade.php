{{-- A note on the incident's timeline (Doc 15 section 15.23): what happened, the status it moves to, and who is responsible from now. Expects $incident and $staff. --}}
<form action="{{ route('admin.incidents.note', $incident->id) }}" method="POST" class="card">
    @csrf
    <h3 class="section-title mb-3">{{ __('Add a Note') }}</h3>

    <label class="form-label" for="body">{{ __('Note') }}</label>
    <textarea name="body" id="body" rows="4" class="form-input mb-3" maxlength="5000" required placeholder="{{ __('What was found or done.') }}"></textarea>

    <label class="form-label" for="status">{{ __('Status') }}</label>
    <div class="mb-3">
        <select name="status" id="status" class="form-select">
            @foreach(\App\Models\Admin\Incident::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($incident->status === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>

    <label class="form-label" for="responsible_id">{{ __('Responsible') }}</label>
    <div class="mb-4">
        <select name="responsible_id" id="responsible_id" class="form-select">
            <option value="">{{ __('Nobody') }}</option>
            @foreach($staff as $admin)
                <option value="{{ $admin->id }}" @selected((int) $incident->responsible_id === (int) $admin->id)>{{ $admin->name }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="btn btn-primary w-full justify-center"><i data-lucide="message-square-plus" class="w-4 h-4"></i>{{ __('Add Note') }}</button>
</form>
