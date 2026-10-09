<?php

namespace App\Models\V2;

use App\Studies\StudyTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** A study submitted through the per-type form: entered values + a supporting file. */
class Study extends Model
{
    protected $table = 'v2_studies';

    protected $guarded = ['id'];

    protected $casts = ['values' => 'array', 'analysis' => 'array', 'issued_at' => 'datetime', 'emailed_at' => 'datetime'];

    public const STATUSES = ['submitted', 'review', 'issued', 'archived'];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            do {
                $s->code = 'S' . strtoupper(Str::random(3) . '-' . Str::random(6));
            } while (self::where('code', $s->code)->exists());
        });
        static::deleting(fn (self $s) => File::deleteDirectory($s->dir()));
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** The revision that answers this one (Rev n+1), if any. */
    public function child()
    {
        return $this->hasOne(self::class, 'parent_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->latest('id');
    }

    /** "Rev 0", "Rev 1"… */
    public function revLabel(): string
    {
        return 'Rev ' . (int) $this->revision;
    }

    /** All revisions of this study, oldest first. */
    public function history(): array
    {
        $first = $this;
        for ($i = 0; $i < 50 && $first->parent; $i++) {
            $first = $first->parent;
        }
        $out = [$first];
        for ($i = 0; $i < 50 && ($next = end($out)->child); $i++) {
            $out[] = $next;
        }

        return $out;
    }

    /** The client may send a new revision once the review is issued and asks for changes. */
    public function canResubmit(): bool
    {
        return $this->isIssued() && in_array($this->decision, ['revise', 'rejected', 'noted'], true) && ! $this->child()->exists();
    }

    public function def(): ?array
    {
        return StudyTypes::find($this->type);
    }

    public function typeName(?string $locale = null): string
    {
        $d = $this->def();

        return $d ? StudyTypes::t($d['name'], $locale) : $this->type;
    }

    /** Private folder with the supporting file and what was read from it (never web-accessible). */
    public function dir(): string
    {
        return storage_path('app/studies/' . $this->id);
    }

    /** The supporting file (any accepted extension). */
    public function filePath(): ?string
    {
        foreach (glob($this->dir() . '/original.*') ?: [] as $f) {
            return $f;
        }

        return null;
    }

    public function isPdf(): bool
    {
        return ($f = $this->filePath()) && str_ends_with($f, '.pdf');
    }

    public function extracted(): ?array
    {
        $f = $this->dir() . '/extracted.json';

        return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    }

    public function reportPath(): string
    {
        return $this->dir() . '/report.pdf';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    /** Findings the engineer kept (all of them before review). */
    public function keptFindings(): array
    {
        return array_values(array_filter($this->analysis['findings'] ?? [], fn ($f) => $f['include'] ?? true));
    }

    /** The decision shown to the client: the engineer's once issued, otherwise the suggested one. */
    public function shownDecision(): ?string
    {
        return $this->decision ?: ($this->analysis['suggested'] ?? null);
    }

    public function sizeLabel(): string
    {
        $b = (int) $this->file_size;

        return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'issued' => 'ok', 'review' => 'accent', 'submitted' => 'warn', default => 'mute',
        };
    }
}
