<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSizeTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'product_task_id'
    ];

}
