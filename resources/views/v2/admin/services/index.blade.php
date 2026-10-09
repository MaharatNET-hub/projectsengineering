@extends('v2.layouts.admin', ['title' => __('Services')])
@section('actions')<a class="btn" href="{{ route('v2.services') }}" target="_blank"><x-v2.icon name="eye"/>{{ __('View on site') }}</a><a class="btn primary" href="{{ route('v2.admin.services.create') }}"><x-v2.icon name="plus"/>{{ __('Add service') }}</a>@endsection
@section('content')
<div class="card"><div class="tbl"><table class="t"><tr><th></th><th>{{ __('Service') }}</th><th>{{ __('Summary') }}</th><th class="num">{{ __('Order') }}</th><th>{{ __('On site') }}</th><th></th></tr>
@forelse ($items as $s)
  <tr><td><span style="color:var(--accent)"><x-v2.icon :name="$s->icon"/></span></td>
    <td><b>{{ $s->title_en }}</b><div class="mute" dir="rtl" style="text-align:start;font-size:13px">{{ $s->title_ar }}</div></td>
    <td class="mute" style="max-width:420px">{{ \Illuminate\Support\Str::limit($s->summary_en, 110) }}</td><td class="num">{{ $s->sort }}</td>
    <td>@if ($s->active)<span class="chip ok">{{ __('Shown') }}</span>@else<span class="chip mute">{{ __('Hidden') }}</span>@endif</td>
    <td style="text-align:end;white-space:nowrap"><a class="btn sm" href="{{ route('v2.admin.services.edit', $s) }}"><x-v2.icon name="edit"/>{{ __('Edit') }}</a>
      <form method="post" action="{{ route('v2.admin.services.destroy', $s) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete this service?')))">@csrf @method('delete')<button class="btn sm danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-v2.icon name="trash"/></button></form></td></tr>
@empty<tr><td colspan="6" class="empty">{{ __('No services yet.') }}</td></tr>@endforelse
</table></div></div>
@endsection
