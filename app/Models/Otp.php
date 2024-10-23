<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Otp extends Model
{
    protected $fillable = ['user_id', 'token', 'code', 'login_id', 'type', 'auth_type', 'ip', 'agent', 'used_at', 'expired'];

    public function scopeMinutesIsPassed(Builder $query, $subMinutes): void
    {
        $query->where('created_at', '>=', Carbon::now()->subMinutes($subMinutes)->toDateTimeString());
    }

    public function scopeNotExpiredToken(Builder $query, $token, int $minutesIsPassed = 5): void
    {
        $query
            ->where('token', $token)
            ->where('expired', false)
            ->minutesIsPassed($minutesIsPassed);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
