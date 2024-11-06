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
        'design_recipient_id',
        'deadline',
        'updated_by',
        'url_done',
        'count_product',
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
        return $this->belongsTo(User::class, 'designer_recipient_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
