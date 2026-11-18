{{-- Under the document its secure link opens, on the full Sales app (Doc 16
     Phase G2 - CustomerResponses::screen): the customer's answer to a sent
     quotation, and their comments. Plain forms, posted back to the link -
     the page has no scripts. Shown on screen only. --}}
<style>
    .respond { display: grid; gap: 12px; max-width: 820px; margin: 16px auto 0; }
    .respond__box { padding: 24px 40px; border-radius: 8px; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .08); }
    .respond__box h2 { margin: 0 0 4px; font-size: 16px; }
    .respond__lead { margin: 0 0 14px; color: #6b7280; font-size: 13px; }
    .respond__note { margin: 0; padding: 12px 16px; border-radius: 8px; background: #fff; font-size: 14px; }
    .respond__note--done { background: #ecfdf3; color: #166534; }
    .respond__note--error { background: #fef2f2; color: #991b1b; }
    .respond form { display: grid; gap: 10px; margin: 0; }
    .respond label { display: grid; gap: 4px; font-size: 13px; font-weight: 600; }
    .respond input, .respond textarea { padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; font-weight: 400; }
    .respond textarea { min-height: 72px; resize: vertical; }
    .respond button { justify-self: start; padding: 9px 16px; border: 0; border-radius: 6px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
    .respond button.respond__decline { background: #fff; border: 1px solid #d1d5db; color: #374151; }
    .respond details { margin-top: 12px; }
    .respond summary { cursor: pointer; font-size: 13px; font-weight: 600; color: #6b7280; }
    .respond details form { margin-top: 10px; }
    .respond__comment { margin: 0 0 12px; padding: 10px 14px; border-radius: 6px; background: #f3f4f6; font-size: 14px; }
    .respond__comment p { margin: 0 0 4px; white-space: pre-line; }
    .respond__comment span { color: #6b7280; font-size: 12px; }
    @media (max-width: 640px) {
        .respond__box { padding: 20px; }
    }
</style>

<section class="respond" aria-label="Respond">
    @if (session('responded'))
        <p class="respond__note respond__note--done" role="status">{{ session('responded') }}</p>
    @endif

    @if ($errors->any())
        <p class="respond__note respond__note--error" role="alert">{{ $errors->first() }}</p>
    @endif

    @if ($screen['answer'])
        <p class="respond__note">
            {{ $screen['answer']['kind'] === 'accepted' ? 'Accepted' : 'Declined' }}{{ $screen['answer']['name'] ? ' by '.$screen['answer']['name'] : '' }} on {{ $screen['answer']['at'] }}.
        </p>
    @elseif ($screen['answerable'])
        <div class="respond__box">
            <h2>Your Answer</h2>
            <p class="respond__lead">Accept the quotation, or decline it, and {{ $screen['business'] }} is told at once.</p>

            <form method="post" action="{{ $screen['accept_url'] }}">
                @csrf
                <label>Your Name <input name="name" maxlength="100" autocomplete="name" value="{{ old('name') }}"></label>
                <button type="submit">Accept Quotation</button>
            </form>

            <details>
                <summary>Decline it instead</summary>
                <form method="post" action="{{ $screen['reject_url'] }}">
                    @csrf
                    <label>Your Name <input name="name" maxlength="100" autocomplete="name" value="{{ old('name') }}"></label>
                    <label>Why <textarea name="reason" maxlength="1000">{{ old('reason') }}</textarea></label>
                    <button type="submit" class="respond__decline">Decline Quotation</button>
                </form>
            </details>
        </div>
    @endif

    @if ($screen['commenting'])
        <div class="respond__box">
            <h2>Comments</h2>
            <p class="respond__lead">A question or a note for {{ $screen['business'] }}, about this document.</p>

            @foreach ($screen['comments'] as $comment)
                <div class="respond__comment">
                    <p>{{ $comment['body'] }}</p>
                    <span>{{ $comment['name'] ?: 'Sent' }} · {{ $comment['at'] }}</span>
                </div>
            @endforeach

            <form method="post" action="{{ $screen['comment_url'] }}">
                @csrf
                <label>Your Name <input name="name" maxlength="100" autocomplete="name" value="{{ old('name') }}"></label>
                <label>Comment <textarea name="body" maxlength="2000" required>{{ old('body') }}</textarea></label>
                <button type="submit">Send Comment</button>
            </form>
        </div>
    @endif
</section>
