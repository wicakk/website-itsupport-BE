<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number', 'title', 'description', 'category', 'priority', 'status',
        'requester_id', 'assigned_to', 'department',
        'sla_deadline', 'resolved_at', 'closed_at',
        'resolution_time_minutes', 'sla_breached', 'resolution_notes', 'satisfaction_rating',
    ];

    protected $casts = [
        'sla_deadline'  => 'datetime',
        'resolved_at'   => 'datetime',
        'closed_at'     => 'datetime',
        'sla_breached'  => 'boolean',
    ];

    // ── Auto-generate ticket_number & SLA deadline ────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            // Generate ticket number: TKT-0001
            $last = static::withTrashed()->max('id') ?? 0;
            $ticket->ticket_number = 'TKT-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);

            // Set SLA deadline
            $hours = match($ticket->priority) {
                'Critical' => config('app.sla_critical_hours', 4),
                'High'     => config('app.sla_high_hours',     8),
                'Medium'   => config('app.sla_medium_hours',   24),
                default    => config('app.sla_low_hours',      72),
            };
            $ticket->sla_deadline = now()->addHours($hours);
        });

        // Check SLA breach on resolve
        static::updating(function (Ticket $ticket) {
            if ($ticket->isDirty('status') && $ticket->status === 'Resolved') {
                $ticket->resolved_at = now();
                $ticket->resolution_time_minutes = (int) $ticket->created_at->diffInMinutes(now());
                if ($ticket->sla_deadline && now()->gt($ticket->sla_deadline)) {
                    $ticket->sla_breached = true;
                }
            }
            if ($ticket->isDirty('status') && $ticket->status === 'Closed') {
                $ticket->closed_at = now();
            }
        });
    }

    // ── Scopes ───────────────────────────────────────────────────────────────
    public function scopeOpen($q)       { return $q->whereNotIn('status', ['Resolved','Closed']); }
    public function scopeOverdue($q)    { return $q->where('sla_deadline', '<', now())->whereNotIn('status', ['Resolved','Closed']); }
    public function scopeForUser($q, $user) {
        if ($user->isTechnician()) return $q;
        return $q->where('requester_id', $user->id);
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function requester()    { return $this->belongsTo(User::class, 'requester_id'); }
    public function assignee()     { return $this->belongsTo(User::class, 'assigned_to'); }
    public function comments()     { return $this->hasMany(TicketComment::class); }
    public function attachments()  { return $this->hasMany(TicketAttachment::class); }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function isOverdue(): bool {
        return $this->sla_deadline
            && now()->gt($this->sla_deadline)
            && !in_array($this->status, ['Resolved','Closed']);
    }

    public function getSlaStatusAttribute(): string {
        if (in_array($this->status, ['Resolved','Closed'])) {
            return $this->sla_breached ? 'Breached' : 'Met';
        }
        if ($this->sla_deadline && now()->gt($this->sla_deadline)) return 'Overdue';
        if ($this->sla_deadline && now()->addHour()->gt($this->sla_deadline)) return 'At Risk';
        return 'On Track';
    }

    public function hardwareAsset() 
    { 
        return $this->hasOne(TicketHardwareAsset::class); 
    }
}
