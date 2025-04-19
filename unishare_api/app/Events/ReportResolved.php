<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $report;

    /**
     * Create a new event instance.
     */
    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->report->user_id),
            new PrivateChannel('moderators'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        $reportableType = class_basename($this->report->reportable_type);
        
        return [
            'id' => $this->report->id,
            'user_id' => $this->report->user_id,
            'reportable_type' => $reportableType,
            'reportable_id' => $this->report->reportable_id,
            'status' => $this->report->status,
            'resolved_by' => $this->report->resolved_by,
            'resolver_name' => $this->report->resolver ? $this->report->resolver->name : null,
            'resolution_note' => $this->report->resolution_note,
            'resolved_at' => $this->report->resolved_at ? $this->report->resolved_at->toIso8601String() : null,
        ];
    }
}
