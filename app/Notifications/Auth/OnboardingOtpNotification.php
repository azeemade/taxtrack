<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OnboardingOtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
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
        $appName = Str::title(config('app.name'));
        $data = $this->data;

        return (new MailMessage)
            ->subject('Your ' . $appName . ' Onboarding OTP')
            ->greeting('Hello ' . $data['name'] . ',')
            ->line('Please enter the code below to continue with your onboarding.')
            ->line('**' . $data['token'] . '**')
            ->line('This OTP is valid for 15 minutes.')
            ->line('If you did not request this OTP, please ignore this email.')
            ->salutation('Regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
