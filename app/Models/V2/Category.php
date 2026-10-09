<?php

namespace App\Models\V2;

use App\Models\User;
use App\Models\V2\Concerns\Translated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * A review category (or position): the engineers responsible for it, its specification document and the
 * criteria the check engine applies to files submitted under it.
 */
class Category extends Model
{
    use Translated;

    protected $table = 'v2_categories';

    protected $guarded = ['id'];

    protected $casts = ['rules' => 'array', 'study_types' => 'array', 'active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn (self $c) => File::deleteDirectory($c->dir()));
    }

    public function engineers()
    {
        return $this->belongsToMany(User::class, 'v2_category_user');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function dir(): string
    {
        return storage_path('app/v2/categories/' . $this->id);
    }

    /** The uploaded specification PDF (private; served to office users only). */
    public function specPath(): ?string
    {
        $f = $this->dir() . '/spec.pdf';

        return is_file($f) ? $f : null;
    }

    /** The criteria in the shape of the engine's rules.json (rules + spec title). */
    public function rulesConfig(): array
    {
        $base = json_decode((string) file_get_contents(resource_path('demo/rules.json')), true);
        $base['rules'] = $this->rules ?? [];
        $base['project']['specDocument'] = $this->spec_title ?: ($base['project']['specDocument'] ?? 'Project specification');

        return $base;
    }

    /**
     * The responsible engineer with the fewest open requests (submittals + studies), or null when the
     * category has no active engineer.
     */
    public function pickEngineer(): ?User
    {
        $open = fn (User $u) => Submission::where('assigned_to', $u->id)->whereIn('status', ['received', 'analysing', 'review'])->count()
            + Study::where('assigned_to', $u->id)->whereIn('status', ['submitted', 'review'])->count();

        return $this->engineers()->where('active', true)->get()->sortBy(fn ($u) => [$open($u), $u->id])->first();
    }

    /** Category for a form-based study type, if one lists it. */
    public static function forStudyType(string $type): ?self
    {
        return self::where('active', true)->get()->first(fn (self $c) => in_array($type, $c->study_types ?? [], true));
    }

    /** Engine attributes the rules can check (what the extractor reads from the data sheets). */
    public const ATTRIBUTES = [
        'ip' => ['Degree of protection (IP)', ['gte']],
        'form' => ['Form of separation', ['form']],
        'auxWire' => ['Auxiliary wiring size (mm²)', ['minAll']],
        'heater' => ['Anti-condensation heater + thermostat', ['present']],
        'consistency' => ['Cover sheet = data sheet = GA notes', ['consistent']],
        'documents' => ['Drawing set complete & correctly titled', ['noAnomaly']],
    ];
}
