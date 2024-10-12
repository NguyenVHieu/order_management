<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'price',
        'shop_id',
        'size',
        'color',
        'personalization',
        'variant_id',
        'print_provider_id',
        'blueprint_id',
        'thumbnail',
        'sku',
        'quantity',
        'first_name',
        'last_name',
        'email',
        'phone',
        'zip',
        'country',
        'state',
        'city',
        'apartment',
        'address',
        'item_total',
        'discount',
        'sub_total',
        'shipping',
        'sale_tax',
        'order_total',
        'user_id',
        'is_push',
        'is_approval',
        'created_by',
        'updated_by',
    ];
}
