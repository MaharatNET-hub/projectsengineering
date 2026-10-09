<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\V2\Client;
use App\Models\V2\Study;
use App\Studies\Spreadsheet;
use App\Studies\StudyTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudyWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $dir = sys_get_temp_dir() . '/sr-wf-' . getmypid() . '-' . uniqid();
        @mkdir($dir . '/app', 0777, true);
        @mkdir($dir . '/framework/views', 0777, true);
        $this->app->useStoragePath($dir);
        config(['v2.admin_email' => 'admin@test.local', 'v2.admin_password' => 'Secret-Pass-123', 'v2.locale' => 'ar']);
        Mail::fake();
        $this->get('/v2'); // installer: database + admin account
    }

    private function admin(): User
    {
        return User::where('email', 'admin@test.local')->firstOrFail();
    }

    private function upload(string $bytes, string $name): string
    {
        $token = $this->postJson('/v2/studies/upload', ['file_name' => $name, 'file_size' => strlen($bytes)])->assertOk()->json('token');
        $this->call('POST', "/v2/studies/upload/$token/chunk?index=0&total=1", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $bytes)->assertOk();

        return $token;
    }

    private static function circuit(array $o = []): array
    {
        return $o + ['tag' => 'C1', 'use' => 'power', 'phases' => '3', 'load_kw' => '100', 'pf' => '0.85', 'length_m' => '120', 'material' => 'Cu', 'size_mm2' => '50', 'runs' => '1', 'derating' => '1', 'breaker_a' => '250'];
    }

    /** A cable study with one non-compliant circuit (Iz 197 A < In 250 A). */
    private function cableStudy(array $circuits = null, array $extra = []): Study
    {
        $token = $this->upload("PK\x03\x04" . str_repeat('x', 2000), 'schedule.xlsx');
        $code = $this->postJson('/v2/studies/cable_sizing', $extra + [
            'client_name' => 'Sara', 'client_email' => 'sara@example.com', 'project_name' => 'Tower A', 'upload_token' => $token,
            'values' => ['project' => ['v3' => 400, 'v1' => 230, 'vd_lighting' => 3, 'vd_power' => 5], 'circuits' => $circuits ?? [self::circuit(), self::circuit(['tag' => 'C2', 'size_mm2' => '120'])]],
        ])->assertOk()->json('code');

        return Study::where('code', $code)->firstOrFail();
    }

    private function issue(Study $s, string $decision): void
    {
        $this->actingAs($this->admin());
        $this->patch("/v2/admin/studies/{$s->id}", ['action' => 'issue', 'decision' => $decision, 'findings' => array_map(fn () => ['include' => 1], $s->analysis['findings'])])->assertSessionHas('ok');
        auth('web')->logout();
    }

    public function test_engineers_roles_assignment_and_activity(): void
    {
        $s = $this->cableStudy();
        $this->actingAs($this->admin());
        $this->get('/v2/admin/users')->assertOk();
        $this->post('/v2/admin/users', ['name' => 'Omar', 'email' => 'omar@test.local', 'role' => 'engineer', 'password' => 'Engineer-Pass-1'])->assertSessionHas('ok');
        $this->post('/v2/admin/users', ['name' => 'Lina', 'email' => 'lina@test.local', 'role' => 'engineer', 'password' => 'Engineer-Pass-2'])->assertSessionHas('ok');
        $omar = User::where('email', 'omar@test.local')->first();
        $lina = User::where('email', 'lina@test.local')->first();
        // the admin cannot lock themselves out
        $this->patch('/v2/admin/users/' . $this->admin()->id, ['name' => 'Administrator', 'role' => 'engineer', 'active' => 1])->assertSessionHas('bad');
        auth('web')->logout();

        // an engineer: studies yes, administration no
        $this->actingAs($omar);
        foreach (['/v2/admin/studies', "/v2/admin/studies/{$s->id}", '/v2/admin/studies/stats', '/v2/admin/me', '/v2/admin'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/v2/admin/users', '/v2/admin/study-types', '/v2/admin/settings', '/v2/admin/company', '/v2/admin/clients', '/v2/admin/messages'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->delete("/v2/admin/studies/{$s->id}")->assertForbidden();
        $this->get('/v2/admin/studies')->assertDontSee('Study types');

        // Omar takes the study; Lina cannot then edit it or take it
        $this->post("/v2/admin/studies/{$s->id}/assign", ['user' => $omar->id])->assertSessionHas('ok');
        $this->assertSame($omar->id, $s->fresh()->assigned_to);
        $this->assertSame('Omar', $s->fresh()->engineer);
        $this->get('/v2/admin/studies?who=mine')->assertSee($s->code);
        auth('web')->logout();
        $this->actingAs($lina);
        $this->patch("/v2/admin/studies/{$s->id}", ['action' => 'save', 'decision' => 'approved'])->assertForbidden();
        $this->post("/v2/admin/studies/{$s->id}/assign", ['user' => $lina->id])->assertForbidden();
        $this->get("/v2/admin/studies/{$s->id}")->assertOk()->assertSee('only they or an admin can edit');
        auth('web')->logout();

        // Omar issues it; the timeline records who did what
        $this->actingAs($omar);
        $this->patch("/v2/admin/studies/{$s->id}", ['action' => 'issue', 'decision' => 'revise', 'findings' => [['include' => 1]]])->assertSessionHas('ok');
        $this->get("/v2/admin/studies/{$s->id}")->assertSee('study issued')->assertSee('Revise and resubmit')->assertSee('study assigned')->assertSee('study submitted');
        $this->get('/v2/admin')->assertOk()->assertSee('My studies');
        auth('web')->logout();
        $this->actingAs($this->admin());
        $this->get('/v2/admin')->assertOk()->assertSee($s->code); // the admin dashboard lists the activity
        auth('web')->logout();

        // a deactivated engineer cannot log in; their open studies are released
        $s2 = $this->cableStudy();
        $s2->update(['assigned_to' => $lina->id]);
        $this->actingAs($this->admin());
        $this->patch("/v2/admin/users/{$lina->id}", ['name' => 'Lina', 'role' => 'engineer', 'active' => 0])->assertSessionHas('ok');
        $this->assertNull($s2->fresh()->assigned_to);
        auth('web')->logout();
        $this->post('/v2/admin/login', ['email' => 'lina@test.local', 'password' => 'Engineer-Pass-2'])->assertSessionHasErrors('email');
        $this->post('/v2/admin/login', ['email' => 'omar@test.local', 'password' => 'Engineer-Pass-1'])->assertRedirect();
    }

    public function test_resubmission_with_comparison(): void
    {
        $rev0 = $this->cableStudy();
        $this->assertSame(['CB2'], array_column($rev0->analysis['findings'], 'rule'));
        $this->get("/v2/studies/lv_switchgear?from={$rev0->code}")->assertNotFound(); // wrong type
        $this->get("/v2/studies/cable_sizing?from={$rev0->code}")->assertNotFound(); // not issued yet

        $this->issue($rev0, 'revise');
        $this->get("/v2/studies/r/{$rev0->code}")->assertOk()->assertSee('تقديم دراسة معدّلة (Rev 1)')->assertSee("from={$rev0->code}", false);
        $form = $this->get("/v2/studies/cable_sizing?from={$rev0->code}")->assertOk()->assertSee('المراجعة Rev 1 للدراسة')->assertSee('schedule.xlsx');
        $this->assertStringContainsString('"field":"iz_a"', $form->getContent()); // the commented field is flagged in the form

        // Rev 1: C1 now 70 mm², a new circuit C3, the same file kept
        $rev1 = $this->cableStudy([self::circuit(['size_mm2' => '70', 'breaker_a' => '200']), self::circuit(['tag' => 'C2', 'size_mm2' => '120']), self::circuit(['tag' => 'C3', 'size_mm2' => '16', 'breaker_a' => '100'])],
            ['parent_code' => $rev0->code, 'upload_token' => null, 'keep_file' => 1]);
        $this->assertSame(1, $rev1->revision);
        $this->assertTrue($rev1->parent->is($rev0));
        $this->assertSame('schedule.xlsx', $rev1->file_name);
        $this->assertNotNull($rev1->filePath());
        $diff = \App\Studies\Revisions::diff($rev0, $rev1);
        $this->assertSame([['C1', 'size_mm2'], ['C1', 'breaker_a']], array_map(fn ($c) => [$c['row'], $c['field']], $diff['changes']));
        $this->assertSame('C3', $diff['added'][0]['row']);
        $this->assertSame('C1', $diff['resolved'][0]['row']);
        $this->assertSame([], $diff['open']);
        $this->assertSame('C3', $diff['new'][0]['row']); // 16 mm² cannot carry 100 A

        $this->get("/v2/studies/r/{$rev1->code}")->assertOk()->assertSee('التغييرات منذ Rev 0')->assertSee('تم حلها');
        $this->get("/v2/studies/r/{$rev0->code}")->assertSee('لهذه الدراسة مراجعة أحدث')->assertDontSee('تقديم دراسة معدّلة');
        // a revision can be sent only once
        $this->postJson('/v2/studies/cable_sizing', ['client_name' => 'Sara', 'client_email' => 'sara@example.com', 'project_name' => 'Tower A', 'parent_code' => $rev0->code, 'keep_file' => 1,
            'values' => ['project' => [], 'circuits' => [self::circuit()]]])->assertStatus(422);
        $this->actingAs($this->admin());
        $this->get("/v2/admin/studies/{$rev1->id}")->assertOk()->assertSee('Changes since Rev 0')->assertSee('Revisions');
    }

    public function test_excel_template_and_import(): void
    {
        $csv = $this->get('/v2/studies/lv_switchgear/template/panels?lang=en')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Degree of protection [ip]', $csv);
        $this->assertStringContainsString('IP31 / IP41', $csv);
        $this->get('/v2/studies/lv_switchgear/template/project')->assertNotFound(); // not a table

        // CSV saved by Excel with ";" and Arabic headers / values
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, "اسم اللوحة;نوع اللوحة;درجة الحماية;شكل الفصل;مقطع أسلاك التحكم;سخان منع التكاثف;التيار المقنن\n"
            . "EMDB-1;EMDB / ESMDB;IP 54;Form 4, Type 6;2.50;نعم;1600\nSMDB-2;smdb;IP43;form 2 type 2;1.5;لا;400\n#note;;;;;;\nDB-3;DB;IP99;1;1;x;63\n");
        $res = $this->post('/v2/studies/lv_switchgear/import/panels', ['file' => new UploadedFile($file, 'panels.csv', 'text/csv', null, true)], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertCount(3, $res['rows']);
        $this->assertSame(['name' => 'EMDB-1', 'kind' => 'EMDB', 'ip' => '54', 'form' => '4-6', 'aux_wire' => '2.5', 'heater' => true, 'rated_a' => '1600'], $res['rows'][0]);
        $this->assertSame(['SMDB', '43', '2-2', '1.5', false], [$res['rows'][1]['kind'], $res['rows'][1]['ip'], $res['rows'][1]['form'], $res['rows'][1]['aux_wire'], $res['rows'][1]['heater']]);
        $this->assertCount(1, $res['warnings']); // IP99
        $this->assertStringContainsString('IP99', $res['warnings'][0]);

        // XLSX (a real zip with deflate), "[key]" headers from the template, numbers as numbers
        $zip = new \ZipArchive;
        $x = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        $zip->open($x, \ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Circuit [tag]</t></si><si><r><t>Cable size (mm²) </t></r><r><t>[size_mm2]</t></r></si><si><t>MDB → SMDB-A</t></si><si><t>Conductor [material]</t></si><si><t>Aluminium XLPE/SWA</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="D1" t="s"><v>3</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>25</v></c><c r="D2" t="s"><v>4</v></c></row>'
            . '<row r="3"><c r="A3" t="inlineStr"><is><t>C2</t></is></c><c r="B3"><v>2.5</v></c></row></sheetData></worksheet>');
        $zip->close();
        $res = $this->post('/v2/studies/cable_sizing/import/circuits', ['file' => new UploadedFile($x, 'c.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame([['tag' => 'MDB → SMDB-A', 'size_mm2' => '25', 'material' => 'Al'], ['tag' => 'C2', 'size_mm2' => '2.5']], $res['rows']);

        $this->post('/v2/studies/cable_sizing/import/circuits', ['file' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame([], Spreadsheet::toRows(StudyTypes::find('cable_sizing'), 'circuits', [['a', 'b'], ['1', '2']])['rows']);
    }

    public function test_client_account(): void
    {
        // a study sent before having an account, in the same browser session
        $before = $this->cableStudy();
        $this->get('/v2/account')->assertRedirect('/v2/account/login');
        $this->get('/v2/account/register')->assertOk();
        $this->post('/v2/account/register', ['name' => 'Sara Haddad', 'company' => 'Panel Co', 'email' => 'sara@example.com', 'phone' => '050', 'password' => 'client-pass', 'password_confirmation' => 'client-pass'])
            ->assertRedirect('/v2/account');
        $client = Client::where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame($client->id, $before->fresh()->client_id);
        $this->get('/v2/account')->assertOk()->assertSee($before->code)->assertSee('دراساتي');
        // the form is filled with the account's details and a new study is linked
        $this->get('/v2/studies/cable_sizing')->assertOk()->assertSee('"client_name":"Sara Haddad"', false);
        $after = $this->cableStudy();
        $this->assertSame($client->id, $after->client_id);

        // another client's study is added by its code; a study already in another account is not
        $other = Study::create(['type' => 'cable_sizing', 'client_name' => 'X', 'client_email' => 'x@example.com', 'project_name' => 'P', 'values' => []]);
        $this->post('/v2/account/claim', ['code' => strtolower($other->code)])->assertSessionHas('ok');
        $this->assertSame($client->id, $other->fresh()->client_id);
        $this->post('/v2/account/claim', ['code' => 'SNOP-E00000'])->assertSessionHasErrors('code');
        $mine = Client::create(['name' => 'Y', 'email' => 'y@example.com', 'password' => 'whatever-pass']);
        $taken = Study::create(['type' => 'cable_sizing', 'client_name' => 'Y', 'client_email' => 'y@example.com', 'project_name' => 'P', 'values' => [], 'client_id' => $mine->id]);
        $this->post('/v2/account/claim', ['code' => $taken->code])->assertSessionHasErrors('code');

        $this->put('/v2/account/profile', ['name' => 'Sara H.', 'company' => 'Panel Co', 'current' => 'wrong', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123'])->assertSessionHasErrors('current');
        $this->put('/v2/account/profile', ['name' => 'Sara H.', 'company' => 'Panel Co', 'current' => 'client-pass', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123'])->assertSessionHas('ok');
        $this->post('/v2/account/logout')->assertRedirect('/v2/studies');
        $this->post('/v2/account/login', ['email' => 'sara@example.com', 'password' => 'client-pass'])->assertSessionHasErrors('email');
        $this->post('/v2/account/login', ['email' => 'sara@example.com', 'password' => 'new-pass-123'])->assertRedirect('/v2/account');

        // a client account is not an office user
        $this->get('/v2/admin/studies')->assertRedirect('/v2/admin/login');
        $this->actingAs($this->admin(), 'web');
        $this->get('/v2/admin/clients')->assertOk()->assertSee('sara@example.com');
    }

    public function test_statistics_and_type_editor(): void
    {
        $s = $this->cableStudy();
        $this->issue($s, 'revise');
        $this->cableStudy([self::circuit(['size_mm2' => '70', 'breaker_a' => '200'])]);
        $this->actingAs($this->admin());
        $page = $this->get('/v2/admin/studies/stats?period=30')->assertOk();
        $page->assertSee('Cable capacity ≥ device rating (In ≤ Iz)')->assertSee('Revise and resubmit')->assertSee('0%'); // Rev 0 issued, not approved
        $this->get('/v2/admin/studies/stats?period=all&type=cable_sizing')->assertOk();

        // a new study type from scratch → on the public list and form at once
        $this->get('/v2/admin/study-types/lv_switchgear')->assertOk()->assertSee('type-editor.js');
        $this->post('/v2/admin/study-types', ['key' => 'Bad Key', 'name_en' => 'X', 'name_ar' => 'س', 'discipline' => 'Other'])->assertSessionHasErrors('key');
        $this->post('/v2/admin/study-types', ['key' => 'lighting_study', 'name_en' => 'Lighting study', 'name_ar' => 'دراسة الإنارة', 'discipline' => 'Electrical'])
            ->assertRedirect('/v2/admin/study-types/lighting_study');
        $this->post('/v2/admin/study-types', ['key' => 'lighting_study', 'name_en' => 'X', 'name_ar' => 'س', 'discipline' => 'Other'])->assertSessionHas('bad');
        $this->assertTrue(StudyTypes::isCustom('lighting_study'));
        $this->get('/v2/studies')->assertSee('دراسة الإنارة');
        $this->get('/v2/studies/lighting_study')->assertOk()->assertSee('القيمة المرجعية');

        // add a rule with the editor's JSON, then use it
        $def = StudyTypes::find('lighting_study');
        $def['rules'][] = ['id' => 'L1', 'section' => 'items', 'field' => 'value', 'op' => 'gte', 'value' => ['ref' => 'project.reference_value'], 'severity' => 'fail', 'label' => ['en' => 'Lux level', 'ar' => 'شدة الإنارة'], 'comment' => ['en' => '{actual} < {expected} in {rows}', 'ar' => '{actual} < {expected}']];
        $this->put('/v2/admin/study-types/lighting_study', ['json' => json_encode($def)])->assertSessionHas('ok');
        $token = $this->upload("PK\x03\x04" . str_repeat('x', 100), 'calc.zip');
        $code = $this->postJson('/v2/studies/lighting_study', ['client_name' => 'A', 'client_email' => 'a@example.com', 'project_name' => 'P', 'upload_token' => $token,
            'values' => ['project' => ['reference_value' => 300], 'items' => [['tag' => 'Office 1', 'value' => 250], ['tag' => 'Office 2', 'value' => 320]]]])->assertOk()->json('code');
        $this->assertSame('250 < 300 in Office 1', Study::where('code', $code)->first()->analysis['findings'][0]['comment']['en']);

        // a type in use cannot be deleted; an unused one can
        $this->delete('/v2/admin/study-types/lighting_study')->assertSessionHas('bad');
        $this->post('/v2/admin/study-types', ['key' => 'tmp_type', 'name_en' => 'Tmp', 'name_ar' => 'مؤقت', 'discipline' => 'Other', 'from' => 'hvac_equipment'])->assertRedirect();
        $this->assertCount(6, StudyTypes::find('tmp_type')['rules']);
        $this->delete('/v2/admin/study-types/tmp_type')->assertRedirect('/v2/admin/study-types');
        $this->assertNull(StudyTypes::find('tmp_type'));
    }
}
