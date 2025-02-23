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
        'cost'
    ];

    protected $casts = [
        'is_shipping' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function userShop()
    {
        return $this->belongsTo(UserShop::class, 'shop_id', 'shop_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approval_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
