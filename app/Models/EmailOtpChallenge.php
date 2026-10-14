<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $expires_at */
#[Fillable(['id', 'email', 'email_key', 'code_hash', 'session_hash', 'attempts', 'expires_at', 'consumed_at'])]
#[Hidden(['email', 'email_key', 'code_hash', 'session_hash'])]
class EmailOtpChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
    }
}
