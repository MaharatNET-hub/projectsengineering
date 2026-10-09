@extends('v2.layouts.admin', ['title' => __('Categories')])
@section('actions')<a class="btn primary" href="{{ route('v2.admin.categories.create') }}"><x-v2.icon name="plus"/>{{ __('New category') }}</a>@endsection
@section('content')
<p class="mute" style="margin-top:0">{{ __('Each category (or position) has its responsible engineers, its specification document and its criteria. The client chooses the category when submitting a file; the request goes to the category\'s engineer with the fewest open requests, and the check runs against that category\'s criteria.') }}</p>
<div class="card">
  @if ($categories->isEmpty())<div class="empty">{{ __('No categories yet.') }}</div>@else
  <div class="tbl"><table class="t">
    <tr><th>{{ __('Category') }}</th><th>{{ __('Engineers') }}</th><th>{{ __('Specification') }}</th><th class="num">{{ __('Criteria') }}</th><th class="num">{{ __('Open') }}</th><th class="num">{{ __('All') }}</th><th>{{ __('Status') }}</th></tr>
    @foreach ($categories as $c)
      <tr class="click" onclick="location.href='{{ route('v2.admin.categories.edit', $c) }}'">
        <td><b>{{ $c->name_en }}</b><div class="mute" style="font-size:12.5px" dir="rtl">{{ $c->name_ar }}</div></td>
        <td>@forelse ($c->engineers as $e)<span class="chip mute" style="margin:2px">{{ $e->name }}</span>@empty<span style="color:var(--fail)">{{ __('No engineer') }}</span>@endforelse</td>
        <td>@if ($c->spec_file)<span class="mono" style="font-size:12.5px">{{ \Illuminate\Support\Str::limit($c->spec_file, 28) }}</span>@else<span class="mute">{{ __('not uploaded') }}</span>@endif<div class="mute" style="font-size:12px">{{ \Illuminate\Support\Str::limit($c->spec_title, 40) }}</div></td>
        <td class="num">{{ count(array_filter($c->rules ?? [], fn ($r) => $r['active'] ?? true)) }}</td>
        <td class="num">{{ $open[$c->id] ?? 0 }}</td><td class="num">{{ $c->submissions_count }}</td>
        <td><span class="chip {{ $c->active ? 'ok' : 'mute' }}"><span class="dot"></span>{{ $c->active ? __('On the form') : __('Hidden') }}</span></td>
      </tr>
    @endforeach
  </table></div>
  @endif
</div>
@endsection
