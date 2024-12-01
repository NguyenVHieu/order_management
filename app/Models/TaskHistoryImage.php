<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskHistoryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_history_id',
        'image_url'
    ];

}
