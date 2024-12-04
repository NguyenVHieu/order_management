<?php

namespace App\Repositories;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;


class OrderRepository implements OrderRepositoryInterface
{

    public function index($params, $columns = ['*'])
    {
        // Subquery để lấy distinct orders
        $query = Order::query()->select($columns)
            ->leftJoin('user_shops', function($join) {  
                $join->on('orders.shop_id', '=', 'user_shops.shop_id');
            })
            ->leftJoin('shops', function($join) {
                $join->on('shops.id', '=', 'user_shops.shop_id');
            })
            ->leftJoin('users', function($join){
                $join->on('orders.approval_by', '=', 'users.id');
            })
            ->leftJoin('categories', function($join){
                $join->on('orders.category_id', '=', 'categories.id');
            })
            ->when($params['userType'] != -1, function ($query) use ($params) {
                $query->where('user_shops.user_id', $params['userId']);
            })
            ->when(!empty($params['type']), function ($query) use ($params) {
                if ($params['type'] == 1) {
                    if (!empty($params['dateOrderFrom'] || $params['dateOrderTo'])) {
                        if (!empty($params['dateOrderFrom'])) {
                            $query->whereDate('orders.date_push', '>=', $params['dateOrderFrom']);
                        }
                        if (!empty($params['dateOrderTo'])) {
                            $query->whereDate('orders.date_push', '<=', $params['dateOrderTo']);
                        }
                    }
                    $query->where('orders.is_push', true);
                } else if ($params['type'] == 2) {
                    $query->where('orders.is_push', false)->where('is_approval', true);
                } else {
                    $query->where('orders.is_push', false)->where('is_approval', false);
                }
            })
            ->when(!empty($params['keyword']), function ($query) use ($params) {
                $query->where(function ($subQuery) use ($params) {
                    $subQuery->where('orders.product_name', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('orders.first_name', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('orders.order_number', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('orders.tracking_order', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('orders.last_name', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('orders.address', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('orders.personalization', 'like', '%' . $params['keyword'] . '%');
                });
            });
    
        // Truy vấn con để lấy DISTINCT các kết quả
        $distinctQuery = $query->distinct();
    
        // Lấy kết quả phân trang
        $results = $distinctQuery->paginate($params['per_page']);
    
        // Chuyển dữ liệu sang resource
        $orders = OrderResource::collection($results);
        $paginator = $orders->resource->toArray();
        $paginator['data'] = $paginator['data'] ?? [];
    
        // Tính toán tổng số kết quả
        $total = $query->distinct()->count('orders.id');
        $paginator['total'] = $total;
        $paginator['last_page'] = ceil($total / $params['per_page']);
    
        $data = [
            'orders' => $orders,
            'paginator' => $paginator,
        ];
    
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
        $start_date = Carbon::parse($params['start_date'])->startOfDay();
        $end_date = Carbon::parse($params['end_date'])->endOfDay(); 
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

    public function calCostOrder($params)
    {   
        $query = DB::table('orders')->selectRaw('SUM(orders.cost) AS total_cost')
                    ->leftJoin('users', 'users.id', '=', 'orders.approval_by')
                    ->leftJoin('shops', 'shops.id', '=', 'orders.shop_id')
                    ->leftJoin('teams', 'teams.id', '=', 'users.team_id');

        $query->where('is_push', true);
        $query->whereBetween('date_push', [$params['start_date'], $params['end_date']]);

        if (!empty($params['user_id'])) 
        {
            $query->where('orders.approval_by', $params['user_id']);
            $query->addSelect('users.name AS user_name', 'shops.name AS shop_name');
            $query->groupBy('users.name', 'shops.name');
        } 
        else if (!empty($params['shop_id'])) 
        {
            $query->where('orders.shop_id', $params['shop_id']);
            $query->addSelect('shops.name AS shop_name');
            $query->groupBy('shops.name');
        } 
        else if (!empty($params['team_id'])) 
        {
            $query->where('users.team_id', $params['team_id']);
            $query->addSelect('teams.name AS team_name')
            ->groupBy('teams.name');
        }

        return $query->get();
        
    }
}