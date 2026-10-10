<?php

namespace App\V2;

use App\Models\V2\Setting;

/** The company profile for v2 views, in the current language. */
final class Site
{
    public static function ar(): bool
    {
        return app()->getLocale() === 'ar';
    }

    /** Pick key_ar / key_en from an array (falls back to English). */
    public static function t(?array $a, string $key): string
    {
        $a ??= [];
        $v = $a[$key . '_' . (self::ar() ? 'ar' : 'en')] ?? '';

        return $v !== '' ? (string) $v : (string) ($a[$key . '_en'] ?? '');
    }

    public static function company(): array
    {
        return Setting::get('company', []);
    }

    public static function contact(): array
    {
        return Setting::get('contact', []);
    }

    public static function name(): string
    {
        return self::t(self::company(), 'name') ?: 'Engineering Consultants';
    }

    public static function initial(): string
    {
        return mb_strtoupper(mb_substr(self::t(self::company(), 'name') ?: 'E', 0, 1));
    }

    /**
     * Slides of the home banner: the admin's (Admin → Home banner) or the three built-in illustrations.
     *
     * @return list<array{img:string,mirror:bool,title:string,sub:string}>
     */
    public static function slides(): array
    {
        $saved = Setting::get('slides', []);
        $list = $saved ?: self::DEFAULT_SLIDES;
        $out = [];
        foreach ($list as $s) {
            $custom = ! empty($s['image']);
            $out[] = [
                'img' => $custom ? route('v2.media', $s['image']) : asset('lib/v2/banner/' . ($s['art'] ?? 'electrical') . '.svg'),
                'mirror' => ! $custom, // the drawings face the text; photos are never flipped
                'title' => self::t($s, 'title'), 'sub' => self::t($s, 'sub'),
            ];
        }

        return $out;
    }

    public const DEFAULT_SLIDES = [
        ['art' => 'electrical', 'title_en' => 'Electrical studies reviewed against your specification', 'title_ar' => 'مراجعة الدراسات الكهربائية وفق مواصفات مشروعك',
            'sub_en' => 'Switchgear, cable sizing and voltage drop — every value checked, every comment tied to its clause.', 'sub_ar' => 'لوحات التوزيع ومقاطع الكابلات وهبوط الجهد — كل قيمة تُفحص وكل ملاحظة مربوطة ببندها.'],
        ['art' => 'mechanical', 'title_en' => 'HVAC selections checked before they reach site', 'title_ar' => 'فحص اختيارات معدات التكييف قبل وصولها إلى الموقع',
            'sub_en' => 'Capacity, efficiency, refrigerant, sound and power — in one structured form.', 'sub_ar' => 'السعة والكفاءة وغاز التبريد والصوت والكهرباء — في نموذج واحد منظّم.'],
        ['art' => 'buildings', 'title_en' => 'From the first study to the issued review', 'title_ar' => 'من أول دراسة حتى إصدار المراجعة',
            'sub_en' => 'Instant automated check, then an engineer signs the final report.', 'sub_ar' => 'فحص آلي فوري، ثم يوقّع المهندس التقرير النهائي.'],
    ];

    public static function stats(): array
    {
        return Setting::get('stats', []);
    }

    public static function review(): array
    {
        return Setting::get('review', []) + ['hide_default' => true, 'notify_email' => '', 'auto_analyse' => false];
    }

    /** Same page in the other language. */
    public static function switchUrl(): string
    {
        return request()->fullUrlWithQuery(['lang' => self::ar() ? 'en' : 'ar']);
    }
}
