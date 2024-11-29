<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskDoneImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'image_url'
    ];

}
