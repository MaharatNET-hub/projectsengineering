<?php

namespace Tests\Feature;

use App\Mail\V2\StudyIssued;
use App\Mail\V2\StudyNew;
use App\Mail\V2\StudyReceived;
use App\Models\User;
use App\Models\V2\Study;
use App\Pdf\Reader;
use App\Studies\Analyzer;
use App\Studies\FormValidator;
use App\Studies\StudyTypes;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $dir = sys_get_temp_dir() . '/sr-st-' . getmypid() . '-' . uniqid();
        @mkdir($dir . '/app', 0777, true);
        @mkdir($dir . '/framework/views', 0777, true);
        $this->app->useStoragePath($dir);
        config(['v2.admin_email' => 'admin@test.local', 'v2.admin_password' => 'Secret-Pass-123', 'v2.locale' => 'ar']);
        Mail::fake();
    }

    private static function panel(array $o = []): array
    {
        return $o + ['name' => 'EMDB-A-1', 'kind' => 'EMDB', 'rated_a' => '1600', 'icw_ka' => '50', 'ip' => '54', 'form' => '4-6', 'busbar' => 'Copper',
            'tin_plated' => '1', 'aux_wire' => '2.5', 'heater' => '1', 'thermostat' => '1'];
    }

    private static function switchgear(array $panels): array
    {
        return ['project' => ['system_voltage' => '400/230V 3ph 50Hz', 'fault_level_ka' => '50', 'manufacturer' => 'Panel builder'], 'panels' => $panels];
    }

    public function test_definitions_are_valid(): void
    {
        $types = StudyTypes::all();
        $this->assertSame(['cable_sizing', 'hvac_equipment', 'lv_switchgear'], array_keys($types));
        foreach ($types as $key => $def) {
            $this->assertSame([], StudyTypes::problems($def), $key);
        }
    }

    public function test_form_values_are_validated(): void
    {
        $def = StudyTypes::find('lv_switchgear');
        [$v, $e] = FormValidator::validate($def, self::switchgear([
            self::panel(['ip' => '99', 'rated_a' => '١٦٠٠']), // unknown option; Arabic digits are accepted
            self::panel(['name' => 'emdb-a-1 ', 'icw_ka' => '500']), // duplicate name; above max
            self::panel(['name' => 'SMDB-1', 'heater' => '']), // required
        ]));
        $this->assertSame(1600.0, $v['panels'][0]['rated_a']);
        $this->assertArrayHasKey('panels.0.ip', $e);
        $this->assertArrayHasKey('panels.1.name', $e);
        $this->assertArrayHasKey('panels.1.icw_ka', $e);
        $this->assertArrayHasKey('panels.2.heater', $e);
        $this->assertCount(4, $e);

        [, $e] = FormValidator::validate($def, ['project' => [], 'panels' => []]);
        $this->assertArrayHasKey('panels', $e); // at least one row
        $this->assertArrayHasKey('project.fault_level_ka', $e);
    }

    public function test_switchgear_rules(): void
    {
        $def = StudyTypes::find('lv_switchgear');
        [$v, $e] = FormValidator::validate($def, self::switchgear([
            self::panel(),
            self::panel(['name' => 'EMDB-A-2', 'ip' => '43', 'aux_wire' => '1.5', 'icw_ka' => '36']),
            self::panel(['name' => 'SMDB-A-1', 'kind' => 'SMDB', 'form' => '2-1', 'heater' => '0']),
            self::panel(['name' => 'DB-1', 'kind' => 'DB', 'ip' => '41', 'form' => '1', 'heater' => '0', 'thermostat' => '0']), // DBs are exempt
        ]));
        $this->assertSame([], $e);
        $a = Analyzer::run($def, $v);
        $by = [];
        foreach ($a['findings'] as $f) {
            $by[$f['rule']] = $f;
        }
        $this->assertEqualsCanonicalizing(['SW1', 'SW4', 'SW5', 'SW6', 'SW9'], array_keys($by));
        $this->assertSame(['EMDB-A-2'], $by['SW1']['rows']);
        $this->assertStringContainsString('submitted IP43', $by['SW1']['comment']['en']);
        $this->assertStringContainsString('36 kA', $by['SW5']['comment']['en']);
        $this->assertStringContainsString('50 kA', $by['SW5']['comment']['en']);
        $this->assertStringContainsString('Form 2 Type 1', $by['SW4']['comment']['en']);
        $this->assertStringContainsString('SMDB-A-1', $by['SW9']['comment']['ar']);
        $this->assertSame('revise', $a['suggested']);
        $this->assertSame(5, $a['stats']['fail']);

        // everything compliant → approved
        [$v] = FormValidator::validate($def, self::switchgear([self::panel()]));
        $a = Analyzer::run($def, $v);
        $this->assertSame([], $a['findings']);
        $this->assertSame('approved', $a['suggested']);
    }

    public function test_cable_sizing_calculation(): void
    {
        $def = StudyTypes::find('cable_sizing');
        $c = ['use' => 'power', 'phases' => '3', 'load_kw' => '100', 'pf' => '0.85', 'length_m' => '120', 'material' => 'Cu', 'size_mm2' => '70', 'runs' => '1', 'derating' => '1', 'breaker_a' => '200'];
        [$v, $e] = FormValidator::validate($def, ['project' => ['v3' => 400, 'v1' => 230, 'vd_lighting' => 3, 'vd_power' => 5], 'circuits' => [
            ['tag' => 'C1'] + $c,
            ['tag' => 'C2', 'size_mm2' => '50', 'breaker_a' => '250'] + $c, // Iz 197 < In 250
            ['tag' => 'C3', 'load_kw' => '2', 'phases' => '1', 'use' => 'lighting', 'size_mm2' => '1.5', 'length_m' => '60', 'breaker_a' => '16', 'pf' => '0.7'] + $c,
        ]]);
        $this->assertSame([], $e);
        $a = Analyzer::run($def, $v);
        [$c1, $c2, $c3] = $a['values']['circuits'];
        $this->assertEqualsWithDelta(169.8, $c1['ib_a'], 0.1);
        $this->assertEqualsWithDelta(251, $c1['iz_a'], 0.1);
        $this->assertEqualsWithDelta(3.06, $c1['vd_pct'], 0.01);
        $this->assertEqualsWithDelta(12.4, $c3['ib_a'], 0.1);
        $this->assertGreaterThan(3, $c3['vd_pct']); // 31 mV/A/m × 12.4 A × 60 m = 23 V = 10 %
        $rules = array_column($a['findings'], 'rows', 'rule');
        $this->assertSame(['C2'], $rules['CB2']);
        $this->assertSame(['C3'], $rules['CB3']);
        $this->assertSame(['C3'], $rules['CB4']); // pf 0.7 → clarify
        $this->assertArrayNotHasKey('CB1', $rules);
    }

    public function test_hvac_rules_with_optional_values(): void
    {
        $def = StudyTypes::find('hvac_equipment');
        $u = ['type' => 'AHU', 'design_kw' => '100', 'offered_kw' => '110', 'eer' => '', 'refrigerant' => '', 'sound_dba' => '60', 'supply' => '400V/3/50'];
        [$v, $e] = FormValidator::validate($def, ['project' => ['ambient_c' => 46, 'supply' => '400V/3/50', 'min_eer' => 2.8, 'max_sound' => 75, 'oversize_pct' => 20], 'units' => [
            ['tag' => 'AHU-1'] + $u, // chilled water: EER / refrigerant rules do not apply
            ['tag' => 'CH-1', 'type' => 'Chiller', 'offered_kw' => '95', 'eer' => '', 'refrigerant' => 'R22', 'supply' => '380V/3/60'] + $u,
            ['tag' => 'FCU-1', 'offered_kw' => '150'] + $u, // 50 % over-sized → clarify
        ]]);
        $this->assertSame([], $e);
        $a = Analyzer::run($def, $v);
        $got = array_map(fn ($f) => $f['rule'] . ':' . $f['status'] . ':' . implode(',', $f['rows']), $a['findings']);
        $this->assertEqualsCanonicalizing(['HV1:fail:CH-1', 'HV2:warn:FCU-1', 'HV3:missing:CH-1', 'HV4:fail:CH-1', 'HV6:fail:CH-1'], $got);
    }

    /** Upload a file to a draft through the public endpoints; returns the token. */
    private function upload(string $bytes, string $name = 'panels.pdf'): string
    {
        $token = $this->postJson('/v2/studies/upload', ['file_name' => $name, 'file_size' => strlen($bytes)])->assertOk()->json('token');
        $parts = str_split($bytes, 1500);
        foreach ($parts as $i => $part) {
            $this->call('POST', "/v2/studies/upload/$token/chunk?index=$i&total=" . count($parts), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $part)->assertOk();
        }

        return $token;
    }

    private function contact(): array
    {
        return ['client_name' => 'Sara Haddad', 'client_company' => 'Panel Co', 'client_email' => 'sara@example.com', 'project_name' => 'Tower A', 'reference' => 'TS-EL-002'];
    }

    public function test_public_flow_with_prefill_and_cross_check(): void
    {
        $this->get('/v2/studies')->assertOk()->assertSee('dir="rtl"', false)->assertSee('مراجعة لوحات الجهد المنخفض');
        $this->get('/v2/studies/lv_switchgear?lang=en')->assertOk()->assertSee('Fill the table from the file')->assertSee('Anti-condensation heater');
        $this->get('/v2/studies/cable_sizing')->assertOk();
        $this->get('/v2/studies/nope')->assertNotFound();

        // a file that is not what its extension says is refused
        $bad = $this->postJson('/v2/studies/upload', ['file_name' => 'x.pdf', 'file_size' => 2000])->assertOk()->json('token');
        $this->call('POST', "/v2/studies/upload/$bad/chunk?index=0&total=1", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], str_repeat('A', 2000))->assertStatus(422);
        $this->postJson('/v2/studies/upload', ['file_name' => 'x.exe', 'file_size' => 2000])->assertStatus(422);

        // the form cannot be sent without the supporting file
        $this->postJson('/v2/studies/lv_switchgear', $this->contact() + ['values' => self::switchgear([self::panel()])])
            ->assertStatus(422)->assertJsonPath('errors.file', fn ($m) => $m !== null);

        // the sample submittal: EMDB-A-1 is IP-43 / 1.5 mm², SMDB-A-1 is IP-54 / 2.5 mm²
        $token = $this->upload(ReviewPipelineTest::submittal());
        $rows = [];
        for ($n = 0; $n < 30; $n++) {
            $r = $this->postJson("/v2/studies/upload/$token/extract")->assertOk()->json();
            if ($r['done']) {
                $rows = $r['rows'];
                break;
            }
        }
        $rows = array_column($rows, null, 'name');
        $this->assertSame(['EMDB-A-1', 'SMDB-A-1'], array_keys($rows));
        $this->assertSame('43', $rows['EMDB-A-1']['ip']);
        $this->assertSame('4-6', $rows['EMDB-A-1']['form']);
        $this->assertSame('1.5', $rows['EMDB-A-1']['aux_wire']);
        $this->assertSame('SMDB', $rows['SMDB-A-1']['kind']);

        // another visitor cannot use this draft
        $this->flushSession();
        $this->postJson("/v2/studies/upload/$token/extract")->assertForbidden();
        $this->withSession(['study_uploads' => [$token]]);

        // the client enters IP54 for EMDB-A-1 (the file says IP-43) and adds a panel the file does not have
        $res = $this->postJson('/v2/studies/lv_switchgear', $this->contact() + ['upload_token' => $token, 'notes' => 'Rev 0', 'values' => self::switchgear([
            self::panel(['aux_wire' => '1.5']),
            self::panel(['name' => 'SMDB-A-1', 'kind' => 'SMDB', 'form' => '2-2']),
            self::panel(['name' => 'EMDB-B-1']),
        ])])->assertOk();
        $s = Study::where('code', $res->json('code'))->firstOrFail();
        $this->assertSame('submitted', $s->status);
        $this->assertSame('panels.pdf', $s->file_name);
        $this->assertSame(6, $s->page_count);
        $this->assertTrue($s->isPdf());
        $this->assertDirectoryDoesNotExist(storage_path("app/studies/drafts/$token"));

        $f = collect($s->analysis['findings']);
        $this->assertSame(['SW6'], $f->where('status', 'fail')->pluck('rule')->all()); // aux wire 1.5
        $mismatch = $f->where('status', 'mismatch');
        $this->assertTrue($mismatch->contains(fn ($m) => $m['rule'] === 'X1' && $m['field'] === 'ip' && str_contains($m['comment']['en'], 'IP43')));
        $this->assertTrue($mismatch->contains(fn ($m) => $m['rule'] === 'X0' && $m['rows'] === ['EMDB-B-1']));
        $this->assertSame(['ran' => true, 'panels' => 2, 'matched' => 2, 'pageCount' => 6], $s->analysis['crossCheck']);
        $this->assertSame('revise', $s->analysis['suggested']);
        Mail::assertSent(StudyReceived::class, fn ($m) => $m->hasTo('sara@example.com'));
        Mail::assertSent(StudyNew::class);

        // the client's page: preliminary findings, in Arabic
        $this->get($res->json('url'))->assertOk()->assertSee($s->code)->assertSee('الفحص الآلي (مبدئي)')->assertSee('يُعدّل ويُعاد تقديمه')->assertSee('مقطع أسلاك التحكم');
        // report: generated pages + the 6 pages of the supporting file
        $pdf = $this->get("/v2/studies/r/{$s->code}/report")->assertOk();
        $out = file_get_contents($pdf->baseResponse->getFile()->getPathname());
        $pages = count((new Reader($out))->pages());
        $this->assertGreaterThan(6, $pages);

        // without preliminary results the client only sees the status
        config(['studies.show_preliminary' => false]);
        $this->get($res->json('url'))->assertOk()->assertDontSee('غير مقبول؛ الحد الأدنى')->assertSee('يقوم مهندسنا بمراجعة دراستك');
        $this->get("/v2/studies/r/{$s->code}/report")->assertNotFound();
    }

    public function test_engineer_edits_issues_and_emails(): void
    {
        $xlsx = "PK\x03\x04" . str_repeat('x', 3000);
        $token = $this->upload($xlsx, 'schedule.xlsx');
        $code = $this->postJson('/v2/studies/cable_sizing', $this->contact() + ['upload_token' => $token, 'values' => ['project' => ['v3' => 400, 'v1' => 230, 'vd_lighting' => 3, 'vd_power' => 5], 'circuits' => [
            ['tag' => 'MDB → SMDB-A', 'use' => 'power', 'phases' => '3', 'load_kw' => '100', 'pf' => '0.85', 'length_m' => '120', 'material' => 'Cu', 'size_mm2' => '50', 'runs' => '1', 'derating' => '1', 'breaker_a' => '250'],
        ]]])->assertOk()->json('code');
        $s = Study::where('code', $code)->firstOrFail();
        $this->assertFalse($s->isPdf());
        $this->assertSame(['CB2'], array_column($s->analysis['findings'], 'rule'));
        $this->assertFileExists($s->reportPath()); // report alone (the xlsx is not appended)

        $this->get('/v2/admin/studies')->assertRedirect('/v2/admin/login');
        $this->actingAs(User::first());
        $this->get('/v2/admin/studies')->assertOk()->assertSee($code);
        $this->get("/v2/admin/studies/{$s->id}")->assertOk()->assertSee('Cable capacity');
        $this->get("/v2/admin/studies/{$s->id}/file")->assertOk();
        $this->get("/v2/admin/studies/{$s->id}/report")->assertOk();
        $this->get('/v2/admin')->assertOk();

        // issuing needs a decision
        $this->patch("/v2/admin/studies/{$s->id}", ['action' => 'issue', 'findings' => [['include' => 1]]])->assertSessionHas('bad');
        $this->post("/v2/admin/studies/{$s->id}/email")->assertSessionHas('bad');

        $this->patch("/v2/admin/studies/{$s->id}", [
            'action' => 'issue', 'decision' => 'revise', 'engineer' => 'Eng. Omar', 'remarks' => 'Resubmit with 70 mm².',
            'findings' => [['include' => 1, 'en' => 'Increase the cable to 70 mm².', 'ar' => 'يجب زيادة المقطع إلى 70 مم².']],
            'add_en' => 'Provide the derating calculation.', 'add_status' => 'warn',
        ])->assertSessionHas('ok');
        $s->refresh();
        $this->assertSame('issued', $s->status);
        $this->assertCount(2, $s->keptFindings());
        $this->assertTrue($s->analysis['findings'][0]['edited']);

        // re-running the rules keeps the edits and the engineer's own comment
        $this->post("/v2/admin/studies/{$s->id}/reanalyse")->assertSessionHas('ok');
        $s->refresh();
        $this->assertSame('Increase the cable to 70 mm².', $s->analysis['findings'][0]['comment']['en']);
        $this->assertSame('ENG', $s->analysis['findings'][1]['rule']);

        $this->post("/v2/admin/studies/{$s->id}/email", ['note' => 'See comments.'])->assertSessionHas('bad'); // mail driver "array" → reported as not sent
        Mail::assertSent(StudyIssued::class, fn ($m) => $m->hasTo('sara@example.com') && $m->attached);

        $this->get('/v2/track?code=' . strtolower($code))->assertRedirect("/v2/studies/r/$code");
        $this->get("/v2/studies/r/$code")->assertOk()->assertSee('مراجعة المهندس')->assertSee('يجب زيادة المقطع إلى 70 مم²')->assertSee('Eng. Omar');

        $this->delete("/v2/admin/studies/{$s->id}")->assertRedirect('/v2/admin/studies');
        $this->assertDirectoryDoesNotExist($s->dir());
    }

    public function test_engineer_edits_a_study_type(): void
    {
        $this->actingAs($this->installAndGetAdmin());
        $this->get('/v2/admin/study-types')->assertOk()->assertSee('lv_switchgear');
        $this->get('/v2/admin/study-types/lv_switchgear')->assertOk();
        $this->put('/v2/admin/study-types/lv_switchgear', ['json' => '{nope'])->assertSessionHas('bad');
        $def = StudyTypes::find('lv_switchgear');
        $def['rules'][0]['op'] = 'bigger';
        $this->put('/v2/admin/study-types/lv_switchgear', ['json' => json_encode($def)])->assertSessionHas('bad');

        // allow 1.5 mm² auxiliary wiring
        $def = StudyTypes::find('lv_switchgear');
        foreach ($def['rules'] as &$r) {
            if ($r['id'] === 'SW6') {
                $r['value'] = 1.5;
            }
        }
        unset($r);
        $this->put('/v2/admin/study-types/lv_switchgear', ['json' => json_encode($def, JSON_UNESCAPED_UNICODE)])->assertSessionHas('ok');
        $this->assertTrue(StudyTypes::isEdited('lv_switchgear'));
        [$v] = FormValidator::validate(StudyTypes::find('lv_switchgear'), self::switchgear([self::panel(['aux_wire' => '1.5'])]));
        $this->assertSame([], Analyzer::run(StudyTypes::find('lv_switchgear'), $v)['findings']);

        $this->delete('/v2/admin/study-types/lv_switchgear')->assertSessionHas('ok');
        $this->assertFalse(StudyTypes::isEdited('lv_switchgear'));
    }

    private function installAndGetAdmin(): User
    {
        $this->get('/v2'); // the installer creates the admin account on the first request

        return User::firstOrFail();
    }
}
