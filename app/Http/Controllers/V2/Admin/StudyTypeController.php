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

    public function edit(string $type)
    {
        $def = StudyTypes::find($type);
        abort_unless($def !== null, 404);

        return view('v2.admin.study-types.edit', [
            'def' => $def, 'json' => old('json', json_encode($def, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)), 'edited' => StudyTypes::isEdited($type),
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

    public function reset(string $type)
    {
        StudyTypes::reset($type);

        return redirect()->route('v2.admin.study-types.edit', $type)->with('ok', 'Back to the shipped definition.');
    }
}
