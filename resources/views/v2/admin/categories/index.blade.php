@extends('v2.layouts.admin', ['title' => __('Categories'), 'icon' => 'layers'])
@section('actions')<a class="btn primary" href="{{ route('v2.admin.categories.create') }}"><x-v2.icon name="plus"/>{{ __('New category') }}</a>@endsection
@section('content')
<p class="mute" style="margin-top:0">{{ __('Each category has its responsible engineers, its specification document and its criteria. The study types linked to it send their files here: the request goes to the category\'s engineer with the fewest open requests, and the file is checked against the category\'s criteria.') }}</p>
@if ($unlinked)<div class="alert bad">{{ __('Study types not linked to any category (their files are not checked against criteria and go to no engineer):') }} <b>{{ implode(' · ', $unlinked) }}</b>. {{ __('Open a category and tick them under "Study types routed here".') }}</div>@endif
<div class="card">
  @if ($categories->isEmpty())<div class="empty">{{ __('No categories yet.') }}</div>@else
  <div class="tbl"><table class="t">
    <tr><th>{{ __('Category') }}</th><th>{{ __('Study types') }}</th><th>{{ __('Engineers') }}</th><th>{{ __('Specification') }}</th><th class="num">{{ __('Criteria') }}</th><th class="num">{{ __('Open') }}</th><th class="num">{{ __('All') }}</th><th>{{ __('Status') }}</th></tr>
    @foreach ($categories as $c)
      <tr class="click" onclick="location.href='{{ route('v2.admin.categories.edit', $c) }}'">
        <td><b>{{ $c->name_en }}</b><div class="mute" style="font-size:12.5px" dir="rtl">{{ $c->name_ar }}</div></td>
        <td>@forelse ($c->study_types ?? [] as $k)<span class="chip accent" style="margin:2px">{{ isset($types[$k]) ? \App\Studies\StudyTypes::t($types[$k]['name']) : $k }}</span>@empty<span style="color:var(--fail)">{{ __('None linked') }}</span>@endforelse</td>
        <td>@forelse ($c->engineers as $e)<span class="chip mute" style="margin:2px">{{ $e->name }}</span>@empty<span style="color:var(--fail)">{{ __('No engineer') }}</span>@endforelse</td>
        <td>@if ($c->spec_file)<span class="mono" style="font-size:12.5px">{{ \Illuminate\Support\Str::limit($c->spec_file, 28) }}</span>@else<span class="mute">{{ __('not uploaded') }}</span>@endif<div class="mute" style="font-size:12px">{{ \Illuminate\Support\Str::limit($c->spec_title, 40) }}</div></td>
        <td class="num">{{ count(array_filter($c->rules ?? [], fn ($r) => $r['active'] ?? true)) }}</td>
        <td class="num">{{ $open[$c->id] ?? 0 }}</td><td class="num">{{ $c->submissions_count }}</td>
        <td><span class="chip {{ $c->active ? 'ok' : 'mute' }}"><span class="dot"></span>{{ $c->active ? __('Active') : __('Hidden') }}</span></td>
      </tr>
    @endforeach
  </table></div>
  @endif
</div>
@endsection
