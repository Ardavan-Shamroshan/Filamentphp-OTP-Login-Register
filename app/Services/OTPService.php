<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OTPService
{
    public function allowRequestOTP(User $user): bool
    {
        if (! $lastOTP = $user->otps()->latest()->first()) {
            return true;
        }

        return $lastOTP->created_at->addSeconds(120)->isPast();
    }

    public function create(User $user, ?array $info = null): HasMany
    {
        $code  = rand(111111, 999999);
        $token = Str::random(60);
        $data  = $info ?? [
            'login_id'  => $user->mobile,
            'type'      => 'mobile',
            'auth_type' => $user->wasRecentlyCreated ? 'register' : 'login',
            'ip'        => request()->ip(),
            'agent'     => request()->userAgent(),
        ];

        $user->otps()->create([
            'code'  => $code,
            'token' => $token,
            ...$data
        ]);

        return $user->otps()->where('code', $code);
    }

    public function isValid(User $user, string $code, bool $markAsExpired = true): bool
    {
        $otp = $user->otps()->where('code', $code)->first();

        if (! $otp) {
            return false;
        }

        if ($otp->used_at) {
            return false;
        };

        if ($otp->created_at->addSeconds(120)->isPast()) {
            return false;
        }

        if ($markAsExpired) {
            $otp->update(['used_at' => now()]);
        }

        return true;
    }

    public function sendOTP($code, string|array $to, string|int $pattern = null)
    {
        // $pattern = $pattern ?? config('sms.drivers.melipayamakpattern.pattern');
        //
        // // No queue
        // return sms()
        //     ->via('melipayamakpattern')
        //     ->send("patterncode=$pattern \n arg1=$code",
        //         function ($sms) use ($to) {
        //             $sms->to($to);
        //         });
    }

    public function markCodeAsUsed(Otp $otp): bool
    {
        $otp->forceFill([
            'expired' => true,
            'used_at' => now()
        ]);

        $otp->save();

        $otp->user->forceFill([
            'mobile_verified_at' => $otp->user->mobile_verified_at ?? now()
        ]);

        $otp->user->save();

        return true;
    }

    public function markCodeAsExpired(Otp $otp): bool
    {
        $otp->update(['expired' => true]);
        return true;
    }
}
