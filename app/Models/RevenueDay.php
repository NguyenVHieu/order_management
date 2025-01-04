<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'date',
        'revenue',
        'created_by',
        'updated_by',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
