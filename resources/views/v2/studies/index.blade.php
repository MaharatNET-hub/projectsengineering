@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.site', ['title' => __('studies.index.title')])
@push('head')<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">@endpush
@section('content')
<section class="page-head"><div class="wrap"><h1>{{ __('studies.index.title') }}</h1><p>{{ __('studies.index.sub') }}</p></div></section>
<section class="block">
  <div class="wrap">
    <div class="types">
      @foreach ($types as $key => $def)
        @php $nFields = 0; foreach ($def['sections'] as $sec) { $nFields += count($sec['fields']); } @endphp
        <a class="card svc" href="{{ route('v2.studies.form', $key) }}">
          <div class="ic"><x-v2.icon :name="$def['icon'] ?? 'clipboard'"/></div>
          <div class="meta"><span>{{ __('v2.submit.disciplines.' . ($def['discipline'] ?? 'Other')) }}</span><span>{{ __('studies.index.fields', ['n' => $nFields]) }}</span><span>{{ __('studies.index.checks', ['n' => count($def['rules'] ?? [])]) }}</span></div>
          <h3>{{ T::t($def['name']) }}</h3>
          <p>{{ T::t($def['summary'] ?? '') }}</p>
          <span class="go">{{ __('studies.index.start') }} <x-v2.icon name="arrow"/></span>
        </a>
      @endforeach
    </div>
    <div class="card why">
      <h3 style="margin-top:0">{{ __('studies.index.why_title') }}</h3>
      <ul class="ticks" style="margin:0">@foreach (__('studies.index.why') as $pt)<li><x-v2.icon name="check"/><span>{{ $pt }}</span></li>@endforeach</ul>
    </div>
  </div>
</section>
@endsection
