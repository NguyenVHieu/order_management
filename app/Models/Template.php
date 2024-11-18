<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'product_task_id',
        'platform_size_task_id'
    ];

    public function product()
    {
        return $this->belongsTo(ProductTask::class, 'product_task_id');
    }

    public function platformSize()
    {
        return $this->belongsTo(PlatformSizeTask::class, 'platform_size_task_id');
    }
}
