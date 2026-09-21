<?php

namespace App\Notifications\Meetings;

use App\Models\OMS\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MinutesPublished extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Meeting $meeting)
    {
        //
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
            ->subject(__('Minutes published for :title', ['title' => $this->meeting->title]))
            ->line(__('Minutes and decisions for ":title" have been published.', ['title' => $this->meeting->title]))
            ->when($this->meeting->decisions !== null, fn (MailMessage $mail) => $mail->line(__('Decisions: :decisions', ['decisions' => $this->meeting->decisions])))
            ->action(__('View meeting'), route('meetings.show', [
                'current_team' => $this->meeting->team->slug,
                'meeting' => $this->meeting,
            ]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
        ];
    }
}
