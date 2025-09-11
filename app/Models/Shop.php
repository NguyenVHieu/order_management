<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'token',
        'created_by',
        'updated_by',
    ];

    public function seller()
    {
        return $this->belongsToMany(User::class, 'user_shops')
                    ->where('user_type_id', 1);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_shops');
    }
}
