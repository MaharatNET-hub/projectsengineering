@extends('v2.layouts.admin', ['title' => $s->project_name])
@section('crumb')<div class="mute" style="font-size:12.5px"><a href="{{ route('v2.admin.submissions') }}">{{ __('Submissions') }}</a> › <span class="mono">{{ $s->code }}</span></div>@endsection
@section('actions')
  @if ($s->hasOriginal() && $canEdit)
    @if ($review)<a class="btn primary" href="{{ route('v2.admin.submissions.workspace', $s) }}"><x-v2.icon name="eye"/>{{ __('Open the check') }}</a>
    @else<a class="btn primary" href="{{ route('v2.admin.submissions.workspace', [$s, 'start' => 1]) }}"><x-v2.icon name="check"/>{{ __('Start the check now') }}</a>@endif
  @elseif ($s->hasOriginal() && $review)<a class="btn" href="{{ route('v2.admin.submissions.workspace', $s) }}"><x-v2.icon name="eye"/>{{ __('View the check') }}</a>@endif
  @if ($s->outputPath())<a class="btn" href="{{ route('v2.admin.submissions.issued', $s) }}"><x-v2.icon name="down"/>{{ ($review['final'] ?? false) ? __('Issued PDF') : __('Draft PDF') }}</a>@endif
