<?php

namespace App\Studies;

/**
 * The table of a repeated section (panels, circuits, units) as a spreadsheet: a blank template to fill in
 * Excel (CSV, opens directly in Excel) and the import of a filled CSV or XLSX back into form rows.
 * Pure PHP: the XLSX (a zip of XML files) is read without the zip extension.
 */
final class Spreadsheet
{
    /** Blank template: header row "Label [key]" + a "#" row describing the allowed values. */
    public static function template(array $def, string $section, string $locale): string
    {
        $sec = StudyTypes::section($def, $section);
        $head = [];
        $hint = [];
        foreach ($sec['fields'] as $f) {
            $head[] = StudyTypes::t($f['label'], $locale) . (! empty($f['unit']) ? " ({$f['unit']})" : '') . " [{$f['key']}]";
            $hint[] = match ($f['type']) {
                'select' => implode(' / ', array_filter(array_map(fn ($o) => StudyTypes::t($o['label'], $locale), array_filter($f['options'], fn ($o) => (string) $o['value'] !== '')))),
                'bool' => $locale === 'ar' ? 'نعم / لا' : 'Yes / No',
                'number' => trim((isset($f['min'], $f['max']) ? "{$f['min']} - {$f['max']}" : '') . ' ' . ($f['unit'] ?? '')),
                default => $f['placeholder'] ?? '',
            } . (empty($f['required']) ? ($locale === 'ar' ? ' (اختياري)' : ' (optional)') : '');
        }
        $hint[0] = '# ' . $hint[0];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM: Excel shows Arabic correctly
        fputcsv($out, $head);
        fputcsv($out, $hint);
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /** @return list<list<string>> */
    public static function read(string $file, string $ext): array
    {
        $bytes = (string) file_get_contents($file);

        return $ext === 'xlsx' ? self::xlsx($bytes) : self::csv($bytes);
    }

    private static function csv(string $bytes): array
    {
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1256'); // Arabic Windows encoding
        }
        $first = strtok($bytes, "\n") ?: '';
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $sep = (string) array_key_first($counts);
        $h = fopen('php://temp', 'r+');
        fwrite($h, $bytes);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $sep)) !== false) {
            $rows[] = array_map(fn ($c) => trim((string) $c), $r);
        }

        return $rows;
    }

    /** Files inside a zip archive (stored or deflated), by name. */
    public static function unzip(string $zip): array
    {
        $eocd = strrpos($zip, "PK\x05\x06");
        if ($eocd === false) {
            throw new \RuntimeException('Not a valid XLSX file');
        }
        $e = unpack('vdisk/vcdDisk/ventriesHere/ventries/VcdSize/VcdOffset', substr($zip, $eocd + 4, 16));
        $pos = $e['cdOffset'];
        $files = [];
        for ($i = 0; $i < $e['entries']; $i++) {
            if (substr($zip, $pos, 4) !== "PK\x01\x02") {
                break;
            }
            $h = unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen/vdisk/viattr/Veattr/Voffset', substr($zip, $pos + 4, 42));
            $name = substr($zip, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
            $l = unpack('vnlen/velen', substr($zip, $h['offset'] + 26, 4));
            $data = substr($zip, $h['offset'] + 30 + $l['nlen'] + $l['elen'], $h['csize']);
            if ($h['size'] > 50 * 1048576) {
                continue; // not a table anyone fills by hand
            }
            $files[$name] = match ($h['method']) {
                0 => $data,
                8 => (string) @gzinflate($data),
                default => '',
            };
        }

        return $files;
    }

    private static function xlsx(string $bytes): array
    {
        $files = self::unzip($bytes);
        $shared = [];
        if (isset($files['xl/sharedStrings.xml'])) {
            $x = self::xml($files['xl/sharedStrings.xml']);
            foreach ($x->si as $si) {
                $shared[] = self::text($si);
            }
        }
        $sheet = $files['xl/worksheets/sheet1.xml'] ?? null;
        if ($sheet === null) {
            foreach ($files as $name => $data) {
                if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                    $sheet = $data;
                    break;
                }
            }
        }
        if ($sheet === null) {
            throw new \RuntimeException('No worksheet found in the XLSX file');
        }
        $rows = [];
        foreach (self::xml($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col = 0;
                foreach (str_split($m[1] ?? 'A') as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $t = (string) $c['t'];
                $v = match ($t) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => self::text($c->is),
                    'b' => ((string) $c->v) === '1' ? 'TRUE' : 'FALSE',
                    default => (string) $c->v,
                };
                $cells[$col - 1] = trim($v);
            }
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $rows[] = $line;
            }
        }

        return $rows;
    }

    private static function xml(string $s): \SimpleXMLElement
    {
        $x = @simplexml_load_string($s, \SimpleXMLElement::class, LIBXML_NONET);
        if (! $x) {
            throw new \RuntimeException('Unreadable XLSX file');
        }

        return $x;
    }

    /** All the text of a shared / inline string (plain or rich-text runs). */
    private static function text(\SimpleXMLElement $node): string
    {
        $out = '';
        foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $t) {
            $out .= (string) $t;
        }

        return $out;
    }

    /**
     * Map spreadsheet rows to form rows. Columns are recognised by "[key]" in the header, or by the
     * field label in either language; options by their value or label; yes/no in both languages.
     *
     * @return array{rows:list<array>,warnings:list<string>}
     */
    public static function toRows(array $def, string $section, array $table): array
    {
        $fields = StudyTypes::section($def, $section)['fields'];
        $norm = fn ($s) => mb_strtolower(trim(preg_replace('/\s*\(.*?\)\s*|\s*\[.*?\]\s*/u', ' ', (string) $s)));
        $map = null;
        $start = 0;
        foreach ($table as $i => $row) {
            $m = [];
            foreach ($row as $col => $cell) {
                foreach ($fields as $f) {
                    $byKey = preg_match('/\[([a-z0-9_]+)\]\s*$/', $cell, $k) && $k[1] === $f['key'];
                    $byLabel = $norm($cell) !== '' && in_array($norm($cell), [$norm(StudyTypes::t($f['label'], 'en')), $norm(StudyTypes::t($f['label'], 'ar')), $f['key']], true);
                    if ($byKey || $byLabel) {
                        $m[$col] = $f;
                    }
                }
            }
            if (count($m) >= 2) {
                $map = $m;
                $start = $i + 1;
                break;
            }
            if ($i > 10) {
                break;
            }
        }
        if (! $map) {
            return ['rows' => [], 'warnings' => [__('studies.excel.no_header')]];
        }
        $rows = [];
        $warnings = [];
        foreach (array_slice($table, $start) as $n => $row) {
            if (! array_filter($row, fn ($c) => $c !== '') || str_starts_with((string) ($row[0] ?? ''), '#')) {
                continue;
            }
            $out = [];
            foreach ($map as $col => $f) {
                $raw = trim((string) ($row[$col] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $out[$f['key']] = self::value($f, $raw, $ok);
                if (! $ok) {
                    $warnings[] = __('studies.excel.unknown', ['row' => $start + $n + 1, 'value' => $raw, 'field' => StudyTypes::t($f['label'])]);
                }
            }
            if ($out) {
                $rows[] = $out;
            }
        }

        return ['rows' => $rows, 'warnings' => array_slice($warnings, 0, 20)];
    }

    private static function value(array $f, string $raw, ?bool &$ok): mixed
    {
        $ok = true;
        $l = mb_strtolower($raw);
        if ($f['type'] === 'bool') {
            if (in_array($l, ['1', 'yes', 'y', 'true', 'نعم', 'x', '✓'], true)) {
                return true;
            }
            if (in_array($l, ['0', 'no', 'n', 'false', 'لا', '-'], true)) {
                return false;
            }
            $ok = false;

            return null;
        }
        if ($f['type'] === 'select') {
            $squash = fn ($s) => preg_replace('/[\s\-_,]+/u', '', mb_strtolower((string) $s));
            foreach ($f['options'] as $o) {
                if ((string) $o['value'] === $raw) {
                    return (string) $o['value'];
                }
            }
            foreach ($f['options'] as $o) {
                $cands = [(string) $o['value'], StudyTypes::t($o['label'], 'en'), StudyTypes::t($o['label'], 'ar')];
                foreach ($cands as $c) {
                    if ($c !== '' && $squash($c) === $squash($raw)) {
                        return (string) $o['value'];
                    }
                }
            }
            // numbers written differently ("2.50" for "2.5", "IP 54" for "54")
            $num = preg_replace('/[^\d.]/', '', $raw);
            foreach ($f['options'] as $o) {
                if ($num !== '' && is_numeric($num) && is_numeric($o['value']) && (float) $num === (float) $o['value']) {
                    return (string) $o['value'];
                }
            }
            $ok = false;

            return null;
        }

        return $raw;
    }
}
