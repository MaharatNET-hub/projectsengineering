<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Mail\V2\StudyNew;
use App\Mail\V2\StudyReceived;
use App\Models\V2\Study;
use App\Review\Extractor;
use App\Review\Store;
use App\Studies\FormValidator;
use App\Studies\Studies;
use App\Studies\StudyTypes;
use App\Studies\Uploads;
use App\V2\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Public "request a study review" flow: pick a study type, fill its form, attach the supporting file
 * (uploaded first as a draft, optionally read to pre-fill the form), send → automated check → result page.
 */
class StudyController extends Controller
{
    public const CHUNK = 1048576;

    private function type(string $type): array
    {
        $def = StudyTypes::find($type);
        abort_unless($def !== null, 404);

        return $def;
    }

    public function index()
    {
        return view('v2.studies.index', ['types' => StudyTypes::all()]);
    }

    public function form(string $type)
    {
        return view('v2.studies.form', ['def' => $this->type($type), 'chunk' => self::CHUNK, 'maxMb' => config('studies.max_upload_mb'), 'accept' => array_keys(Uploads::MAGIC)]);
    }

    // ---- supporting file (draft until the form is sent)

    public function uploadStart(Request $r): JsonResponse
    {
        $data = $r->validate(['file_name' => 'required|string|max:255', 'file_size' => 'required|integer|min:10']);
        if (! isset(Uploads::MAGIC[Uploads::ext($data['file_name'])])) {
            return response()->json(['error' => __('studies.err.file_type')], 422);
        }
        if ($data['file_size'] > config('studies.max_upload_mb') * 1048576) {
            return response()->json(['error' => __('studies.err.file_size', ['mb' => config('studies.max_upload_mb')])], 422);
        }
        $token = Uploads::start($data['file_name'], (int) $data['file_size']);
        $r->session()->push('study_uploads', $token); // only this visitor may use the draft

        return response()->json(['token' => $token, 'chunk' => self::CHUNK]);
    }

    private function draft(Request $r, string $token): string
    {
        abort_unless(in_array($token, (array) $r->session()->get('study_uploads', []), true), 403);
        abort_unless(Uploads::meta($token) !== null, 404);

        return $token;
    }

    public function uploadChunk(Request $r, string $token): JsonResponse
    {
        $this->draft($r, $token);
        $data = $r->getContent();
        if (strlen($data) > self::CHUNK + 1024) {
            return response()->json(['error' => 'Chunk too large'], 413);
        }
        $res = Uploads::chunk($token, (int) $r->query('index', 0), max(1, (int) $r->query('total', 1)), $data, config('studies.max_upload_mb') * 1048576);

        return response()->json($res, isset($res['error']) ? 422 : 200);
    }

    /** Read the PDF (time-boxed batches) and return the rows it yields for the form. */
    public function uploadExtract(Request $r, string $token): JsonResponse
    {
        $this->draft($r, $token);
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        try {
            return response()->json(Uploads::extractStep($token, (float) config('studies.step_seconds', 10)));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['done' => true, 'rows' => [], 'error' => 'unreadable']);
        }
    }

    // ---- send the form

    public function create(Request $r, string $type): JsonResponse
    {
        $def = $this->type($type);
        if ($r->filled('website')) {
            return response()->json(['error' => 'Rejected'], 422);
        }
        $contact = validator($r->all(), [
            'client_name' => 'required|string|max:120', 'client_company' => 'nullable|string|max:160', 'client_email' => 'required|email|max:160',
            'client_phone' => 'nullable|string|max:40', 'project_name' => 'required|string|max:200', 'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:3000', 'upload_token' => 'nullable|string|size:32',
        ]);
        [$values, $errors] = FormValidator::validate($def, $r->input('values'));
        foreach ($contact->errors()->messages() as $k => $m) {
            $errors[$k] = $m[0];
        }
        $token = $r->input('upload_token');
        $hasFile = $token && in_array($token, (array) $r->session()->get('study_uploads', []), true) && (Uploads::meta($token)['done'] ?? false);
        if (! $hasFile && ($def['file']['required'] ?? true)) {
            $errors['file'] = __('studies.err.file');
        }
        if ($errors) {
            return response()->json(['message' => __('studies.form.fix'), 'errors' => $errors], 422);
        }

        $s = Study::create(array_diff_key($contact->validated(), ['upload_token' => 1]) + [
            'type' => $def['key'], 'values' => $values, 'status' => 'submitted', 'locale' => app()->getLocale(),
        ]);
        if ($hasFile && ($meta = Uploads::attach($token, $s->dir()))) {
            $s->update(['file_name' => $meta['name'], 'file_size' => $meta['size'], 'page_count' => $meta['pages'] ?? null]);
        }
        // a PDF that was not read for pre-filling is read now, for the comparison with the form
        if (! empty($def['extract']) && $s->isPdf() && ! is_file($s->dir() . '/extracted.json')) {
            $this->extractNow($s);
        }
        Studies::analyse($s);
        Studies::report($s);
        $r->session()->push('studies', $s->id);
        $this->notify($s);

        return response()->json(['code' => $s->code, 'url' => route('v2.studies.show', $s->code)]);
    }

    private function extractNow(Study $s): void
    {
        try {
            @set_time_limit(300);
            Store::using($s->dir(), function () use ($s) {
                Extractor::start($s->filePath(), 'original.pdf');
                for ($i = 0; $i < 100 && ! Extractor::step(20)['done']; $i++) {
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notify(Study $s): void
    {
        try {
            Mail::to($s->client_email)->send(new StudyReceived($s));
            $office = Site::review()['notify_email'] ?: (Site::contact()['email'] ?? null);
            if ($office) {
                Mail::to($office)->send(new StudyNew($s));
            }
        } catch (\Throwable $e) {
            Log::warning('study mail failed: ' . $e->getMessage());
        }
    }

    // ---- result / tracking page (by code, like the submittal tracking page)

    public function show(string $code)
    {
        $s = Study::where('code', strtoupper($code))->firstOrFail();

        return view('v2.studies.show', ['s' => $s, 'def' => $s->def(), 'showFindings' => $s->isIssued() || config('studies.show_preliminary')]);
    }

    public function report(string $code)
    {
        $s = Study::where('code', strtoupper($code))->firstOrFail();
        abort_unless($s->isIssued() || config('studies.show_preliminary'), 404);
        $f = is_file($s->reportPath()) ? $s->reportPath() : Studies::report($s);
        abort_unless($f && is_file($f), 404);

        return response()->download($f, "Study-{$s->code}" . ($s->isIssued() ? '' : '-DRAFT') . '.pdf');
    }
}
