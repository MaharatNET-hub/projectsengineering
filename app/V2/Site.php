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

    public static function stats(): array
    {
        return Setting::get('stats', []);
    }

    public static function review(): array
    {
        return Setting::get('review', []) + ['hide_default' => true, 'notify_email' => '', 'auto_analyse' => false];
    }

    /**
     * The home-page banner: the slides uploaded from the dashboard, or the built-in artwork.
     * Each slide: image url, eyebrow, title, text (already in the current language).
     */
    public static function slides(): array
    {
        $copy = __('site.slides');
        $out = [];
        foreach (Setting::get('slides', []) as $i => $s) {
            if (empty($s['image']) || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($s['image'])) {
                continue;
            }
            $out[] = [
                'image' => route('v2.media', ['path' => $s['image']]),
                'eyebrow' => self::t($s, 'eyebrow') ?: ($copy[$i % count($copy)]['eyebrow'] ?? ''),
                'title' => self::t($s, 'title') ?: ($i === 0 ? self::name() : ''),
                'text' => self::t($s, 'text') ?: ($i === 0 ? self::t(self::company(), 'tagline') : ''),
            ];
        }
        if ($out) {
            return $out;
        }
        foreach (['skyline', 'blueprint', 'power'] as $i => $art) {
            $c = $copy[$i] ?? [];
            $out[] = [
                'image' => asset("lib/v2/banner/$art.svg"),
                'eyebrow' => $c['eyebrow'] ?? '',
                'title' => $c['title'] ?? self::name(),
                'text' => $c['text'] ?? self::t(self::company(), 'tagline'),
            ];
        }

        return $out;
    }

    /** Same page in the other language. */
    public static function switchUrl(): string
    {
        return request()->fullUrlWithQuery(['lang' => self::ar() ? 'en' : 'ar']);
    }
}
