<?php

namespace App\Http\Middleware\V2;

use App\Http\Controllers\V2\Admin\SubmissionController;
use App\Models\V2\Submission;
use App\Review\Store;
use Closure;
use Illuminate\Http\Request;

/** Point the review engine at one submission's private folder for this request. */
class ScopeSubmission
{
    public function handle(Request $request, Closure $next)
    {
        $s = $request->route('submission');
        abort_unless($s instanceof Submission, 404);
        // reading needs the request to be visible to this user; changing it needs it to be theirs
        abort_unless(SubmissionController::visible($request->user())->whereKey($s->id)->exists(), 403);
        abort_unless($request->isMethod('GET') || SubmissionController::canEdit($s), 403, 'This request is assigned to another engineer.');
        Store::setBase($s->dir());
        try {
            return $next($request);
        } finally {
            Store::setBase(null);
        }
    }
}
