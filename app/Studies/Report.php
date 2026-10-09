<?php

namespace App\Studies;

use App\Models\V2\Study;
use App\Pdf\Canvas;
use App\Pdf\Dict;
use App\Pdf\FontData;
use App\Pdf\Name;
use App\Pdf\Reader;
use App\Pdf\Ref;
use App\Pdf\Str;
use App\Pdf\Writer;
use App\V2\Site;

/**
 * The study's review report as PDF (English — the PDF engine has the standard Latin fonts only; the
 * web report is bilingual): cover data, decision, findings, checks, entered values, and the
 * supporting PDF appended after it.
 */
final class Report
{
    private const W = 595.0;

    private const H = 842.0;

    private const M = 40.0;

    private const INK = [0.06, 0.09, 0.16];

    private const GREY = [0.39, 0.45, 0.55];

    private const LINE = [0.85, 0.88, 0.92];

    private const BRAND = [0.07, 0.19, 0.42];

    private const COLORS = [
        'fail' => [0.73, 0.11, 0.11], 'warn' => [0.71, 0.33, 0.04], 'missing' => [0.39, 0.45, 0.55], 'mismatch' => [0.48, 0.18, 0.66],
        'pass' => [0.08, 0.5, 0.24], 'na' => [0.6, 0.64, 0.7],
    ];

    public const DECISIONS = ['approved' => 'APPROVED', 'noted' => 'APPROVED AS NOTED', 'revise' => 'REVISE AND RESUBMIT', 'rejected' => 'REJECTED'];

    public const STATUS = ['fail' => 'NON-COMPLIANT', 'warn' => 'CLARIFY', 'missing' => 'MISSING DATA', 'mismatch' => 'FORM vs FILE', 'pass' => 'COMPLIES', 'na' => 'N/A'];

    /** @var list<Canvas> */
    private array $pages = [];

    private Canvas $c;

    private float $y = 0;

    private function __construct(private Study $s, private array $def) {}

    /** Build report.pdf in the study's folder; returns its path. */
    public static function build(Study $s): string
    {
        $r = new self($s, $s->def() ?? ['name' => $s->type, 'sections' => []]);
        $r->compose();

        return $r->write();
    }

    /** Text the standard fonts can show; other scripts (e.g. Arabic) are flagged instead of printed as "?". */
    public static function latin(?string $s): string
    {
        static $ok = null;
        $ok ??= array_flip(FontData::WIN_ANSI);
        $s = trim((string) $s);
        foreach (mb_str_split($s) as $ch) {
            $cp = mb_ord($ch);
            if (! isset($ok[$cp]) && $cp !== 0x2212 && $cp !== 0x2192 && $cp !== 0x2265 && $cp !== 0x2264 && $cp !== 0x2260) {
                return '[non-Latin text - see the online report]';
            }
        }

        return strtr($s, ["\u{2192}" => '->', "\u{2265}" => '>=', "\u{2264}" => '<=', "\u{2260}" => '!=']);
    }

    private function page(): void
    {
        $this->c = new Canvas;
        $this->pages[] = $this->c;
        $this->y = self::H - self::M;
        $this->c->text(self::latin((Site::company()['name_en'] ?? '') ?: Site::name()), self::M, $this->y, 9, self::GREY, true);
        $tag = $this->s->isIssued() ? 'ISSUED' : 'DRAFT - AUTOMATED CHECK, SUBJECT TO ENGINEER REVIEW';
        $this->c->text($tag, self::W - self::M - Canvas::width($tag, 8, true), $this->y, 8, $this->s->isIssued() ? self::COLORS['pass'] : self::COLORS['fail'], true);
        $this->y -= 10;
        $this->c->line(self::M, $this->y, self::W - self::M, $this->y, self::LINE, 0.8);
        $this->y -= 22;
    }

    /** Start a new page when fewer than $need points are left. */
    private function room(float $need): void
    {
        if ($this->y - $need < 60) {
            $this->page();
        }
    }

    private function heading(string $t): void
    {
        $this->room(40);
        $this->y -= 6;
        $this->c->text($t, self::M, $this->y, 11.5, self::BRAND, true);
        $this->y -= 8;
        $this->c->line(self::M, $this->y, self::W - self::M, $this->y, self::BRAND, 1);
        $this->y -= 16;
    }

    /** Wrapped paragraph; returns the lines used. */
    private function para(string $t, float $x, float $width, float $size = 9, array $color = self::INK, bool $bold = false): int
    {
        $lines = Canvas::wrap(self::latin($t), $size, $width, $bold);
        foreach ($lines as $l) {
            $this->room($size + 4);
            $this->c->text($l, $x, $this->y, $size, $color, $bold);
            $this->y -= $size + 3.5;
        }

        return count($lines);
    }

