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

        $query->leftJoin('users', function($join) {  
            $join->on('users.shop_id', '=', 'orders.shop_id');
            $join->whereNull('users.deleted_at');
        });

        $query->leftJoin('user_shops', function($join) {  
            $join->on('users.id', '=', 'user_shops.user_id');
        });

        if ($params['userType'] != -1) {
            if ($params['userType'] == 2) {
                $query->where('orders.is_approval', true);
            }

            if (!empty($params['shopId'])) {
                $query->where('orders.shop_id', $params['shopId']);
            }

            $query->where('users.id',  $params['userId']);
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

        $query2 = Order::query()->select($columns)->distinct()
                        ->where('orders.is_push', false);

        $query2->leftJoin('users', function($join) {  
            $join->on('users.shop_id', '=', 'orders.shop_id');
            $join->whereNull('users.deleted_at');
        });

        $query2->leftJoin('shops', function($join) {  
            $join->on('shops.id', '=', 'orders.shop_id');
            $join->whereNull('shops.deleted_at');
        });

        if ($params['userType'] != -1) {
            if ($params['userType'] == 2) {
                $query2->where('orders.is_approval', true);
            }

            if (!empty($params['shopId'])) {
                $query2->where('orders.shop_id', $params['shopId']);
            }

            $query2->where('users.id',  $params['userId']);
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