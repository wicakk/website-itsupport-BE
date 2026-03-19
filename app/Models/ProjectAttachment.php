<?php
// app/Models/ProjectAttachment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectAttachment extends Model
{
    protected $fillable = ['project_id','uploaded_by','filename','path','mime_type','size'];
    protected $appends  = ['url','size_formatted'];

    public function project(): BelongsTo   { return $this->belongsTo(Project::class); }
    public function uploader(): BelongsTo  { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function getSizeFormattedAttribute(): string
    {
        $b = $this->size;
        if ($b < 1024)     return $b . ' B';
        if ($b < 1048576)  return round($b/1024,1) . ' KB';
        return round($b/1048576,1) . ' MB';
    }
}