@endsection
@section('content')
<div class="grid g21">
  <div class="grid">
    <div class="card pad">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px"><h2 style="margin:0">{{ __('Review') }}</h2>@include('v2.admin.partials.status')</div>
      @if ($review)
        <div class="kpis" style="grid-template-columns:repeat(4,1fr);margin-bottom:12px">
          <div class="card kpi"><div class="lbl">{{ __('Pages') }}</div><b>{{ $review['pageCount'] }}</b></div>
          <div class="card kpi"><div class="lbl">{{ __('Panels') }}</div><b>{{ $review['stats']['panels'] }}</b></div>
          <div class="card kpi"><div class="lbl">{{ __('Comments') }}</div><b>{{ count($review['comments']) }}</b></div>
          <div class="card kpi"><div class="lbl">{{ __('Non-compliances') }}</div><b style="color:var(--fail)">{{ $review['stats']['fail'] }}</b></div>
        </div>
        <dl class="kv">
          <dt>{{ ($review['final'] ?? false) ? __('Action') : __('Suggested action') }}</dt><dd><b>{{ $review['decision'] }}</b></dd>
          <dt>{{ __('Engineer') }}</dt><dd>{{ $review['engineerName'] ?: '–' }}</dd>
          <dt>{{ __('Client details') }}</dt><dd>{{ $review['hidden'] ? __('Hidden in the PDF (client disclosure on)') : __('Visible') }}</dd>
          <dt>{{ __('Issued file') }}</dt><dd class="mono">{{ $review['pdfName'] }}</dd>
        </dl>
        <h3 style="margin-top:16px">{{ __('Comments') }}</h3>
        <ol style="margin:0;padding-inline-start:20px">@foreach ($review['comments'] as $c)<li style="margin-bottom:6px">{{ $c['text'] }}</li>@endforeach</ol>
      @elseif (! $s->hasOriginal())
        <p class="mute">{{ $s->status === 'uploading' ? __('The original PDF is not on the server — the upload was not completed.') : __('The original PDF is not on the server (it may have been removed by a host restart).') }}</p>
      @else
        <p class="mute">{{ __('Not checked yet.') }}@if ($canEdit) <a href="{{ route('v2.admin.submissions.workspace', [$s, 'start' => 1]) }}"><b>{{ __('Start the check now') }}</b></a> {{ __('— it reads every page, applies the criteria of :category and drafts the comment sheet.', ['category' => $s->category ? (app()->getLocale() === 'ar' ? ($s->category->name_ar ?: $s->category->name_en) : $s->category->name_en) : __('the default template')]) }}@endif</p>
      @endif
    </div>

    <div class="card pad" id="send">
      <h2>{{ __('Send to the client') }}</h2>
      @if (($review['final'] ?? false) && $s->outputPath())
        <form class="form" method="post" action="{{ route('v2.admin.submissions.email', $s) }}">
          @csrf
          <div class="row2"><label class="f">{{ __('To') }}<input class="inp" type="email" name="to" value="{{ $s->client_email }}"></label><label class="f">{{ __('File') }}<input class="inp" value="{{ basename($s->outputPath()) }} · {{ number_format(filesize($s->outputPath()) / 1048576, 1) }} MB" disabled></label></div>
          <label class="f">{{ __('Letter subject') }}<input class="inp" name="letter_subject" value="{{ old('letter_subject', $letter['subject']) }}" dir="{{ $s->locale === 'ar' ? 'rtl' : 'ltr' }}"></label>
          <label class="f">{{ __('Official letter') }} <small>({{ __(':language — the client\'s language; signed with :signer', ['language' => $s->locale === 'ar' ? __('Arabic') : __('English'), 'signer' => ($s->assignee ?? auth()->user())->name . (($s->assignee ?? auth()->user())->title ? ', ' . ($s->assignee ?? auth()->user())->title : '')]) }})</small>
            <textarea class="inp" name="letter_body" dir="{{ $s->locale === 'ar' ? 'rtl' : 'ltr' }}" style="min-height:260px;line-height:1.7">{{ old('letter_body', $letter['body']) }}</textarea></label>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button class="btn primary"><x-v2.icon name="send"/>{{ __('Email the letter + reviewed PDF') }}</button>
          <span class="mute" style="font-size:12.5px">{{ __('PDF attached up to :mb MB, otherwise a link to the tracking page.', ['mb' => config('v2.mail_attach_mb')]) }} @if ($s->emailed_at){{ __('Last sent :when.', ['when' => $s->emailed_at->diffForHumans()]) }}@endif</span></div>
        </form>
      @else
        <p class="mute" style="margin:0">{{ __('Available once the review is approved: open the analysis, check the comments and press') }} <b>{{ __('Approve & generate') }}</b>.</p>
      @endif
    </div>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card pad">
      <h2>{{ __('Submittal') }}</h2>
      <dl class="kv">
        <dt>{{ __('Code') }}</dt><dd class="mono">{{ $s->code }}</dd>
        <dt>{{ __('Received') }}</dt><dd>{{ $s->created_at->format('d M Y, H:i') }}</dd>
        <dt>{{ __('Project') }}</dt><dd>{{ $s->project_name }}</dd>
        <dt>{{ __('Title') }}</dt><dd>{{ $s->title ?: '–' }}</dd>
        <dt>{{ __('Submittal no.') }}</dt><dd>{{ $s->submittal_no ?: '–' }}</dd>
        <dt>{{ __('Category') }}</dt><dd>{{ $s->category ? (app()->getLocale() === 'ar' ? ($s->category->name_ar ?: $s->category->name_en) : $s->category->name_en) : $s->discipline }}</dd>
        <dt>{{ __('File') }}</dt><dd>@if ($s->hasOriginal())<a href="{{ route('v2.admin.submissions.original', $s) }}">{{ $s->file_name }}</a>@else{{ $s->file_name ?: '–' }}@endif <span class="mute">· {{ $s->sizeLabel() }}{{ $s->page_count ? ' · ' . __(':n pages', ['n' => $s->page_count]) : '' }}</span></dd>
      </dl>
      @if ($s->notes)<h3 style="margin-top:14px">{{ __('Notes from the client') }}</h3><p style="white-space:pre-line;margin:0">{{ $s->notes }}</p>@endif
    </div>
    <div class="card pad">
      <h2>{{ __('Engineer') }}</h2>
      <p style="margin:0 0 10px">{!! $s->assignee ? '<b>' . e($s->assignee->name) . '</b>' . ($s->assignee->title ? ' <span class="mute">· ' . e($s->assignee->title) . '</span>' : '') : '<span style="color:var(--fail)">' . e(__('Not assigned')) . '</span>' !!}</p>
      @if ($s->category)
        <p class="mute" style="margin:0 0 10px;font-size:13px">{{ __('Category:') }} <b>{{ (app()->getLocale() === 'ar' ? ($s->category->name_ar ?: $s->category->name_en) : $s->category->name_en) }}</b>@if ($s->category->specPath()) · <a href="{{ route('v2.admin.categories.spec', $s->category) }}" target="_blank">{{ __('specification') }} ↗</a>@endif · {{ __(':n criteria', ['n' => count(array_filter($s->category->rules ?? [], fn ($r) => $r['active'] ?? true))]) }}</p>
      @endif
      @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('v2.admin.submissions.assign', $s) }}" style="display:flex;gap:8px">@csrf
          <select class="inp" name="user"><option value="">{{ __('— nobody —') }}</option>@foreach ($engineers as $e)<option value="{{ $e->id }}" @selected($s->assigned_to === $e->id)>{{ $e->name }}{{ $s->category && $s->category->engineers->contains($e) ? ' ★' : '' }}</option>@endforeach</select>
          <button class="btn">{{ __('Assign') }}</button>
        </form>
        <p class="mute" style="font-size:12px;margin:6px 0 0">★ {{ __('responsible for this category') }}</p>
      @elseif (! $s->assigned_to && $canEdit)
        <form method="post" action="{{ route('v2.admin.submissions.assign', $s) }}">@csrf<input type="hidden" name="user" value="{{ auth()->id() }}"><button class="btn primary">{{ __('Take this request') }}</button></form>
      @elseif ($s->assigned_to === auth()->id())
        <form method="post" action="{{ route('v2.admin.submissions.assign', $s) }}">@csrf<button class="btn">{{ __('Hand it back') }}</button></form>
      @endif
      @if ($canEdit && $s->hasOriginal() && $review)
        <form method="post" action="{{ route('v2.admin.submissions.restart', $s) }}" style="margin-top:12px" onsubmit="return confirm(@js(__('Re-run the check with the category\'s current criteria? Your edits to the comments will be lost.')))">@csrf<button class="btn line"><x-v2.icon name="chart"/>{{ __('Re-run with the current criteria') }}</button></form>
      @endif
    </div>
    <div class="card pad">
      <h2>{{ __('Client') }}</h2>
      <dl class="kv">
        <dt>{{ __('Name') }}</dt><dd>{{ $s->client_name }}</dd>
        <dt>{{ __('Company') }}</dt><dd>{{ $s->client_company ?: '–' }}</dd>
        <dt>{{ __('Email') }}</dt><dd><a href="mailto:{{ $s->client_email }}">{{ $s->client_email }}</a></dd>
        <dt>{{ __('Phone') }}</dt><dd>{{ $s->client_phone ?: '–' }}</dd>
        <dt>{{ __('Language') }}</dt><dd>{{ $s->locale === 'ar' ? __('Arabic') : __('English') }}</dd>
        <dt>{{ __('Tracking page') }}</dt><dd><a href="{{ route('v2.track.show', $s->code) }}" target="_blank">{{ __('open') }} ↗</a></dd>
      </dl>
    </div>
    <div class="card pad">
      <h2>{{ __('Status') }}</h2>
      <form method="post" action="{{ route('v2.admin.submissions.update', $s) }}" style="display:flex;gap:8px">@csrf @method('patch')
        <select class="inp" name="status">@foreach (\App\Models\V2\Submission::STATUSES as $st)<option value="{{ $st }}" @selected($s->status === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select>
        <button class="btn">{{ __('Save') }}</button>
      </form>
    </div>
    <div class="card pad">
      <h2>{{ __('History') }}</h2>
      <ul class="feed">@forelse ($activity as $a)<li><span>{{ __(str_replace(['.', '_'], ' ', $a->action)) }}@if ($a->detail)<span class="mute"> — {{ $a->detail }}</span>@endif @if ($a->user)<span class="mute"> · {{ $a->user->name }}</span>@endif</span><span class="when">{{ $a->created_at->format('d M H:i') }}</span></li>@empty<li class="mute">{{ __('No history.') }}</li>@endforelse</ul>
    </div>
    @if (auth()->user()->isAdmin())<form method="post" action="{{ route('v2.admin.submissions.destroy', $s) }}" onsubmit="return confirm(@js(__('Delete this submission and all its files? This cannot be undone.')))">@csrf @method('delete')<button class="btn danger"><x-v2.icon name="trash"/>{{ __('Delete submission') }}</button></form>@endif
  </div>
</div>
@endsection
