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

}
