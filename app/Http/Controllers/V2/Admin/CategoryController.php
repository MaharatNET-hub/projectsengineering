<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\V2\Category;
use App\Models\V2\Submission;
use App\Pdf\Reader;
use App\Studies\StudyTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Review categories (positions): the responsible engineers, the specification PDF and the criteria the
 * check applies. Admins manage them; engineers can open the specification of their categories.
 */
class CategoryController extends Controller
{
    public function index()
    {
        $open = Submission::whereIn('status', ['received', 'analysing', 'review'])->whereNotNull('category_id')->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        return view('v2.admin.categories.index', ['categories' => Category::with('engineers')->withCount('submissions')->orderBy('sort')->orderBy('id')->get(), 'open' => $open]);
    }

    public function create()
    {
        return view('v2.admin.categories.edit', ['c' => new Category(['active' => true, 'discipline' => 'Electrical']), 'engineers' => $this->engineers(), 'types' => StudyTypes::all(), 'copyFrom' => Category::orderBy('sort')->get()]);
    }

    public function edit(Category $category)
    {
        return view('v2.admin.categories.edit', ['c' => $category, 'engineers' => $this->engineers(), 'types' => StudyTypes::all(), 'copyFrom' => collect()]);
    }

    private function engineers()
    {
        return User::where('active', true)->orderBy('name')->get();
    }

    private function data(Request $r): array
    {
        return $r->validate([
            'name_en' => 'required|string|max:160', 'name_ar' => 'required|string|max:160', 'discipline' => 'required|in:Electrical,Mechanical,Plumbing,Fire,Other',
            'description_en' => 'nullable|string|max:2000', 'description_ar' => 'nullable|string|max:2000', 'spec_title' => 'nullable|string|max:255',
            'sort' => 'nullable|integer|min:0|max:999', 'engineers' => 'array', 'engineers.*' => 'exists:users,id', 'study_types' => 'array', 'study_types.*' => 'string|max:60',
        ]);
    }

    public function store(Request $r)
    {
        $d = $this->data($r);
        $from = $r->filled('copy_from') ? Category::find($r->input('copy_from')) : null;
        $c = Category::create([
            'slug' => $this->slug($d['name_en']), 'name_en' => $d['name_en'], 'name_ar' => $d['name_ar'], 'discipline' => $d['discipline'],
            'description_en' => $d['description_en'] ?? null, 'description_ar' => $d['description_ar'] ?? null, 'spec_title' => $d['spec_title'] ?? $from?->spec_title,
            'sort' => $d['sort'] ?? (int) Category::max('sort') + 1, 'active' => $r->boolean('active', true), 'study_types' => $this->types($d),
            'rules' => $from?->rules ?? [],
        ]);
        $c->engineers()->sync($d['engineers'] ?? []);

        return redirect()->route('v2.admin.categories.edit', $c)->with('ok', __('Category created. Upload its specification and set its criteria below.'));
    }

    public function update(Request $r, Category $category)
    {
        $d = $this->data($r);
        $category->update([
            'name_en' => $d['name_en'], 'name_ar' => $d['name_ar'], 'discipline' => $d['discipline'], 'description_en' => $d['description_en'] ?? null,
            'description_ar' => $d['description_ar'] ?? null, 'spec_title' => $d['spec_title'] ?? null, 'sort' => $d['sort'] ?? $category->sort,
            'active' => $r->boolean('active'), 'study_types' => $this->types($d),
        ]);
        $category->engineers()->sync($d['engineers'] ?? []);

        return back()->with('ok', __('Category saved.'));
    }

