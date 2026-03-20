<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    protected $fillable = ['ticket_id', 'comment_id', 'user_id', 'filename', 'original_name', 'mime_type', 'file_size', 'path'];

    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function user()   { return $this->belongsTo(User::class); }

    public function getUrlAttribute(): string {
        return asset('storage/' . $this->path);
    }
}
