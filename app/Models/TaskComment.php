<?php
// app/Models/TaskComment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['task_id','user_id','body'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'comment_id');
    }
}
