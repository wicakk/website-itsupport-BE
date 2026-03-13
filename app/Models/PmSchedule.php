<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmSchedule extends Model
{
    use HasFactory;

    // App\Models\PmSchedule.php
protected $fillable = ['asset_id', 'title', 'interval', 'next_date', 'notes', 'status', 'last_done'];

   public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
