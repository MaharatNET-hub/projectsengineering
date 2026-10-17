{{-- The analysis tool (comment sheet, panels, marked-up drawings, criteria) inside a dashboard page, for submission $sub. --}}
@push('head')<link rel="stylesheet" href="{{ asset('lib/v2/ws-embed.css') }}?v={{ filemtime(public_path('lib/v2/ws-embed.css')) }}">@endpush
<section class="card pad ws" id="ws" dir="ltr">
  <div class="ws-bar">
    <h2>{{ $title ?? __('File check against the category criteria') }} <span class="mute" style="font-weight:500;font-size:13px">· {{ $sub->category?->t('name') ?? __('default criteria') }}</span></h2>
    <div style="display:flex;gap:8px;align-items:center">
      @if ($sub->review() && \App\Http\Controllers\V2\Admin\SubmissionController::canEdit($sub))
        <form method="post" action="{{ route('v2.admin.submissions.restart', $sub) }}" onsubmit="return confirm('{{ __('Read the whole file again and draft the comments again? Your edits and boxes on this check are replaced.') }}')">@csrf<button class="btn" style="padding:6px 10px;font-size:13px">{{ __('Re-read the file') }}</button></form>
      @endif
      <div class="user"></div>
    </div>
  </div>
  <div id="view"><div class="mute" style="padding:30px;text-align:center">{{ __('Loading the check…') }}</div></div>
  <div id="toast" class="toast" hidden></div>
</section>
<script>
window.APP = {
  base: @json(url('v2/admin/submissions/' . $sub->id)),
  chunk: 1048576,
  csrf: @json(csrf_token()),
  back: '#send',
  // a study's page: the final download is the whole file (report + comment sheet + marked-up drawings)
  fullPdf: @json($fullPdf ?? null),
  code: @json($code ?? $sub->code),
  category: @json($sub->category?->name_en),
  engineer: @json(($sub->assignee ?? auth()->user())->name),
  spec: @json($sub->category?->specPath() ? route('v2.admin.categories.spec', $sub->category) : null),
  specTitle: @json($sub->category?->spec_title ?: $sub->category?->spec_file),
  sub: { file: @json($sub->file_name ?? 'submittal.pdf'), size: @json($sub->sizeLabel() . ($sub->page_count ? ' · ' . $sub->page_count . ' pages' : '')) },
};
</script>
<script type="module" src="{{ asset('lib/v2/workspace.js') }}?v={{ filemtime(public_path('lib/v2/workspace.js')) }}"></script>
