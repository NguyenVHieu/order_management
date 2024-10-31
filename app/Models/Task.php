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
        'designer_tag',
        'deadline',
        'updated_by',
        'url_done',
        'level_task',
        'is_done',
        'done_at',
    ];

    public function status()
    {
        return $this->belongsTo(StatusTask::class, 'status_id');
    }

    public function images()
    {
        return $this->hasMany(TaskImage::class, 'task_id');
    }

    public function designer()
    {
        return $this->belongsTo(User::class, 'designer_tag');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
