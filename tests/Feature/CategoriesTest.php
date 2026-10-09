<?php

namespace Tests\Feature;

use App\Mail\V2\ReviewIssued;
use App\Models\User;
use App\Models\V2\Category;
use App\Models\V2\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    private Category $lv;

    private User $omar;

    private User $sami;

    private User $lina;

    protected function setUp(): void
    {
        parent::setUp();
        $dir = sys_get_temp_dir() . '/sr-cat-' . getmypid() . '-' . uniqid();
        @mkdir($dir . '/app', 0777, true);
        @mkdir($dir . '/framework/views', 0777, true);
        $this->app->useStoragePath($dir);
        config(['v2.admin_email' => 'admin@test.local', 'v2.admin_password' => 'Secret-Pass-123', 'v2.locale' => 'ar']);
        Mail::fake();
        $this->get('/v2'); // installer: database, admin, default categories
        $this->lv = Category::where('slug', 'lv-switchgear')->firstOrFail();
        $mk = fn ($n, $e, $t) => User::create(['name' => $n, 'email' => $e, 'password' => 'Engineer-Pass-1', 'role' => 'engineer', 'title' => $t]);
        $this->omar = $mk('Omar', 'omar@test.local', 'Senior Electrical Engineer');
        $this->sami = $mk('Sami', 'sami@test.local', 'Electrical Engineer');
        $this->lina = $mk('Lina', 'lina@test.local', 'Mechanical Engineer');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@test.local')->firstOrFail();
    }

    private function submit(?int $category, string $title = 'LV panels'): \Illuminate\Testing\TestResponse
    {
        $pdf = ReviewPipelineTest::submittal();
        $res = $this->postJson('/v2/submit', array_filter([
            'category_id' => $category, 'client_name' => 'Sara Haddad', 'client_company' => 'Panel Co', 'client_email' => 'sara@example.com',
            'project_name' => 'Tower A', 'submittal_no' => 'TS-EL-002', 'title' => $title, 'file_name' => 'sub.pdf', 'file_size' => strlen($pdf),
        ]));
        if ($res->status() !== 200) {
            return $res;
        }
        $code = $res->json('code');
        $this->call('POST', "/v2/submit/$code/chunk?index=0&total=1", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $pdf)->assertOk();

        return $res;
    }

    public function test_admin_sets_up_a_category(): void
    {
        $this->actingAs($this->admin());
        $this->get('/v2/admin/categories')->assertOk()->assertSee('LV switchgear & distribution boards')->assertSee('No engineer');
        $this->get("/v2/admin/categories/{$this->lv->id}/edit")->assertOk()->assertSee('Degree of protection');

        // engineers and the specification
        $this->put("/v2/admin/categories/{$this->lv->id}", ['name_en' => $this->lv->name_en, 'name_ar' => $this->lv->name_ar, 'discipline' => 'Electrical', 'active' => 1,
            'spec_title' => 'Vol. 3 Part F', 'engineers' => [$this->omar->id, $this->sami->id], 'study_types' => ['lv_switchgear']])->assertSessionHas('ok');
        $this->assertEqualsCanonicalizing([$this->omar->id, $this->sami->id], $this->lv->engineers()->pluck('users.id')->all());
        $spec = tempnam(sys_get_temp_dir(), 'spec');
        file_put_contents($spec, ReviewPipelineTest::submittal());
        $this->post("/v2/admin/categories/{$this->lv->id}/spec", ['spec' => new UploadedFile($spec, 'Part F.pdf', 'application/pdf', null, true)])->assertSessionHas('ok');
        $this->assertNotNull($this->lv->fresh()->specPath());
        $bad = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($bad, 'not a pdf');
        $this->post("/v2/admin/categories/{$this->lv->id}/spec", ['spec' => new UploadedFile($bad, 'x.pdf', 'application/pdf', null, true)])->assertSessionHas('bad');

        // criteria: EMDB minimum IP43 instead of IP54, one rule switched off, a junk row ignored
        $rules = [
            ['id' => 'R1', 'active' => 1, 'label' => 'Degree of protection', 'attribute' => 'ip', 'appliesTo' => 'EMDB', 'expected' => '43', 'section' => '262300', 'path' => '2.6.B', 'specPage' => '182', 'text' => 'IP 43 min.', 'comment' => 'IP to be {actual}+. Panels: {panels}.'],
            ['id' => 'R5', 'label' => 'Aux wiring', 'attribute' => 'auxWire', 'appliesTo' => 'EMDB, SMDB', 'expected' => '2.5'],
            ['id' => 'R3', 'active' => 1, 'label' => 'Form', 'attribute' => 'form', 'appliesTo' => 'emdb', 'form' => '4', 'type' => '6'],
            ['id' => 'X', 'active' => 1, 'label' => '', 'attribute' => 'ip'],
        ];
        $this->put("/v2/admin/categories/{$this->lv->id}/rules", ['rules' => $rules])->assertSessionHas('ok');
        $saved = $this->lv->fresh()->rules;
        $this->assertCount(3, $saved);
        $this->assertSame(['id' => 'R1', 'expected' => 43, 'appliesTo' => ['EMDB'], 'page' => 182], ['id' => $saved[0]['id'], 'expected' => $saved[0]['expected'], 'appliesTo' => $saved[0]['appliesTo'], 'page' => $saved[0]['clause']['specPage']]);
        $this->assertFalse($saved[1]['active']);
        $this->assertSame(['form' => '4', 'type' => '6'], $saved[2]['expected']);
        $this->assertSame(['EMDB'], $saved[2]['appliesTo']);

        // a new category, starting from a copy of the LV criteria
        $this->post('/v2/admin/categories', ['name_en' => 'Fire alarm', 'name_ar' => 'إنذار الحريق', 'discipline' => 'Fire', 'active' => 1, 'engineers' => [$this->lina->id], 'copy_from' => $this->lv->id])->assertRedirect();
        $fire = Category::where('name_en', 'Fire alarm')->firstOrFail();
        $this->assertSame('fire-alarm', $fire->slug);
        $this->assertCount(3, $fire->rules);
        $this->get('/v2/submit')->assertSee('إنذار الحريق');

        // engineers cannot manage categories
        auth('web')->logout();
        $this->actingAs($this->omar);
        $this->get('/v2/admin/categories')->assertForbidden();
        $this->put("/v2/admin/categories/{$this->lv->id}/rules", ['rules' => []])->assertForbidden();
        $this->get("/v2/admin/categories/{$this->lv->id}/spec")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        auth('web')->logout();
        $this->actingAs($this->lina);
        $this->get("/v2/admin/categories/{$this->lv->id}/spec")->assertForbidden();
    }

    public function test_requests_are_routed_checked_and_sent_with_a_letter(): void
    {
        $this->lv->engineers()->sync([$this->omar->id, $this->sami->id]);
        $this->submit(null)->assertStatus(422)->assertJsonValidationErrors('category_id');

        // round-robin by load: Omar (id lower) first, then Sami
        $first = Submission::where('code', $this->submit($this->lv->id)->json('code'))->firstOrFail();
        $second = Submission::where('code', $this->submit($this->lv->id, 'Second')->json('code'))->firstOrFail();
        $this->assertSame($this->omar->id, $first->assigned_to);
        $this->assertSame($this->sami->id, $second->assigned_to);
        $this->assertSame('received', $first->fresh()->status); // the engineer starts the check, not the upload
        $this->assertFalse(is_file($first->dir() . '/review.json'));

        // each engineer sees their own requests only
        $this->actingAs($this->sami);
        $this->get("/v2/admin/submissions/{$first->id}")->assertForbidden();
        $this->get('/v2/admin/submissions')->assertOk()->assertSee($second->code)->assertDontSee($first->code);
        $this->postJson("/v2/admin/submissions/{$first->id}/api/start")->assertForbidden();
        auth('web')->logout();
        $this->actingAs($this->lina);
        $this->get('/v2/admin/submissions')->assertOk()->assertDontSee($first->code)->assertDontSee($second->code);
        auth('web')->logout();

        // Omar: dashboard → start the check → criteria of the category (IP43 accepted for EMDB)
        $this->lv->update(['rules' => array_map(fn ($r) => $r['id'] === 'R1' ? ['expected' => 43] + $r : $r, $this->lv->rules)]);
        $this->actingAs($this->omar);
        $this->get('/v2/admin')->assertOk()->assertSee('My submittals')->assertSee($first->code)->assertSee('Start the check');
        $this->get("/v2/admin/submissions/{$first->id}")->assertOk()->assertSee('Start the check now')->assertSee('LV switchgear');
        // the criteria were taken when the file arrived; re-run picks up the edited ones
        $this->post("/v2/admin/submissions/{$first->id}/restart")->assertRedirect("/v2/admin/submissions/{$first->id}/workspace?start=1");
        $this->get("/v2/admin/submissions/{$first->id}/workspace")->assertOk()->assertSee('category: "LV switchgear', false);
        $this->postJson("/v2/admin/submissions/{$first->id}/api/start")->assertOk();
        for ($n = 0; $n < 30 && ! $this->postJson("/v2/admin/submissions/{$first->id}/api/step")->json('done'); $n++) {
        }
        $state = $this->postJson("/v2/admin/submissions/{$first->id}/api/review")->assertOk()->json();
        $rules = array_column($state['review']['comments'], 'rule');
        $this->assertNotContains('R1', $rules); // EMDB IP-43 meets the category's IP43
        $this->assertContains('R5', $rules); // 1.5 mm² aux wiring still fails
        $this->assertSame($this->lv->spec_title, $state['review']['project']['specDocument']); // "checked against" the category's specification

        // approve, then the official letter (client wrote in Arabic)
        $this->postJson("/v2/admin/submissions/{$first->id}/api/generate", ['comments' => $state['review']['comments'], 'decision' => 'Revise / Resubmit', 'engineer' => 'Omar'])->assertOk();
        $page = $this->get("/v2/admin/submissions/{$first->id}")->assertOk();
        $page->assertSee('السادة / Sara Haddad — Panel Co المحترمين', false)->assertSee('يُعدّل ويُعاد تقديمه')->assertSee('Senior Electrical Engineer');
        $this->post("/v2/admin/submissions/{$first->id}/email", ['letter_subject' => 'مراجعة التقديم TS-EL-002', 'letter_body' => "السادة المحترمين،\nنرفق لكم المراجعة."])->assertSessionHas('bad'); // array mailer: reported as not sent
        Mail::assertSent(ReviewIssued::class, function ($m) {
            $html = $m->render();

            return $m->hasTo('sara@example.com') && $m->letter['subject'] === 'مراجعة التقديم TS-EL-002' && str_contains($html, 'Senior Electrical Engineer')
                && str_contains($html, 'نرفق لكم المراجعة') && $m->engineer?->is($this->omar) && $m->attached;
        });

        // hand back → it can be taken by the other engineer of the category
        $this->post("/v2/admin/submissions/{$second->id}/assign", ['user' => $this->omar->id])->assertForbidden(); // Sami's
        auth('web')->logout();
        $this->actingAs($this->sami);
        $this->post("/v2/admin/submissions/{$second->id}/assign")->assertSessionHas('ok');
        $this->assertNull($second->fresh()->assigned_to);
        auth('web')->logout();
        $this->actingAs($this->omar);
        $this->get('/v2/admin')->assertSee('Waiting in my categories')->assertSee($second->code);
        $this->post("/v2/admin/submissions/{$second->id}/assign", ['user' => $this->omar->id])->assertSessionHas('ok');
        $this->assertSame($this->omar->id, $second->fresh()->assigned_to);
    }

    public function test_form_studies_follow_their_category(): void
    {
        $this->lv->engineers()->sync([$this->sami->id]);
        $token = $this->postJson('/v2/studies/upload', ['file_name' => 'x.zip', 'file_size' => 200])->json('token');
        $this->call('POST', "/v2/studies/upload/$token/chunk?index=0&total=1", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], "PK\x03\x04" . str_repeat('x', 196))->assertOk();
        $code = $this->postJson('/v2/studies/cable_sizing', ['client_name' => 'A', 'client_email' => 'a@example.com', 'project_name' => 'P', 'upload_token' => $token,
            'values' => ['project' => ['v3' => 400, 'v1' => 230, 'vd_lighting' => 3, 'vd_power' => 5], 'circuits' => [['tag' => 'C1', 'use' => 'power', 'phases' => '3', 'load_kw' => '10', 'pf' => '0.9', 'length_m' => '20', 'material' => 'Cu', 'size_mm2' => '10', 'runs' => '1', 'derating' => '1', 'breaker_a' => '25']]]])
            ->assertOk()->json('code');
        $this->assertSame($this->sami->id, \App\Models\V2\Study::where('code', $code)->value('assigned_to'));
    }
}
