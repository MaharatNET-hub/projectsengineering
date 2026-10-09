<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\V2\Study;
use App\Studies\StudyTypes;
use Illuminate\Http\Request;

/** Study types: see their fields and rules, and edit the definition (JSON) — limits, wording, options. */
class StudyTypeController extends Controller
{
    public function index()
    {
        $counts = Study::selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return view('v2.admin.study-types.index', ['types' => StudyTypes::all(), 'counts' => $counts]);
    }

    /** A new type: blank, or a copy of an existing one. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/'], 'name_en' => 'required|string|max:120', 'name_ar' => 'required|string|max:120',
            'discipline' => 'required|in:Electrical,Mechanical,Plumbing,Fire,Other', 'from' => 'nullable|string',
        ], ['key.regex' => 'The key uses lower-case letters, digits and _ (e.g. lighting_study).']);
        if (StudyTypes::find($data['key'])) {
            return back()->withInput()->with('bad', "A study type \"{$data['key']}\" already exists.");
        }
        $name = ['en' => $data['name_en'], 'ar' => $data['name_ar']];
        if (! empty($data['from']) && ($src = StudyTypes::find($data['from']))) {
            $def = array_merge($src, ['key' => $data['key'], 'name' => $name, 'discipline' => $data['discipline'], 'version' => 1]);
        } else {
            $def = StudyTypes::blank($data['key'], $name, $data['discipline']);
        }
        StudyTypes::save($data['key'], $def);

        return redirect()->route('v2.admin.study-types.edit', $data['key'])->with('ok', 'Study type created. Add its fields and rules, then save — it is on the public form right away.');
    }

    public function edit(string $type)
    {
        $def = StudyTypes::find($type);
        abort_unless($def !== null, 404);

        return view('v2.admin.study-types.edit', [
            'def' => $def, 'json' => old('json', json_encode($def, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)), 'edited' => StudyTypes::isEdited($type),
            'custom' => StudyTypes::isCustom($type), 'used' => Study::where('type', $type)->count(),
        ]);
    }

    public function update(Request $r, string $type)
    {
        abort_unless(StudyTypes::find($type) !== null, 404);
        $r->validate(['json' => 'required|string|max:500000']);
        $def = json_decode($r->input('json'), true);
        if (! is_array($def)) {
            return back()->withInput()->with('bad', 'Not valid JSON: ' . json_last_error_msg());
        }
        if ($problems = StudyTypes::problems($def)) {
            return back()->withInput()->with('bad', implode(' ', array_slice($problems, 0, 6)));
        }
        $def['version'] = (int) ($def['version'] ?? 1);
        StudyTypes::save($type, $def);

        return redirect()->route('v2.admin.study-types.edit', $type)->with('ok', 'Saved. New studies use it now; press "Run the rules again" on an existing study to apply it there.');
    }

    /** Shipped type: back to its definition. Created type: deleted (only while no study uses it). */
    public function reset(string $type)
    {
        if (StudyTypes::isCustom($type)) {
            if (Study::where('type', $type)->exists()) {
                return back()->with('bad', 'Studies of this type exist, so it cannot be deleted.');
            }
            StudyTypes::reset($type);

            return redirect()->route('v2.admin.study-types')->with('ok', 'Study type deleted.');
        }
        StudyTypes::reset($type);

        return redirect()->route('v2.admin.study-types.edit', $type)->with('ok', 'Back to the shipped definition.');
    }
}
