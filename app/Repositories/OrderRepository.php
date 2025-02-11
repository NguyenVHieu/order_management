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
        $type = $params['type'] ?? 'seller';

        if ($type === 'seller') {
            $query = DB::table('users')
                ->leftJoin('orders', function ($join) use ($params) {
                    $join->on('users.id', '=', 'orders.approval_by')
                        ->where('orders.is_push', true)
                        ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                })
                ->join('shops', 'orders.shop_id', '=', 'shops.id')
                ->where('users.user_type_id', 1)
                ->select('users.id', 'users.name AS user_name', 'shops.id AS shop_id', 'shops.name AS shop_name')
                ->selectRaw('COALESCE(SUM(orders.cost), 0) AS total_cost')
                ->selectRaw('COALESCE(COUNT(DISTINCT orders.order_number_group)) AS total_order')
                ->groupBy('users.id', 'users.name', 'shops.id', 'shops.name')
                ->orderBy('users.id')
                ->union(
                    DB::table('users')
                        ->leftJoin('user_shops', 'users.id', '=', 'user_shops.user_id')
                        ->leftJoin('shops', 'user_shops.shop_id', '=', 'shops.id')
                        ->select('users.id', 'users.name AS user_name', 'shops.id AS shop_id', 'shops.name AS shop_name')
                        ->selectRaw('0 AS total_cost')
                        ->selectRaw('0 AS total_order')
                        ->where('users.user_type_id', 1)
                        ->whereNotExists(function ($query) use ($params) {
                            $query->select(DB::raw(1))
                                ->from('orders')
                                ->whereRaw('orders.approval_by = users.id')
                                ->where('orders.is_push', true)
                                ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                        })
                );

        } 
        else if ($type === 'shop') 
        {
            $query = DB::table('shops')
                ->leftJoin('orders', function ($join) use ($params) {
                    $join->on('shops.id', '=', 'orders.shop_id')
                        ->where('orders.is_push', true)
                        ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                })
                ->select('shops.id', 'shops.name AS shop_name')
                ->selectRaw('COALESCE(SUM(orders.cost), 0) AS total_cost')
                ->selectRaw('COALESCE(COUNT(DISTINCT orders.order_number_group)) AS total_order')
                ->groupBy('shops.id', 'shops.name')
                ->unionAll(
                    DB::table('shops')
                        ->select('shops.id', 'shops.name AS shop_name')
                        ->selectRaw('0 AS total_cost')
                        ->selectRaw('0 AS total_order')
                        ->whereNotExists(function ($query) use ($params) {
                            $query->select(DB::raw(1))
                                ->from('orders')
                                ->whereRaw('orders.shop_id = shops.id')
                                ->where('orders.is_push', true)
                                ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                        })
                );
        } else {
            $query = DB::table('teams')
                ->leftJoin('users', 'users.team_id', '=', 'teams.id')
                ->leftJoin('orders', function ($join) use ($params) {
                    $join->on('users.id', '=', 'orders.approval_by')
                        ->where('orders.is_push', true)
                        ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                })
                ->select('teams.id', 'teams.name AS team_name')
                ->selectRaw('COALESCE(SUM(orders.cost), 0) AS total_cost')
                ->selectRaw('COALESCE(COUNT(DISTINCT orders.order_number_group)) AS total_order')
                ->groupBy('teams.id', 'teams.name')
                ->unionAll(
                    DB::table('teams')
                        ->select('teams.id', 'teams.name AS team_name')
                        ->selectRaw('0 AS total_cost')
                        ->selectRaw('0 AS total_order')
                        ->whereNotExists(function ($query) use ($params) {
                            $query->select(DB::raw(1))
                                ->from('users')
                                ->whereRaw('users.team_id = teams.id')
                                ->whereNotExists(function ($query) use ($params) {
                                    $query->select(DB::raw(1))
                                        ->from('orders')
                                        ->whereRaw('orders.approval_by = users.id')
                                        ->where('orders.is_push', true)
                                        ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                                });
                        })
                );
        }

        return $query->get();
    }


    public function calOrderByTime($params) 
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
            DB::raw("COUNT(DISTINCT order_number_group) AS total_order"),
        ];
        
        $query = Order::query()
            ->select($columns)
            ->whereBetween('recieved_mail_at', [$start_date, $end_date])
            ->groupBy(DB::raw("DATE_FORMAT(recieved_mail_at, '$format')")) 
            ->orderBy(DB::raw("DATE_FORMAT(recieved_mail_at, '$format')"))
            ->get()
            ->keyBy('time')
            ->map(function ($row) {
                return [
                    'total_order' => $row->total_order,
                ];
            });
    
        return $query;
    }
}