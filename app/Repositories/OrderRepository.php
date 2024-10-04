<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use GuzzleHttp\Psr7\Request;
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

    public function filterOrderByTime($params) 
    {
        $start_date = $params['start_date'];
        $end_date = $params['end_date'];
        $type = $params['type'];

        if ($type === 'date') {
            $format = "%Y-%m-%d";
        } else if ($type === 'month') {
            $format = "%Y-%m";
        } else if ($type === 'year') {
            $format = "%Y";
        }

        $columns = [
            DB::raw("DATE_FORMAT(recieved_mail_at, '$format') AS time"),
            DB::raw("COUNT(DISTINCT CONCAT(order_number_group, '-', is_push, IF(is_push = 1, place_order, ''))) AS amount_order"),
            DB::raw("COUNT(DISTINCT CASE WHEN is_push = true THEN CONCAT(order_number_group, '-', place_order) END) AS amount_order_push"),
            DB::raw("COUNT(DISTINCT CASE WHEN is_push = false THEN order_number_group END) AS amount_order_not_push"),
            DB::raw("SUM(CASE WHEN is_push = true THEN cost ELSE 0 END) AS total_cost")
        ];
        
        $query = DB::table('orders')
            ->select($columns)
            ->whereBetween('recieved_mail_at', [$start_date, $end_date])
            ->groupBy(DB::raw("DATE_FORMAT(recieved_mail_at, '$format')")) 
            ->orderBy(DB::raw("DATE_FORMAT(recieved_mail_at, '$format')"))
            ->get()
            ->keyBy('time')
            ->map(function ($row) {
                return [
                    'amount_order' => $row->amount_order,
                    'amount_order_push' => $row->amount_order_push,
                    'amount_order_not_push' => $row->amount_order_not_push,
                    'total_cost' => $row->total_cost,
                ];
            });
    
        return $query;
    }
}