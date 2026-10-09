<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\V2\Study;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Office users: admins (everything) and engineers (review studies and submittals). */
class UserController extends Controller
{
    public function index()
    {
        $open = Study::whereIn('status', ['submitted', 'review'])->whereNotNull('assigned_to')->selectRaw('assigned_to, count(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');
        $issued = Study::where('status', 'issued')->whereNotNull('assigned_to')->selectRaw('assigned_to, count(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');

        return view('v2.admin.users', ['users' => User::orderByDesc('active')->orderBy('name')->get(), 'open' => $open, 'issued' => $issued]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|max:160|unique:users,email',
            'role' => ['required', Rule::in(User::ROLES)], 'password' => 'required|string|min:10',
        ]);
        User::create($data + ['active' => true]);

        return back()->with('ok', "{$data['name']} can now log in at " . route('v2.admin.login') . ' with the password you chose.');
    }

    public function update(Request $r, User $user)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120', 'role' => ['required', Rule::in(User::ROLES)],
            'active' => 'nullable|boolean', 'password' => 'nullable|string|min:10',
        ]);
        $active = (bool) ($data['active'] ?? false);
        // never lock the last way in: you cannot demote or deactivate yourself
        if ($user->is($r->user()) && ($data['role'] !== 'admin' || ! $active)) {
            return back()->with('bad', 'You cannot remove your own admin role or deactivate yourself.');
        }
        $user->fill(['name' => $data['name'], 'role' => $data['role'], 'active' => $active]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        if (! $active) {
            Study::where('assigned_to', $user->id)->whereIn('status', ['submitted', 'review'])->update(['assigned_to' => null]);
        }

        return back()->with('ok', "{$user->name} updated." . (! $active ? ' Their open studies are unassigned.' : ''));
    }
}
