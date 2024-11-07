<?php

namespace App\Traits;

use App\Exceptions\BadRequestException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait VerificationTrait
{
    public function generateToken($type, $type_id, $duration)
    {
        $token = random_int(10000, 99999);

        DB::table('verification_tokens')->insert([
            'tokenable_type' => $type,
            'tokenable_id' => $type_id,
            'token' => $token,
            'expires_at' => now()->addMinutes($duration)
        ]);

        $verification = $this->findToken($type, $token, $type_id);
        return $verification->token;
    }

    public function verify($type, $token, $type_id)
    {
        $verification = $this->findToken($type, $token, $type_id);

        if (!$verification) {
            throw new BadRequestException('Invalid token', 400);
        }

        if (
            Carbon::parse($verification->expires_at)->lt(now())
        ) {
            $this->updateToken($token);
            throw new BadRequestException('Token has expired', 400);
        }

        $this->updateToken($token);
    }

    protected function updateToken(string $token)
    {
        DB::table('verification_tokens')
            ->where('tokenable_type', 'App\Models\User')
            ->where('token', $token)
            ->latest()
            ->update([
                'last_used_at' => now()
            ]);
    }

    protected function findToken(string $type, string $token, $type_id = null)
    {
        return DB::table('verification_tokens')
            ->where('tokenable_type', $type)
            ->where('token', $token)
            ->when($type_id, function ($query) use ($type_id) {
                $query->where('tokenable_id', $type_id);
            })
            ->latest()
            ->first();
    }
}
