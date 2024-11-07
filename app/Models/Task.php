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
        'created_by',
        'created_at'
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
        return $this->belongsTo(User::class, 'design_recipient_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories()
    {
        return $this->hasMany(TaskHistory::class, 'task_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'category_design_id');
    }

    public function category()
    {
        return $this->belongsTo(CategoryDesign::class, 'category_design_id');   
    }
}
