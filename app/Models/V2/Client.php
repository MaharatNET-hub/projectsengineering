<?php

namespace App\Models\V2;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A client account: sees its studies in one place and starts new ones with its details filled in. */
class Client extends Authenticatable
{
    protected $table = 'v2_clients';

    protected $fillable = ['name', 'company', 'email', 'phone', 'password', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function studies()
    {
        return $this->hasMany(Study::class)->latest();
    }
}
