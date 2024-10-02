<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;


class OrderRepository implements OrderRepositoryInterface
{

    public function index($params, $columns = ['*'])
    {
        $query = Order::query()->select($columns)->distinct();

        $query->join('user_shops', function($join) {  
            $join->on('orders.shop_id', '=', 'user_shops.shop_id');
        });

        $query->join('shops', function($join) {
            $join->on('shops.id', '=', 'user_shops.shop_id');
        });

        if ($params['userType'] != -1) {
            if ($params['userType'] == 2) {
                $query->where('orders.is_approval', true);
            }

            $query->where('user_shops.user_id',  $params['userId']);
        }

        if (!empty($params['dateOrderFrom'] || $params['dateOrderTo'])) {
            if (!empty($params['dateOrderFrom'])) {
                $query->whereDate('orders.recieved_mail_at', '>=', $params['dateOrderFrom'].' 00:00:00');
            }
    
            if (!empty($params['dateOrderTo'])) {
                $query->whereDate('orders.recieved_mail_at', '<=', $params['dateOrderTo'].' 23:59:59');
            }

            $query->where('orders.is_push', true);
        }

        $query2 = Order::query()->select($columns)->distinct()->where('is_push', false);

        $query2->join('user_shops', function($join) {  
            $join->on('orders.shop_id', '=', 'user_shops.shop_id');
        });

        $query2->join('shops', function($join) {
            $join->on('shops.id', '=', 'user_shops.shop_id');
        });

        if ($params['userType'] != -1) {
            if ($params['userType'] == 2) {
                $query2->where('orders.is_approval', true);
            }

            $query2->where('user_shops.user_id',  $params['userId']);
        }
        
        $data = $query->union($query2)
                   ->orderBy('id', 'desc')
                   ->get();

        return $data;
    }


    public function listOrder($order_number) 
    {
        return DB::table('orders')->where('order_number', $order_number)
                    ->where('is_push', false)
                    ->get();
    }
}