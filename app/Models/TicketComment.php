<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class TicketComment extends Model
{
    use SoftDeletes;
    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_internal'];
    protected $casts    = ['is_internal' => 'boolean'];
    public function ticket()      { return $this->belongsTo(Ticket::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function attachments() { return $this->hasMany(TicketAttachment::class, 'comment_id'); }
}
