<?php

namespace App\Traits;

use App\Exceptions\BadRequestException;
use App\Notifications\OtpNotification;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

trait SendOtpTrait
{
    /**
     * Generate and store OTP for the user
     * 
     * @param int $length Length of the OTP
     * @param int $validity Validity time in minutes
     * @return string
     */
    public function generateOtp(int $length = 6, int $validity = 5): array
    {
        // Generate random OTP
        $otp = (string) random_int(pow(10, $length - 1), pow(10, $length) - 1);

        // Create cache key using user's ID
        $cacheKey = $this->getOtpKey();

        // Store OTP in cache with expiration
        Cache::put($cacheKey, [
            'otp' => $otp,
            'created_at' => now()
        ], Carbon::now()->addMinutes($validity));

        return ["otp" => $otp, "validity" => $validity];
    }

    /**
     * Verify the provided OTP
     * 
     * @param string $otp
     * @return bool
     */
    public function verifyOtp(string $otp): bool
    {
        $cacheKey = $this->getOtpKey();
        $storedData = Cache::get($cacheKey);

        if (!$storedData) {
            return false;
        }

        return $storedData['otp'] === $otp;
    }

    /**
     * Get stored OTP details
     * 
     * @return array|null
     */
    public function getStoredOtp(): ?array
    {
        return Cache::get($this->getOtpKey());
    }

    /**
     * Clear stored OTP
     * 
     * @return bool
     */
    public function clearOtp(): bool
    {
        return Cache::forget($this->getOtpKey());
    }

    /**
     * Get unique cache key for user's OTP
     * 
     * @return string
     */
    protected function getOtpKey(): string
    {
        return 'user_otp_' . $this->id;
    }

    /**
     * Send OTP via preferred channel (email/sms)
     * 
     * @param string|null $otp
     * @return bool
     */
    public function sendOtp(?string $otp = null): bool
    {
        $generateOtp = $this->generateOtp();
        $otp = $otp ?? $generateOtp['otp'];
        $validity = $generateOtp['validity'] ?? 5;

        // If user has phone number and SMS configuration exists, send via SMS
        if (isset($this->phone) && config('services.sms.enabled')) {
            return $this->sendOtpViaSms($otp, $validity);
        }

        // Default to email
        return $this->sendOtpViaEmail($otp, $validity);
    }

    /**
     * Send OTP via email
     * 
     * @param string $otp
     * @return bool
     */
    protected function sendOtpViaEmail(string $otp, int $validity): bool
    {
        try {
            // You can create a dedicated notification class for this
            $this->notify(new OtpNotification($otp, $validity));
            return true;
        } catch (\Exception $e) {
            throw new BadRequestException($e->getMessage(), Response::HTTP_BAD_REQUEST);
            return false;
        }
    }

    /**
     * Send OTP via SMS
     * 
     * @param string $otp
     * @return bool
     */
    protected function sendOtpViaSms(string $otp, int $validity): bool
    {
        try {
            // Implement your SMS sending logic here
            // You can use services like Twilio, Nexmo, etc.
            return true;
        } catch (\Exception $e) {
            throw new BadRequestException($e->getMessage(), Response::HTTP_BAD_REQUEST);
            return false;
        }
    }
}