    private function types(array $d): array
    {
        return array_values(array_intersect($d['study_types'] ?? [], array_keys(StudyTypes::all())));
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        for ($i = 2; Category::where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    public function destroy(Category $category)
    {
        $name = $category->name_en;
        $category->delete(); // its submissions keep their files; they just lose the category

        return redirect()->route('v2.admin.categories')->with('ok', __('Category ":name" deleted.', ['name' => $name]));
    }

    // ---- the specification PDF

    public function uploadSpec(Request $r, Category $category)
    {
        $r->validate(['spec' => 'required|file|max:' . (64 * 1024)]);
        $file = $r->file('spec');
        $bytes = (string) file_get_contents($file->getRealPath());
        if (! str_starts_with($bytes, '%PDF')) {
            return back()->with('bad', __('The specification must be a PDF file.'));
        }
        try {
            $pages = count((new Reader($bytes))->pages());
        } catch (\Throwable $e) {
            return back()->with('bad', __('This PDF cannot be read: :error', ['error' => $e->getMessage()]));
        }
        File::ensureDirectoryExists($category->dir());
        $file->move($category->dir(), 'spec.pdf');
        $category->update(['spec_file' => basename($file->getClientOriginalName()), 'spec_size' => strlen($bytes), 'spec_title' => $category->spec_title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)]);

        return back()->with('ok', __('Specification uploaded (:n pages).', ['n' => $pages]));
    }

    /** Opened in the browser's PDF viewer; "#page=N" in the link jumps to a clause's page. */
    public function spec(Category $category)
    {
        $u = auth()->user();
        abort_unless($u->isAdmin() || $category->engineers()->whereKey($u->id)->exists() || Submission::where('category_id', $category->id)->where('assigned_to', $u->id)->exists(), 403);
        abort_unless($f = $category->specPath(), 404);

        return response()->file($f, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . addslashes($category->spec_file ?: 'specification.pdf') . '"']);
    }

    public function deleteSpec(Category $category)
    {
        @unlink($category->dir() . '/spec.pdf');
        $category->update(['spec_file' => null, 'spec_size' => 0]);

        return back()->with('ok', __('Specification removed.'));
    }

    // ---- the criteria (rules of the check engine)

    public function rules(Request $r, Category $category)
    {
        $r->validate(['rules' => 'array|max:100']);
        $rules = [];
        foreach ((array) $r->input('rules', []) as $i => $x) {
            $attr = (string) ($x['attribute'] ?? '');
            if (! isset(Category::ATTRIBUTES[$attr]) || trim((string) ($x['label'] ?? '')) === '') {
                continue;
            }
            $op = Category::ATTRIBUTES[$attr][1][0];
            $rule = [
                'id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($x['id'] ?? '')) ?: 'R' . ($i + 1),
                'active' => (bool) ($x['active'] ?? false),
                'appliesTo' => array_values(array_filter(array_map(fn ($a) => strtoupper(trim($a)), explode(',', (string) ($x['appliesTo'] ?? '*'))))) ?: ['*'],
                'attribute' => $attr, 'label' => trim((string) $x['label']), 'operator' => $op,
                'clause' => [
                    'section' => trim((string) ($x['section'] ?? '')) ?: '—', 'path' => trim((string) ($x['path'] ?? '')),
                    'specPage' => is_numeric($x['specPage'] ?? null) ? (int) $x['specPage'] : null, 'text' => trim((string) ($x['text'] ?? '')),
                ],
                'comment' => trim((string) ($x['comment'] ?? '')) ?: 'Not compliant with the specification ({actual}). Refer Section {section} clause {path}. Panels: {panels}.',
            ];
            if ($op === 'gte' || $op === 'minAll') {
                $rule['expected'] = is_numeric($x['expected'] ?? null) ? +$x['expected'] : ($op === 'gte' ? 54 : 2.5);
            }
            if ($op === 'minAll') {
                $rule['unit'] = 'sq.mm';
            }
            if ($op === 'form') {
                $rule['expected'] = ['form' => trim((string) ($x['form'] ?? '4')), 'type' => trim((string) ($x['type'] ?? '')) ?: null];
            }
            foreach (['note', 'markup'] as $k) {
                if (trim((string) ($x[$k] ?? '')) !== '') {
                    $rule[$k] = trim((string) $x[$k]);
                }
            }
            $rules[] = $rule;
        }
        $category->update(['rules' => $rules]);

        return back()->with('ok', __(':n criteria saved. New checks use them; press "Re-run with the current criteria" on a submittal to apply them there.', ['n' => count($rules)]));
    }
}
