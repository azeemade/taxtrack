<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpNotification extends Notification
{
    use Queueable;
    protected $otp;
    protected $duration;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, int $duration)
    {
        $this->otp = $otp;
        $this->duration = $duration;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your One-Time Password (OTP)')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have requested a one-time password (OTP).')
            ->line('Your OTP is: ' . $this->otp)
            ->line('This OTP will expire in ' . $this->duration . ' minutes.')
            ->line('If you did not request this OTP, please ignore this email.')
            ->line('For security reasons, please do not share this OTP with anyone.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp' => $this->otp,
            'type' => 'otp_notification',
            'message' => 'OTP sent successfully'
        ];
    }
}
