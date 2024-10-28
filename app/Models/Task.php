<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status_id',
        'created_by',
        'category_design_id',
        'designer_id',
        'deadline',
        'updated_by',
        'url_done',
        'level_task',
    ];
}
