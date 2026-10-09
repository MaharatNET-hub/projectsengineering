@extends('v2.mail.layout')
@section('body')
@if ($letter)
{{-- the engineer's official letter --}}
<p style="color:#64748b;font-size:13px;margin:0 0 4px">{{ now()->format('d/m/Y') }} · {{ $s->locale === 'ar' ? 'المرجع' : 'Ref.' }}: <span dir="ltr">{{ $s->submittal_no ?: $s->code }}</span></p>
<p style="font-weight:700;font-size:16px;margin:0 0 14px">{{ $letter['subject'] }}</p>
<div style="white-space:pre-line">{{ $letter['body'] }}</div>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0;border-{{ $s->locale === 'ar' ? 'right' : 'left' }}:3px solid #12306b;padding:0 12px"><tr><td style="padding:0 12px">
@foreach (\App\V2\Letter::signature($engineer, $s->locale) as $i => $line)<div style="{{ $i === 0 ? 'font-weight:700;font-size:15px' : 'color:#475569;font-size:13.5px' }}">{{ $line }}</div>@endforeach
</td></tr></table>
@elseif ($s->locale === 'ar')
<p>مرحباً {{ $s->client_name }}،</p>
<p>صدرت مراجعة تقديمك <b>{{ $s->title ?: $s->project_name }}</b>.</p>
<p>القرار: <b style="color:#b91c1c">{{ $s->decision }}</b> · عدد الملاحظات: <b>{{ $s->comment_count }}</b></p>
@else
<p>Hello {{ $s->client_name }},</p>
<p>The review of your submittal <b>{{ $s->title ?: $s->project_name }}</b> has been issued.</p>
<p>Action: <b style="color:#b91c1c">{{ $s->decision }}</b> · Comments: <b>{{ $s->comment_count }}</b></p>
@endif
@if ($note)<p style="background:#f8fafc;border-radius:8px;padding:12px 14px;white-space:pre-line">{{ $note }}</p>@endif
<p>{{ $attached ? ($s->locale === 'ar' ? 'الملف المراجَع مرفق بهذه الرسالة.' : 'The reviewed file is attached to this email.') : ($s->locale === 'ar' ? 'يمكنك تحميل الملف المراجَع من صفحة التتبّع:' : 'Download the reviewed file from the tracking page:') }}</p>
<p><a href="{{ route('v2.track.show', $s->code) }}" style="display:inline-block;background:#1f4fbf;color:#fff;text-decoration:none;padding:11px 18px;border-radius:9px;font-weight:600">{{ $s->locale === 'ar' ? 'فتح صفحة التتبّع' : 'Open the tracking page' }}</a></p>
<p style="color:#64748b;font-size:13px">{{ $s->locale === 'ar' ? 'رمز التتبّع' : 'Tracking code' }}: <span dir="ltr">{{ $s->code }}</span></p>
@endsection
