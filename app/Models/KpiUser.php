<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_month',
        'user_id',
        'kpi',
    ];

}
