<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;


class OrderRepository implements OrderRepositoryInterface
{

    public function index($params)
    {
        $query = Order::query()->with([
            'approver:id,name',
            'category:id,name',
        ]);

        if ($params['userType'] != -1) {
            $query->whereHas('userShop', function ($subQuery) use ($params) {
                $subQuery->where('user_id', $params['userId']);
            });
        }

        if (!empty($params['type'])) {
            if ($params['type'] == 1) {
                if (!empty($params['dateOrderFrom'] || $params['dateOrderTo'])) {
                    if (!empty($params['dateOrderFrom'])) {
                        $query->whereDate('date_push', '>=', $params['dateOrderFrom']);
                    }
                    if (!empty($params['dateOrderTo'])) {
                        $query->whereDate('date_push', '<=', $params['dateOrderTo']);
                    }
                }
                $query->where('is_push', true);
            } elseif ($params['type'] == 2) {
                $query->where('is_push', false)->where('is_approval', true);
            } else {
                $query->where('is_push', false)->where('is_approval', false);
            }
        }

        if (!empty($params['keyword'])) {
            $query->where(function ($subQuery) use ($params) {
                $subQuery->where('product_name', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('first_name', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('order_number', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('tracking_order', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('last_name', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('address', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('personalization', 'like', '%' . $params['keyword'] . '%');
            });
        }

        $query->orderBy('recieved_mail_at', 'DESC');

        return $query->paginate($params['per_page']); // Hoặc paginate nếu cần
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
            DB::raw("DATE_FORMAT(date_push, '$format') AS time"),
            DB::raw("COUNT(DISTINCT CASE WHEN is_push = true THEN CONCAT(order_number_group, '-', place_order) END) AS amount_order_push"),
            DB::raw("SUM(CASE WHEN is_push = true THEN cost ELSE 0 END) AS total_cost")
        ];
        
        $query = Order::query()
            ->select($columns)
            ->whereBetween('date_push', [$start_date, $end_date])
            ->groupBy(DB::raw("DATE_FORMAT(date_push, '$format')")) 
            ->orderBy(DB::raw("DATE_FORMAT(date_push, '$format')"))
            ->get()
            ->keyBy('time')
            ->map(function ($row) {
                return [
                    'amount_order_push' => $row->amount_order_push,
                    'total_cost' => $row->total_cost,
                ];
            });
    
        return $query;
    }

    public function calCostOrder($params)
    {   
        $query = DB::table('orders')->selectRaw('SUM(orders.cost) AS total_cost')->selectRaw('COUNT(DISTINCT orders.order_number_group) AS total_order')
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