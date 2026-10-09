@php use App\Studies\StudyTypes as T; $L = app()->getLocale(); @endphp
{{-- what changed since the previous revision: $diff from App\Studies\Revisions::diff() --}}
<div class="diff">
  <div class="diff-k">
    <span class="st pass">{{ count($diff['resolved']) }} · {{ __('studies.rev.resolved') }}</span>
    <span class="st fail">{{ count($diff['open']) }} · {{ __('studies.rev.open') }}</span>
    <span class="st warn">{{ count($diff['new']) }} · {{ __('studies.rev.new') }}</span>
  </div>
  @if ($diff['changes'] || $diff['added'] || $diff['removed'])
    <div class="tbl-wrap tbl"><table class="vals t">
      <tr><th>{{ __('studies.rev.item') }}</th><th>{{ $diff['from'] }}</th><th>{{ $diff['to'] }}</th></tr>
      @foreach ($diff['changes'] as $c)
        <tr><td>@if ($c['row'] !== '')<b>{{ $c['row'] }}</b> · @endif{{ T::t($c['label']) }}</td><td style="text-decoration:line-through;color:#94a3b8">{{ T::display($c['old'][0], $c['old'][1]) }}</td><td><b>{{ T::display($c['new'][0], $c['new'][1]) }}</b></td></tr>
      @endforeach
      @foreach ($diff['added'] as $a)<tr><td><b>{{ $a['row'] }}</b> · {{ T::t($a['section']) }}</td><td>–</td><td><b>{{ __('studies.rev.added') }}</b></td></tr>@endforeach
      @foreach ($diff['removed'] as $a)<tr><td><b>{{ $a['row'] }}</b> · {{ T::t($a['section']) }}</td><td>{{ $a['row'] }}</td><td><b>{{ __('studies.rev.removed') }}</b></td></tr>@endforeach
    </table></div>
  @else
    <p class="mute" style="margin:8px 0 0">{{ __('studies.rev.no_changes') }}</p>
  @endif
  @if ($diff['resolved'])
    <p style="margin:12px 0 4px;font-weight:600">{{ __('studies.rev.resolved') }}:</p>
    <ul style="margin:0;padding-inline-start:20px">@foreach ($diff['resolved'] as $f)<li>{{ T::t($f['label']) }}@if ($f['row'] !== '') — {{ $f['row'] }}@endif</li>@endforeach</ul>
  @endif
</div>
