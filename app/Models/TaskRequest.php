<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'request_from',
        'request_to',
        'approval',
        'approved_at',
        'approved_by',  
        'description',
        'score_request',
        'score_task',
        'score_approval',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function requestFrom()
    {
        return $this->belongsTo(User::class, 'request_from');
    }

    public function requestTo()
    {
        return $this->belongsTo(User::class, 'request_to');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

}
