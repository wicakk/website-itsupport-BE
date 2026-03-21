<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'building',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scope untuk hanya ambil yang aktif (dipakai di dropdown form)
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
