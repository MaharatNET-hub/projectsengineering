<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\V2\Client;
use Illuminate\Http\Request;

/** Client accounts and how many studies each has sent. */
class ClientController extends Controller
{
    public function index(Request $r)
    {
        $q = Client::withCount('studies')->latest();
        if ($r->filled('q')) {
            $term = '%' . $r->query('q') . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('company', 'like', $term));
        }

        return view('v2.admin.clients', ['clients' => $q->paginate(30)->withQueryString()]);
    }
}
