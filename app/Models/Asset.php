<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_number','name','category','brand','model',
        'serial_number','status','assigned_to','location',
        'purchase_date','purchase_price','warranty_expiry','notes','specs',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'warranty_expiry' => 'date',
        'purchase_price'  => 'decimal:2',
        'specs'           => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            $last = static::withTrashed()->max('id') ?? 0;
            $asset->asset_number = 'AST-' . str_pad($last + 1, 3, '0', STR_PAD_LEFT);
        });
    }

    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }

    public function isWarrantyExpired(): bool {
        return $this->warranty_expiry && $this->warranty_expiry->isPast();
    }

    public function isWarrantyExpiringSoon(): bool {
        return $this->warranty_expiry
            && !$this->warranty_expiry->isPast()
            && $this->warranty_expiry->lt(now()->addDays(30));
    }

    public function pmSchedules()
    {
        return $this->hasMany(PmSchedule::class);
    }
}
