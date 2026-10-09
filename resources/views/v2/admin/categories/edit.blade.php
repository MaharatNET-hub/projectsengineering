@php
  use App\Models\V2\Category;
  use App\Studies\StudyTypes as T;
  $new = ! $c->exists;
  $rules = $c->rules ?? [];
  $mine = $new ? [] : $c->engineers->pluck('id')->all();
@endphp
@extends('v2.layouts.admin', ['title' => $new ? __('New category') : (app()->getLocale() === 'ar' ? ($c->name_ar ?: $c->name_en) : $c->name_en)])
@section('crumb')<div class="mute" style="font-size:12.5px"><a href="{{ route('v2.admin.categories') }}">{{ __('Categories') }}</a></div>@endsection
@section('content')
<div class="grid g21">
  <form class="card pad form" method="post" action="{{ $new ? route('v2.admin.categories.store') : route('v2.admin.categories.update', $c) }}">
    @csrf @unless ($new) @method('put') @endunless
    <h2 style="margin:0">{{ __('Category') }}</h2>
    <div class="row2">
      <label class="f">{{ __('Name (English)') }}<input class="inp" name="name_en" value="{{ old('name_en', $c->name_en) }}" required></label>
      <label class="f">{{ __('Name (Arabic)') }}<input class="inp" name="name_ar" value="{{ old('name_ar', $c->name_ar) }}" required dir="rtl"></label>
    </div>
    <div class="row2">
      <label class="f">{{ __('Discipline') }}<select class="inp" name="discipline">@foreach (['Electrical', 'Mechanical', 'Plumbing', 'Fire', 'Other'] as $d)<option value="{{ $d }}" @selected(old('discipline', $c->discipline) === $d)>{{ __($d) }}</option>@endforeach</select></label>
      <label class="f">{{ __('Order on the form') }}<input class="inp" type="number" name="sort" min="0" value="{{ old('sort', $c->sort) }}"></label>
    </div>
    <div class="row2">
      <label class="f">{{ __('What the client submits here (English)') }}<textarea class="inp" name="description_en">{{ old('description_en', $c->description_en) }}</textarea></label>
      <label class="f">{{ __('What the client submits here (Arabic)') }}<textarea class="inp" name="description_ar" dir="rtl">{{ old('description_ar', $c->description_ar) }}</textarea></label>
    </div>
    <label class="f">{{ __('Specification title') }} <small>{{ __('(printed on the review: "checked against …")') }}</small><input class="inp" name="spec_title" value="{{ old('spec_title', $c->spec_title) }}" placeholder="Volume 3 – PART F Electrical (IFC Dec 2024)"></label>
    <div>
      <div class="f" style="margin-bottom:6px"><b>{{ __('Responsible engineers') }}</b> <small class="mute">{{ __('— new requests go to the one with the fewest open requests') }}</small></div>
      <div style="display:flex;flex-wrap:wrap;gap:8px 18px">
        @foreach ($engineers as $e)<label class="check"><input type="checkbox" name="engineers[]" value="{{ $e->id }}" @checked(in_array($e->id, old('engineers', $mine)))> {{ $e->name }} <span class="mute" style="font-size:12px">{{ $e->title ?: $e->role }}</span></label>@endforeach
      </div>
      @if ($engineers->count() < 2)<p class="mute" style="font-size:12.5px;margin:6px 0 0">{{ __('Add engineers under') }} <a href="{{ route('v2.admin.users') }}">{{ __('Users') }}</a>.</p>@endif
    </div>
    <div>
      <div class="f" style="margin-bottom:6px"><b>{{ __('Form-based studies routed here') }}</b></div>
      <div style="display:flex;flex-wrap:wrap;gap:8px 18px">@foreach ($types as $k => $d)<label class="check"><input type="checkbox" name="study_types[]" value="{{ $k }}" @checked(in_array($k, old('study_types', $c->study_types ?? [])))> {{ T::t($d['name']) }}</label>@endforeach</div>
    </div>
    @if ($new && $copyFrom->isNotEmpty())
      <label class="f">{{ __('Start the criteria from') }}<select class="inp" name="copy_from"><option value="">{{ __('No criteria (manual review until you add some)') }}</option>@foreach ($copyFrom as $o)<option value="{{ $o->id }}">{{ __('A copy of:') }} {{ (app()->getLocale() === 'ar' ? ($o->name_ar ?: $o->name_en) : $o->name_en) }} ({{ __(':n criteria', ['n' => count($o->rules ?? [])]) }})</option>@endforeach</select></label>
    @endif
    <label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $c->active))> {{ __('Shown on the submission form') }}</label>
    <div><button class="btn primary"><x-v2.icon name="check"/>{{ $new ? __('Create category') : __('Save') }}</button></div>
  </form>

  <div class="grid" style="align-content:start">
    @unless ($new)
      <div class="card pad">
        <h2>{{ __('Specification (base file)') }}</h2>
        @if ($c->spec_file)
          <p style="margin:0 0 10px"><a href="{{ route('v2.admin.categories.spec', $c) }}" target="_blank"><x-v2.icon name="file"/> {{ $c->spec_file }}</a> <span class="mute">· {{ number_format($c->spec_size / 1048576, 1) }} MB</span></p>
        @else
          <p class="mute" style="margin:0 0 10px">{{ __('No specification uploaded yet. The engineers open it from the check to read the clause behind each comment.') }}</p>
        @endif
        <form method="post" action="{{ route('v2.admin.categories.spec.upload', $c) }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
          <input type="file" name="spec" accept="application/pdf,.pdf" required class="inp" style="max-width:260px">
          <button class="btn"><x-v2.icon name="upload"/>{{ $c->spec_file ? __('Replace') : __('Upload') }}</button>
        </form>
        @if ($c->spec_file)<form method="post" action="{{ route('v2.admin.categories.spec.delete', $c) }}" style="margin-top:8px" onsubmit="return confirm(@js(__('Remove the specification file?')))">@csrf @method('delete')<button class="btn danger" style="padding:6px 10px">{{ __('Remove') }}</button></form>@endif
        <p class="mute" style="font-size:12.5px;margin:10px 0 0">{{ __('PDF up to :mb MB (the server\'s upload limit applies).', ['mb' => min(64, (int) ini_get('upload_max_filesize') ?: 64)]) }}</p>
      </div>
      <div class="card pad">
        <h2>{{ __('Danger zone') }}</h2>
        <form method="post" action="{{ route('v2.admin.categories.destroy', $c) }}" onsubmit="return confirm(@js(__('Delete this category? Its submittals stay, without a category.')))">@csrf @method('delete')<button class="btn danger"><x-v2.icon name="trash"/>{{ __('Delete category') }}</button></form>
      </div>
    @endunless
  </div>