    private function compose(): void
    {
        $s = $this->s;
        $a = $s->analysis ?? [];
        $en = fn ($v) => StudyTypes::t($v, 'en');
        $this->page();

        $this->c->text('STUDY REVIEW REPORT', self::M, $this->y, 18, self::INK, true);
        $this->y -= 18;
        $this->para($en($this->def['name']), self::M, 380, 11, self::GREY);
        $this->c->text($s->code, self::W - self::M - Canvas::width($s->code, 13, true), self::H - self::M - 32, 13, self::BRAND, true);
        $this->y -= 8;

        $rows = [
            ['Project', $s->project_name], ['Reference', $s->reference ?: '-'],
            ['Submitted by', trim($s->client_name . ($s->client_company ? ', ' . $s->client_company : ''))], ['Submitted on', $s->created_at?->format('d M Y H:i') ?? '-'],
            ['Specification', $en($this->def['spec'] ?? '') ?: '-'], ['Supporting file', $s->file_name ? $s->file_name . ($s->page_count ? " ({$s->page_count} pages)" : '') : '-'],
        ];
        foreach (array_chunk($rows, 2) as $pair) {
            $top = $this->y;
            $low = $this->y;
            foreach ($pair as $i => [$k, $v]) {
                $x = self::M + $i * 260;
                $this->y = $top;
                $this->c->text(strtoupper($k), $x, $this->y, 7, self::GREY, true);
                $this->y -= 11;
                $this->para((string) $v, $x, 245, 9.5);
                $low = min($low, $this->y);
            }
            $this->y = $low - 6;
        }

        // decision box
        $decision = $s->shownDecision();
        $label = self::DECISIONS[$decision] ?? '-';
        $color = match ($decision) {
            'approved' => self::COLORS['pass'], 'noted' => self::COLORS['warn'], default => self::COLORS['fail'],
        };
        $this->room(70);
        $this->y -= 4;
        $this->c->rect(self::M, $this->y - 40, self::W - 2 * self::M, 46, ['border' => $color, 'width' => 1.5]);
        $this->c->text($s->isIssued() ? 'ACTION' : 'SUGGESTED ACTION (AUTOMATED)', self::M + 12, $this->y - 10, 7.5, self::GREY, true);
        $this->c->text($label, self::M + 12, $this->y - 30, 15, $color, true);
        $st = $a['stats'] ?? [];
        $kept = $s->keptFindings();
        $count = fn ($k) => count(array_filter($kept, fn ($f) => $f['status'] === $k));
        $sum = sprintf('%d non-compliant · %d clarify · %d missing · %d form vs file · %d of %d checks pass', $count('fail'), $count('warn'), $count('missing'), $count('mismatch'), $st['pass'] ?? 0, $st['checks'] ?? 0);
        $sum = str_replace('·', '-', $sum);
        $this->c->text($sum, self::W - self::M - 12 - Canvas::width($sum, 8), $this->y - 28, 8, self::GREY);
        $this->y -= 62;

        // findings
        $this->heading('FINDINGS');
        if (! $kept) {
            $this->para('No findings: every check that could be applied complies.', self::M, 500, 9.5, self::COLORS['pass'], true);
        }
        foreach ($kept as $n => $f) {
            $this->room(40);
            $status = self::STATUS[$f['status']] ?? strtoupper($f['status']);
            $col = self::COLORS[$f['status']] ?? self::INK;
            $top = $this->y;
            $this->c->text(($n + 1) . '.', self::M, $this->y, 9.5, self::INK, true);
            $this->c->text($status, self::M + 18, $this->y, 7.5, $col, true);
            $this->y -= 11;
            $this->para($en($f['label']), self::M + 18, 120, 8.5, self::INK, true);
            if (! empty($f['clause'])) {
                $this->para($f['clause'], self::M + 18, 120, 7.5, self::GREY);
            }
            $low = $this->y;
            $this->y = $top;
            $this->para($en($f['comment']), self::M + 150, self::W - 2 * self::M - 150, 9);
            $this->y = min($low, $this->y) - 6;
            $this->c->line(self::M, $this->y + 3, self::W - self::M, $this->y + 3, self::LINE, 0.5);
            $this->y -= 6;
        }

        // checks
        $this->heading('CHECKS APPLIED');
        foreach ($a['checks'] ?? [] as $ch) {
            $this->room(14);
            $col = self::COLORS[$ch['status']] ?? self::INK;
            $this->c->text(self::STATUS[$ch['status']] ?? $ch['status'], self::M, $this->y, 7.5, $col, true);
            $this->c->text(self::latin($ch['id'] . '  ' . $en($ch['label'])), self::M + 85, $this->y, 8.5, self::INK);
            $cl = self::latin($ch['clause']);
            $this->c->text($cl, self::W - self::M - Canvas::width($cl, 7.5), $this->y, 7.5, self::GREY);
            $this->y -= 13;
        }

        // entered values
        $values = $a['values'] ?? $s->values ?? [];
        $this->heading('DATA ENTERED BY THE SUBMITTER');
        foreach ($this->def['sections'] as $sec) {
            $this->room(30);
            $this->c->text(strtoupper($en($sec['title'])), self::M, $this->y, 8.5, self::BRAND, true);
            $this->y -= 14;
            $fields = array_merge($sec['fields'], array_map(fn ($c) => $c + ['type' => 'number'], $this->def['computed'][$sec['key']] ?? []));
            $rows = ! empty($sec['repeat']) ? ($values[$sec['key']] ?? []) : [$values[$sec['key']] ?? []];
            $titleKey = $sec['repeat']['title'] ?? null;
            foreach ($rows as $row) {
                $parts = [];
                foreach ($fields as $f) {
                    if ($f['key'] === $titleKey) {
                        continue;
                    }
                    $parts[] = $en($f['label']) . ': ' . StudyTypes::display($f, $row[$f['key']] ?? null, 'en');
                }
                $this->room(24);
                if ($titleKey) {
                    $this->c->text(self::latin((string) ($row[$titleKey] ?? '')), self::M, $this->y, 9, self::INK, true);
                    $this->y -= 12;
                }
                $this->para(implode('   |   ', $parts), self::M + ($titleKey ? 10 : 0), self::W - 2 * self::M - ($titleKey ? 10 : 0), 8, self::INK);
                $this->y -= 5;
            }
        }
        if (! empty($this->def['tables']['note'])) {
            $this->para('Note: ' . $this->def['tables']['note'], self::M, self::W - 2 * self::M, 7.5, self::GREY);
        }

        // engineer
        $this->heading('ENGINEER');
        if ($s->remarks) {
            $this->para($s->remarks, self::M, self::W - 2 * self::M, 9.5);
            $this->y -= 6;
        }
        $this->room(50);
        $this->c->text('Reviewed by: ' . self::latin($s->engineer ?: '______________________'), self::M, $this->y, 9.5, self::INK);
        $this->c->text('Date: ' . ($s->issued_at?->format('d M Y') ?? '____________'), self::M + 300, $this->y, 9.5, self::INK);
        $this->y -= 26;
        $this->c->text('Signature: ______________________', self::M, $this->y, 9.5, self::INK);
        if ($s->isPdf()) {
            $this->y -= 26;
            $this->para('The supporting file submitted with this study follows this report.', self::M, 500, 8.5, self::GREY);
        }
    }

