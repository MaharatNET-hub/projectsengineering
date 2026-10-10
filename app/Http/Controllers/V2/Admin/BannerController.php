<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\V2\Setting;
use App\V2\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The animated banner on the home page: up to six slides, each a photo (or a built-in drawing) with a title and a line. */
class BannerController extends Controller
{
    public const ARTS = ['electrical', 'mechanical', 'buildings'];

    public function index()
    {
        return view('v2.admin.banner', ['slides' => Setting::get('slides', []) ?: Site::DEFAULT_SLIDES]);
    }

    public function update(Request $r)
    {
        $r->validate([
            'slides' => 'array|max:6', 'slides.*.title_en' => 'nullable|string|max:120', 'slides.*.title_ar' => 'nullable|string|max:120',
            'slides.*.sub_en' => 'nullable|string|max:240', 'slides.*.sub_ar' => 'nullable|string|max:240', 'slides.*.art' => 'nullable|in:' . implode(',', self::ARTS),
            'slides.*.photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:6144',
        ]);
        $old = Setting::get('slides', []);
        $out = [];
        foreach ((array) $r->input('slides', []) as $i => $s) {
            $image = $s['image'] ?? null; // kept from before
            if (! empty($s['remove'])) {
                if ($image) {
                    Storage::disk('public')->delete($image);
                }

                continue;
            }
            if ($r->hasFile("slides.$i.photo")) {
                if ($image) {
                    Storage::disk('public')->delete($image);
                }
                $image = $r->file("slides.$i.photo")->store('v2/banner', 'public');
            } elseif (! empty($s['drop_photo']) && $image) {
                Storage::disk('public')->delete($image);
                $image = null;
            }
            if ($image && ! in_array($image, array_column($old, 'image'), true) && ! $r->hasFile("slides.$i.photo")) {
                $image = null; // only images this page stored
            }
            if (trim(($s['title_en'] ?? '') . ($s['title_ar'] ?? '')) === '' && ! $image) {
                continue;
            }
            $out[] = ['image' => $image, 'art' => $s['art'] ?? 'electrical'] + array_map(fn ($v) => (string) $v, array_intersect_key($s, array_flip(['title_en', 'title_ar', 'sub_en', 'sub_ar'])));
        }
        Setting::put('slides', $out);

        return back()->with('ok', __('Banner saved — it is live on the home page.'));
    }
}
