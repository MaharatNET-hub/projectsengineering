<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\V2\Activity;
use App\Models\V2\Client;
use App\Models\V2\Study;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Client accounts: one place for all of a client's studies. Studies are linked when sent while logged
 * in, when sent earlier in the same browser session, or by entering their tracking code (the code is
 * the secret that proves the study is theirs — an email address alone is not, it is not verified).
 */
class AccountController extends Controller
{
    private function guard()
    {
        return Auth::guard('client');
    }

    public function loginForm()
    {
        return view('v2.account.login');
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! $this->guard()->attempt($cred, $r->boolean('remember'))) {
            return back()->withErrors(['email' => __('studies.account.wrong')])->onlyInput('email');
        }
        $r->session()->regenerate();
        $this->claimSession($r);

        return redirect()->intended(route('v2.account'));
    }

    public function registerForm()
    {
        return view('v2.account.register');
    }

    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120', 'company' => 'nullable|string|max:160', 'email' => 'required|email|max:160|unique:v2_clients,email',
            'phone' => 'nullable|string|max:40', 'password' => 'required|string|min:8|confirmed',
        ]);
        $client = Client::create($data + ['locale' => app()->getLocale()]);
        $this->guard()->login($client);
        $r->session()->regenerate();
        $this->claimSession($r);

        return redirect()->route('v2.account')->with('ok', __('studies.account.welcome'));
    }

    /** Studies sent from this browser session before logging in become the account's. */
    private function claimSession(Request $r): void
    {
        $ids = (array) $r->session()->get('studies', []);
        if ($ids) {
            Study::whereIn('id', $ids)->whereNull('client_id')->update(['client_id' => $this->guard()->id()]);
        }
    }

    public function index()
    {
        $client = $this->guard()->user();
        // one line per study: its latest revision
        $studies = $client->studies()->whereDoesntHave('child')->get();

        return view('v2.account.index', ['client' => $client, 'studies' => $studies]);
    }

    public function claim(Request $r)
    {
        $code = strtoupper(trim((string) $r->validate(['code' => 'required|string|max:20'])['code']));
        $s = Study::where('code', $code)->first();
        if (! $s) {
            return back()->withErrors(['code' => __('studies.account.not_found')]);
        }
        if ($s->client_id && $s->client_id !== $this->guard()->id()) {
            return back()->withErrors(['code' => __('studies.account.taken')]);
        }
        // the whole chain of revisions comes along
        foreach ($s->history() as $h) {
            if (! $h->client_id) {
                $h->update(['client_id' => $this->guard()->id()]);
            }
        }
        Activity::forStudy($s, 'study.claimed', 'linked to a client account');

        return back()->with('ok', __('studies.account.claimed', ['code' => $code]));
    }

    public function profile(Request $r)
    {
        $client = $this->guard()->user();
        $data = $r->validate([
            'name' => 'required|string|max:120', 'company' => 'nullable|string|max:160', 'phone' => 'nullable|string|max:40',
            'current' => 'nullable|required_with:password|string', 'password' => 'nullable|string|min:8|confirmed',
        ]);
        if (! empty($data['password'])) {
            if (! Hash::check((string) $data['current'], $client->password)) {
                return back()->withErrors(['current' => __('studies.account.wrong_current')]);
            }
            $client->password = $data['password'];
        }
        $client->fill(['name' => $data['name'], 'company' => $data['company'] ?? null, 'phone' => $data['phone'] ?? null])->save();

        return back()->with('ok', __('studies.account.saved'));
    }

    public function logout(Request $r)
    {
        $this->guard()->logout();
        $r->session()->regenerateToken();

        return redirect()->route('v2.studies');
    }
}
