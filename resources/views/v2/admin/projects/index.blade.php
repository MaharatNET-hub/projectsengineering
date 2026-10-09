@extends('v2.layouts.admin', ['title' => __('Projects')])
@section('actions')<a class="btn" href="{{ route('v2.projects') }}" target="_blank"><x-v2.icon name="eye"/>{{ __('View on site') }}</a><a class="btn primary" href="{{ route('v2.admin.projects.create') }}"><x-v2.icon name="plus"/>{{ __('Add project') }}</a>@endsection
@section('content')
<div class="card"><div class="tbl"><table class="t"><tr><th></th><th>{{ __('Project') }}</th><th>{{ __('Sector') }}</th><th>{{ __('Location') }}</th><th class="num">{{ __('Year') }}</th><th>{{ __('On site') }}</th><th></th></tr>
@forelse ($items as $p)
  <tr>
    <td>@if ($p->imageUrl())<img class="thumb" src="{{ $p->imageUrl() }}" alt="">@else<span class="thumb" style="--c: {{ $p->accent }}"></span>@endif</td>
    <td><b>{{ $p->title_en }}</b><div class="mute" dir="rtl" style="text-align:start;font-size:13px">{{ $p->title_ar }}</div></td>
    <td>{{ $p->category ?: '–' }}</td><td>{{ $p->location_en }}</td><td class="num">{{ $p->year }}</td>
    <td>@if ($p->active)<span class="chip ok">{{ __('Shown') }}</span>@else<span class="chip mute">{{ __('Hidden') }}</span>@endif @if ($p->featured)<span class="chip accent">{{ __('Featured') }}</span>@endif</td>
    <td style="text-align:end;white-space:nowrap"><a class="btn sm" href="{{ route('v2.admin.projects.edit', $p) }}"><x-v2.icon name="edit"/>{{ __('Edit') }}</a>
      <form method="post" action="{{ route('v2.admin.projects.destroy', $p) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete this project?')))">@csrf @method('delete')<button class="btn sm danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-v2.icon name="trash"/></button></form></td>
  </tr>
@empty<tr><td colspan="7" class="empty">{{ __('No projects yet.') }}</td></tr>@endforelse
</table></div></div>
@endsection
