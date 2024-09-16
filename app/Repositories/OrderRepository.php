<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{

    public function index($params, $columns = ['*'])
    {
        $query = Order::query()->select($columns)->distinct();

        $query->leftJoin('users', function($join) {  
            $join->on('users.shop_id', '=', 'orders.shop_id');
            $join->whereNull('users.deleted_at');
        });

        $query->leftJoin('shops', function($join) {  
            $join->on('shops.id', '=', 'orders.shop_id');
            $join->whereNull('shops.deleted_at');
        });

        if ($params['userType'] != -1) {
            if ($params['userType'] == 1) {
                $query->where('orders.is_approval', false);
            }else if ($params['userType'] == 2) {
                $query->where('orders.is_approval', true);
            }

            if (!empty($params['shopId'])) {
                $query->where('orders.shop_id', $params['shopId']);
            }
            $query->where('users.id',  $params['userId']);
        }
        $data = $query->orderBy('orders.id', 'desc')->get();
        return $data;
    }



    
}