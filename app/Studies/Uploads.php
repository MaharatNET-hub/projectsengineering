<?php

namespace App\Studies;

use App\Pdf\Reader;
use App\Review\Extractor;
use App\Review\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The supporting file is uploaded (in chunks, shared hosts cap request size) to a draft folder before
 * the form is sent, so it can also be read to pre-fill the form. Sending the form moves the draft into
 * the study's own folder.
 */
final class Uploads
{
    /** Accepted extensions and the bytes each file must start with. */
    public const MAGIC = [
        'pdf' => ['%PDF'], 'png' => ["\x89PNG"], 'jpg' => ["\xFF\xD8\xFF"], 'jpeg' => ["\xFF\xD8\xFF"],
        'xlsx' => ["PK\x03\x04"], 'docx' => ["PK\x03\x04"], 'zip' => ["PK\x03\x04"], 'xls' => ["\xD0\xCF\x11\xE0"], 'doc' => ["\xD0\xCF\x11\xE0"], 'dwg' => ['AC10'],
    ];

    public static function root(): string
    {
        return storage_path('app/studies/drafts');
    }

    public static function dir(string $token): string
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9]{32}$/', $token), 404);

        return self::root() . '/' . $token;
    }

    public static function ext(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public static function start(string $name, int $size): string
    {
        self::cleanup();
        $token = Str::random(32);
        File::ensureDirectoryExists(self::dir($token));
        file_put_contents(self::dir($token) . '/meta.json', json_encode(['name' => basename($name), 'size' => $size, 'ext' => self::ext($name), 'done' => false]));

        return $token;
    }

    public static function meta(string $token): ?array
    {
        $f = self::dir($token) . '/meta.json';

        return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    }

    private static function saveMeta(string $token, array $meta): void
    {
        file_put_contents(self::dir($token) . '/meta.json', json_encode($meta));
    }

    /**
     * Append one chunk; on the last one check the file and keep it as original.{ext}.
     *
     * @return array{received?:int,done?:bool,pages?:?int,error?:string}
     */
    public static function chunk(string $token, int $index, int $total, string $data, int $maxBytes): array
    {
        $meta = self::meta($token);
        if (! $meta || $meta['done']) {
            return ['error' => 'Upload not open'];
        }
        $part = self::dir($token) . '/upload.part';
        if ($index === 0) {
            $ok = false;
            foreach (self::MAGIC[$meta['ext']] ?? [] as $m) {
                $ok = $ok || str_starts_with($data, $m);
            }
            if (! $ok) {
                return ['error' => __('studies.err.file_type')];
            }
            file_put_contents($part, $data);
        } else {
            file_put_contents($part, $data, FILE_APPEND);
        }
        if (filesize($part) > $maxBytes) {
            @unlink($part);

            return ['error' => __('studies.err.file_size', ['mb' => (int) round($maxBytes / 1048576)])];
        }
        if ($index < $total - 1) {
            return ['received' => $index + 1];
        }
        $pages = null;
        if ($meta['ext'] === 'pdf') {
            try {
                $pages = count((new Reader((string) file_get_contents($part)))->pages());
            } catch (\Throwable $e) {
                @unlink($part);

                return ['error' => $e->getMessage()];
            }
        }
        rename($part, self::dir($token) . '/original.' . $meta['ext']);
        $meta['done'] = true;
        $meta['size'] = filesize(self::dir($token) . '/original.' . $meta['ext']);
        $meta['pages'] = $pages;
        self::saveMeta($token, $meta);

        return ['done' => true, 'pages' => $pages];
    }

    /**
     * Read the next batch of PDF pages with the submittal extractor; when finished return the panels
     * found, as form rows.
     */
    public static function extractStep(string $token, float $budget): array
    {
        $meta = self::meta($token);
        $dir = self::dir($token);
        if (! $meta || ! $meta['done'] || $meta['ext'] !== 'pdf') {
            return ['done' => true, 'rows' => []];
        }
        Store::using($dir, function () use ($dir) {
            if (! Store::read('job.json')) {
                Extractor::start("$dir/original.pdf", 'original.pdf');
            }
        });
        $r = Store::using($dir, fn () => Extractor::step($budget));
        if (! $r['done'] || ! empty($r['busy'])) {
            return ['done' => false, 'page' => $r['page'], 'total' => $r['total']];
        }
        $ex = json_decode((string) @file_get_contents("$dir/extracted.json"), true) ?: [];

        return ['done' => true, 'page' => $r['total'], 'total' => $r['total'], 'rows' => CrossCheck::prefill($ex)];
    }

    /** Move the draft into the study's folder. */
    public static function attach(string $token, string $target): ?array
    {
        $meta = self::meta($token);
        if (! $meta || ! $meta['done']) {
            return null;
        }
        File::ensureDirectoryExists(dirname($target));
        File::deleteDirectory($target);
        rename(self::dir($token), $target);
        // the extractor's redaction cache is not needed for studies
        File::deleteDirectory("$target/redact");

        return $meta;
    }

    /** Drafts older than a day were abandoned. */
    public static function cleanup(): void
    {
        foreach (glob(self::root() . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (filemtime($d) < time() - 86400) {
                File::deleteDirectory($d);
            }
        }
    }
}
