<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportResolved extends Notification implements ShouldQueue
{
    use Queueable;

    protected $report;

    /**
     * Create a new notification instance.
     */
    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $reportableType = class_basename($this->report->reportable_type);
        $status = $this->report->status;
        
        $statusText = match($status) {
            'resolved' => 'đã được xử lý',
            'rejected' => 'đã bị từ chối',
            default => 'đã được cập nhật',
        };
        
        return (new MailMessage)
            ->subject("Báo cáo của bạn {$statusText}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Báo cáo của bạn về {$reportableType} {$statusText}.")
            ->line("Lý do báo cáo: {$this->report->reason}")
            ->line("Ghi chú xử lý: {$this->report->resolution_note}")
            ->action('Xem chi tiết', url("/reports/{$this->report->id}"))
            ->line('Cảm ơn bạn đã giúp chúng tôi duy trì cộng đồng an toàn và thân thiện!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reportableType = class_basename($this->report->reportable_type);
        
        return [
            'report_id' => $this->report->id,
            'reportable_type' => $reportableType,
            'reportable_id' => $this->report->reportable_id,
            'status' => $this->report->status,
            'resolution_note' => $this->report->resolution_note,
            'resolved_at' => $this->report->resolved_at,
        ];
    }
}
