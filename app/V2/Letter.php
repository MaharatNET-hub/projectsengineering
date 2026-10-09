<?php

namespace App\V2;

use App\Models\User;
use App\Models\V2\Submission;

/**
 * The official letter that goes with an issued review, in the client's language. The engineer gets a
 * prefilled draft to edit; the email shows it on letterhead with their signature block.
 */
final class Letter
{
    /** The engine's decisions, as written in an Arabic letter. */
    public const AR = [
        'Approved' => 'معتمد', 'Approved as noted' => 'معتمد مع ملاحظات', 'Revise / Resubmit' => 'يُعدّل ويُعاد تقديمه', 'Rejected' => 'مرفوض',
        'Approved as noted / Resubmit' => 'معتمد مع ملاحظات / يُعاد تقديمه', 'No action required / for information only' => 'لا يلزم إجراء / للعلم فقط',
    ];

    /** @return array{subject:string,body:string} */
    public static function draft(Submission $s, ?User $engineer = null): array
    {
        $engineer ??= $s->assignee ?? auth()->user();
        $ar = $s->locale === 'ar';
        $ref = $s->submittal_no ?: $s->code;
        $what = $s->title ?: $s->project_name;
        $decision = $s->decision ?: '—';
        $shown = $ar ? (self::AR[$decision] ?? $decision) : $decision;
        $n = (int) $s->comment_count;
        $to = trim($s->client_name . ($s->client_company ? ' — ' . $s->client_company : ''));
        if ($ar) {
            return [
                'subject' => "مراجعة التقديم رقم $ref — $what",
                'body' => "السادة / $to المحترمين،\n\nتحية طيبة وبعد،\n\nبالإشارة إلى تقديمكم رقم ($ref) الخاص بـ \"$what\" لمشروع \"{$s->project_name}\"، نفيدكم بأنه تمت مراجعته وفق مواصفات المشروع"
                    . ($s->category ? ' الخاصة بتصنيف «' . $s->category->name_ar . '»' : '') . ".\n\n"
                    . "القرار: $shown\n" . ($n ? "عدد الملاحظات: $n — مفصّلة في ورقة الملاحظات المرفقة ومعلَّمة على المخططات.\n" : '')
                    . "\nنرجو التكرم بمراجعة الملاحظات المرفقة" . (preg_match('/Revise|Reject|Resubmit/i', $decision) ? ' والرد عليها وإعادة التقديم بالمراجعة التالية' : '') . ".\n\n"
                    . "وتفضلوا بقبول فائق الاحترام والتقدير،",
            ];
        }

        return [
            'subject' => "Review of submittal $ref — $what",
            'body' => "Dear $to,\n\nWith reference to your submittal no. $ref, \"$what\", for the project \"{$s->project_name}\", please note that it has been reviewed against the project specification"
                . ($s->category ? ' for ' . $s->category->name_en : '') . ".\n\n"
                . "Action: $decision\n" . ($n ? "Comments: $n — detailed in the attached comment sheet and marked on the drawings.\n" : '')
                . "\nPlease review the attached comments" . (preg_match('/Revise|Reject|Resubmit/i', $decision) ? ', respond to each of them and resubmit as the next revision' : '') . ".\n\n"
                . "Yours faithfully,",
        ];
    }

    /** Signature block under the letter. */
    public static function signature(?User $engineer, string $locale): array
    {
        $c = Site::contact();
        $co = Site::company();

        return array_values(array_filter([
            $engineer?->name,
            $engineer?->title,
            ($co['name_' . ($locale === 'ar' ? 'ar' : 'en')] ?? '') ?: Site::name(),
            trim(implode(' · ', array_filter([$c['phone'] ?? null, $engineer?->email ?? ($c['email'] ?? null)]))),
        ]));
    }
}
