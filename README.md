# Engineering Study Review — form + supporting file (experiment)

This repository started as a copy of **Laravel_Technical_Spec** (the Submittal Review Assistant below) to try a
different intake: instead of only uploading a file and hoping it contains everything, the client **fills a form
per study type** (the values the checks need) and **attaches the supporting file**. See
[Study review requests](#study-review-requests-form--supporting-file) — everything else below is unchanged.

---

# Submittal Review Assistant — Laravel

Reads a contractor's technical submittal (PDF from AutoCAD/Word), extracts each panel's values,
checks them against the project specification rules and drafts the consultant's full return file:
transmittal (Form F12.5A, parts A–D), comment sheet, the client's technical-control comments and the
submittal stamped and marked up — for the engineer to edit and approve.

Pure PHP: no Node, no Docker, no database, no external programs. Runs on shared / free hosting.
Upload guide (Arabic): [`DEPLOY.md`](DEPLOY.md) — shared/free PHP hosting via `release/*.zip`, or Render via the `Dockerfile` / `render.yaml` (Web Service, runtime Docker).

## Versions
- **v1** — `/`: the original demo (single review, no login). Unchanged by v2.
- **v2** — `/v2`: study requests go through the per-type forms at `/v2/studies` (the older "send a file"
  submittal form is off unless `V2_FILE_SUBMIT=true`); the home banner's photos are uploaded under
  *Company profile → Banner slides*; the control centre at `/v2/admin` is Arabic/English with a light/dark theme. Also: company website (AR/EN: home, about, services, projects, contact), public submittal
  form with tracking codes, and an admin dashboard at `/v2/admin` (submissions, files, the same analysis
  tool per submission, issue + email the PDF, contact inbox, content editing, CSV export). Uses a
  database (SQLite by default, created on the first request; MySQL/Postgres via `DB_URL`).

## Layout
| Path | What |
|---|---|
| `app/Pdf/` | PDF engine: `Reader` (xref, object streams, filters, damaged-file repair), `Font` (ToUnicode, encodings, CID, widths), `ContentWalker` (text positions like pdf.js, image boxes, true redaction), `Writer` (full rewrite — removed content does not survive), `Canvas` (drawing with the standard fonts) |
| `app/Review/Extractor.php` | Step 1 — classify pages, extract panel values, find text/logos to redact (time-boxed batches) |
| `app/Review/Reviewer.php` | Step 2 — apply rules, draft comments, build the issued PDF |
| `app/Http/Controllers/DemoController.php` | JSON API used by the page (chunked upload, batched reading) |
| `resources/demo/rules.json` | Project, rules and client-disclosure config (copied to `storage/app/demo/` on first run; edits from the UI go there) |
| `public/app.js`, `public/style.css`, `public/lib/pdfjs/` | The single-page UI and the PDF viewer |
| `routes/v2.php`, `app/Http/Controllers/V2/`, `app/V2/`, `app/Models/V2/` | v2 site, submission flow and admin; `App\V2\Submissions` runs the engine inside `storage/app/v2/submissions/{id}` |
| `resources/views/v2/`, `lang/{ar,en}/v2.php`, `public/lib/v2/` | v2 views, translations and assets (`workspace.js` is generated from `public/app.js`) |
| `database/seeders/` | `DemoSeeder` (v1 sample review), `V2Seeder` (admin account + site content) |

## Client disclosure
The **Client disclosure** switch (top right) hides the project's identifying details everywhere:

| Where | What happens when "Details hidden" |
|---|---|
| Screen | Names/refs replaced with neutral labels on the server — the real names never reach the browser |
| Generated pages | Transmittal, comment sheet and client comments use the labels; footer reads "CLIENT DETAILS WITHHELD" |
| Submittal drawings | Matching text and logos are **removed from the PDF content** (not only covered), then blacked out |
| File | Masked file name; metadata, bookmarks, annotations and form fields dropped; the file is fully rewritten |

What is hidden: `disclosure.aliases` (real → label, longest first) and `disclosure.redactPatterns`
(regexes for text on the drawings) in `rules.json`. Set `DEMO_LOCK_DISCLOSURE=true` in `.env` to keep
details hidden permanently (the switch is removed) — recommended for a public link.

## Run locally
```
composer install
php artisan serve                       # http://localhost:8000
php artisan demo:review file.pdf --hide=1   # same pipeline from the command line
php artisan test
php artisan v2:install                  # create/migrate/seed the v2 database (also done on the first /v2 request)
```
Put the sample submittal at `storage/app/demo/sample/submittal.pdf` to enable "Run review" on the home
page (never committed — confidential). `.env` and its key are created automatically on the first request.

## Limits
- Encrypted (password-protected) PDFs are refused with a message.
- The extraction heuristics were written for the reference submittal's layout (Pioneer / EATON data
  sheets). Check the results — and that a search of the issued PDF finds none of the hidden names — on
  the first run of every new submittal format.

## Study review requests (form + supporting file)

Public: `/v2/studies` → pick a study type → `/v2/studies/{type}` form → result page `/v2/studies/r/{code}`
(the code also works on `/v2/track`). Admin: **Studies (form)** and **Study types** in the sidebar.