</div>

@unless ($new)
<form class="card pad form" method="post" action="{{ route('v2.admin.categories.rules', $c) }}" style="margin-top:16px" id="crit">
  @csrf @method('put')
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">{{ __('Criteria — what the check verifies') }}</h2><button class="btn primary"><x-v2.icon name="check"/>{{ __('Save criteria') }}</button></div>
  <p class="mute" style="margin:0;font-size:13px">{{ __('The check reads the values below from the submitted data sheets and drawings (the reader was built for LV switchgear panel data sheets). Each criterion cites the specification clause — give its page so the engineer can open it. A category without criteria is reviewed by hand in the same screen: the engineer adds the comments and issues the same PDF.') }}</p>
  <div id="rules" style="display:grid;gap:10px">
    @foreach (array_merge($rules, [[]]) as $i => $r)
      @php $tpl = $r === []; @endphp
      @if ($tpl)<template id="ruleTpl">@endif
      <div class="te-rule" data-rule>
        <div class="te-row">
          <input class="inp mono" name="rules[{{ $tpl ? '__I__' : $i }}][id]" value="{{ $r['id'] ?? '' }}" placeholder="R1" style="max-width:70px">
          <label class="check"><input type="checkbox" name="rules[{{ $tpl ? '__I__' : $i }}][active]" value="1" @checked($r['active'] ?? true)> {{ __('on') }}</label>
          <input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][label]" value="{{ $r['label'] ?? '' }}" placeholder="{{ __('Check name, e.g. Degree of protection') }}">
          <select class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][attribute]" data-attr style="max-width:300px">@foreach (Category::ATTRIBUTES as $k => [$l]) <option value="{{ $k }}" @selected(($r['attribute'] ?? 'ip') === $k)>{{ __($l) }}</option>@endforeach</select>
          <button type="button" class="btn danger te-mini" data-del>✕</button>
        </div>
        <div class="te-inline">
          <label class="f">{{ __('Panels') }} <small>{{ __('(EMDB, SMDB, DB… or *)') }}</small><input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][appliesTo]" value="{{ implode(', ', $r['appliesTo'] ?? ['*']) }}" style="max-width:170px"></label>
          <label class="f" data-show="ip auxWire">{{ __('Minimum') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][expected]" value="{{ is_numeric($r['expected'] ?? null) ? $r['expected'] : '' }}" style="max-width:90px" placeholder="54"></label>
          <label class="f" data-show="form">{{ __('Form') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][form]" value="{{ $r['expected']['form'] ?? '' }}" style="max-width:70px" placeholder="4"></label>
          <label class="f" data-show="form">{{ __('Type') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][type]" value="{{ $r['expected']['type'] ?? '' }}" style="max-width:70px" placeholder="6"></label>
          <label class="f">{{ __('Section') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][section]" value="{{ $r['clause']['section'] ?? '' }}" style="max-width:100px" placeholder="262300"></label>
          <label class="f">{{ __('Clause') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][path]" value="{{ $r['clause']['path'] ?? '' }}" style="max-width:100px" placeholder="2.6.B"></label>
          <label class="f">{{ __('Spec page') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][specPage]" value="{{ $r['clause']['specPage'] ?? '' }}" style="max-width:80px" inputmode="numeric"></label>
        </div>
        <label class="f">{{ __('Clause text (quoted on the comment sheet)') }}<input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][text]" value="{{ $r['clause']['text'] ?? '' }}"></label>
        <label class="f">{{ __('Comment') }} <small>{{ __('— {actual} {section} {path} {panels} are filled in') }}</small><input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][comment]" value="{{ $r['comment'] ?? '' }}"></label>
        <div class="row2">
          <label class="f">{{ __('Mark on the drawing') }} <small>{{ __('(optional)') }}</small><input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][markup]" value="{{ $r['markup'] ?? '' }}"></label>
          <label class="f">{{ __('Note for the engineer') }} <small>{{ __('(optional)') }}</small><input class="inp" name="rules[{{ $tpl ? '__I__' : $i }}][note]" value="{{ $r['note'] ?? '' }}"></label>
        </div>
      </div>
      @if ($tpl)</template>@endif
    @endforeach
  </div>
  <div style="display:flex;gap:10px"><button type="button" class="btn line" id="addRule"><x-v2.icon name="plus"/>{{ __('Add a criterion') }}</button><button class="btn primary"><x-v2.icon name="check"/>{{ __('Save criteria') }}</button></div>
</form>
@endunless
@endsection
@push('scripts')
<script>
(() => {
  const box = document.getElementById('rules'); if (!box) return;
  let n = box.querySelectorAll('[data-rule]').length + 100;
  const wire = el => {
    const sel = el.querySelector('[data-attr]');
    const sync = () => el.querySelectorAll('[data-show]').forEach(f => f.hidden = !f.dataset.show.split(' ').includes(sel.value));
    sel.addEventListener('change', sync); sync();
    el.querySelector('[data-del]').addEventListener('click', () => { if (confirm(@js(__('Remove this criterion?')))) el.remove(); });
  };
  box.querySelectorAll('[data-rule]').forEach(wire);
  document.getElementById('addRule').addEventListener('click', () => {
    const t = document.getElementById('ruleTpl');
    const html = t.innerHTML.replaceAll('__I__', String(n++));
    const tmp = document.createElement('div'); tmp.innerHTML = html;
    const el = tmp.firstElementChild; box.appendChild(el); wire(el); el.querySelector('input').focus();
  });
})();
</script>
@endpush