    private function write(): string
    {
        $src = $this->s->isPdf() ? Reader::open($this->s->filePath()) : new Reader(self::blankPdf());
        $w = new Writer($src);
        $fonts = [];
        foreach (Canvas::fontDicts() as $k => $d) {
            $fonts[$k] = $w->add($d);
        }
        $rootDict = new Dict;
        $newRoot = $w->add($rootDict);
        $kids = [];
        $total = count($this->pages);
        foreach ($this->pages as $i => $c) {
            $label = 'Page ' . ($i + 1) . ' of ' . $total . '  -  ' . $this->s->code;
            $c->text($label, self::W - self::M - Canvas::width($label, 7.5), 28, 7.5, self::GREY);
            $kids[] = $w->add(new Dict(['Type' => new Name('Page'), 'Parent' => $newRoot, 'MediaBox' => [0, 0, self::W, self::H],
                'Resources' => new Dict(['Font' => new Dict($fonts)]), 'Contents' => $w->stream($c->ops())]));
        }
        $count = $total;
        $cat = new Dict(['Type' => new Name('Catalog')]);
        if ($this->s->isPdf()) {
            $old = $src->root()->get('Pages');
            if ($old instanceof Ref) {
                $w->replace($old, $src->dict($old)->copy()->set('Parent', $newRoot));
                $kids[] = $old;
                $count += count($src->pages());
            }
        }
        $rootDict->e = ['Type' => new Name('Pages'), 'Kids' => $kids, 'Count' => $count];
        $cat->set('Pages', $newRoot);
        $info = $w->add(new Dict(['Title' => new Str('Study review ' . $this->s->code), 'Producer' => new Str('Study Review Assistant')]));
        $out = $this->s->reportPath();
        @mkdir(dirname($out), 0775, true);
        $w->write("$out.tmp", $w->add($cat), $info);
        rename("$out.tmp", $out);

        return $out;
    }

    /** An empty PDF to start the writer from when there is no supporting PDF. */
    private static function blankPdf(): string
    {
        $objs = ["<</Type /Catalog /Pages 2 0 R>>", '<</Type /Pages /Kids [] /Count 0>>'];
        $out = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $i => $o) {
            $off[] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $x = strlen($out);
        $out .= "xref\n0 3\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }

        return $out . "trailer\n<</Size 3 /Root 1 0 R>>\nstartxref\n$x\n%%EOF\n";
    }
}