| Step | What happens |
|---|---|
| Form | Fields come from the study type's definition: required values, units, min/max, options; rows for panels / circuits / units. Errors are shown next to each field (checked in the browser and again on the server). |
| Supporting file | Required (PDF, Excel, Word, image, DWG, ZIP). Uploaded in 1 MB chunks to a private draft, then moved into the study's folder. |
| Fill from file | For LV switchgear, the existing PDF extractor reads the panel data sheets and fills the panel rows; the client checks and completes them. |
| Automated check | Calculations (cable Ib / Iz / voltage drop, HVAC capacity ratio), then the rules → findings: *non-compliant*, *clarify*, *missing data*, and *form ≠ file* (what the client typed differs from what the PDF shows). A decision is suggested. |
| Engineer | Untick / reword findings (EN + AR), add comments, choose the action, issue, email. "Run the rules again" keeps the edits. |
| Report | Web page (Arabic/English, printable) + PDF (English — standard PDF fonts) with the supporting PDF appended. |

Study types shipped (`resources/studies/*.json`): **LV switchgear panels**, **Cable sizing & voltage drop**,
**HVAC equipment selection**. Add a type by adding a JSON file (and, if it needs derived values, a method in
`App\Studies\Calculators`). Engineers edit limits, options and wording from **Admin → Study types**; the edited
copy is saved in `storage/app/studies/types/` and can be reset.

| Path | What |
|---|---|
| `resources/studies/*.json` | Study type definitions: sections, fields, rules, computed values, tables |
| `app/Studies/` | `StudyTypes` (load / edit / validate definitions), `FormValidator`, `Calculators`, `Analyzer` (rules → findings), `CrossCheck` (form vs PDF), `Uploads` (drafts), `Report` (PDF), `Studies` (re-run keeping edits) |
| `app/Http/Controllers/V2/StudyController.php`, `.../Admin/StudyController.php`, `.../Admin/StudyTypeController.php` | Public flow, engineer review, type editor |
| `resources/views/v2/studies/`, `resources/views/v2/admin/studies/`, `lang/{ar,en}/studies.php`, `public/lib/v2/studies.css` | Views, translations, styles |
| `tests/Feature/StudiesTest.php` | Rules, calculations, validation, full public + admin flow |

### Categories, engineers and the check (submittals)

| Step | What happens |
|---|---|
| **Admin → Categories & criteria** | Each category (or position) has its responsible engineers, its specification PDF (the base file) and its criteria — the rules the check engine applies (IP, form of separation, aux wiring, heaters, consistency, drawing set), each citing a clause and its page in the specification. Categories can start from a copy of another's criteria. Form-based study types can be routed to a category too. |
| **Client submits** | The submission form asks for the category (required). The request goes to the category's active engineer with the fewest open requests; the upload no longer starts the analysis (Settings → "Analyse automatically" turns that back on). |
| **Engineer** | Their dashboard lists their requests and the unassigned ones of their categories (take / hand back). Engineers only see and edit those; admins see everything and can reassign. |
| **Start the check now** | Runs the v1 engine on the file with the category's criteria; the criteria tab links each clause to the specification page. The engineer edits / adds comments, chooses the action and presses *Approve & generate* for the full PDF (transmittal, comment sheet, client comments, marked-up drawings). "Re-run with the current criteria" applies edited criteria. |
| **Official letter** | "Send to the client" prefills a formal letter in the client's language (subject, body, decision, number of comments); the engineer edits it and it is emailed on letterhead with their name, job title and the company, the reviewed PDF attached. |

Limits: the file reader was built for LV switchgear panel data sheets — for other categories the check finds nothing automatically and the engineer writes the comments in the same screen (the PDF and letter work the same). The PDF uses Latin fonts, so the sign-off name on it should be in Latin letters.

### Workflow features

| Feature | Where |
|---|---|
| **Revisions (Rev 1, Rev 2…)** | When the engineer issues *revise / rejected / approved as noted*, the client's page offers "Submit a revised study": the form opens with the previous values, the commented fields highlighted, and the option to keep the previous supporting file. The new revision shows what changed (values; findings resolved / still open / new) on both sides. |
| **Excel import** | Each table has "Download the Excel template" (CSV with `Label [key]` headers) and "Import from Excel" (CSV or XLSX, Arabic or English headers and values). |
| **Draft + live checks** | The form is saved on the device as you type and restored on return; specification limits show next to a field as soon as a value breaks them (calculated values are checked on the server). |
| **Client accounts** | `/v2/account`: register / log in, all studies with their status and revisions to send, add a study by tracking code; the form is filled with the account's details. |
| **Engineers and roles** | Admin → Users: *admin* (everything) or *engineer* (studies and submittals only). Studies are assigned or taken; only the assignee or an admin edits a review. Every study has an activity timeline. |
| **Statistics** | Admin → Statistics: volume per week, open / unassigned, time to issue, first-time approval rate, most frequent problems, decisions, per type and per engineer. |
| **Visual type editor** | Admin → Study types: sections, fields (type, units, limits, choices) and rules edited with forms (JSON stays as an advanced tab); new study types from scratch or as a copy. |

Settings (`.env`): `STUDIES_MAX_UPLOAD_MB` (50), `STUDIES_SHOW_PRELIMINARY` (true — show the automated check to
the client immediately; false = status only until the engineer issues), `STUDIES_STEP_SECONDS` (10).

Limits: the cable tables are indicative (BS 7671 4E4A/4E4B, copper XLPE armoured, method C) — set the project's
own values in the study type. The PDF report cannot print Arabic text (it says so in its place); the web report can.
