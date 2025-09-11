<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Auth;
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
                $query->where('is_push', false)->where('is_approval', false)->where('recieved_mail_at', '>=', '2024-12-31 23:59:59');
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

        $userId = Auth::user()->id;
        $shopIds = User::find($userId)->shops()->pluck('shops.id')->toArray();
        $userType = Auth::user()->user_type_id ?? null;
        $columns = [
            DB::raw("DATE_FORMAT(date_push, '$format') AS time"),
            DB::raw("COUNT(DISTINCT CASE WHEN is_push = true THEN CONCAT(order_number_group, '-', place_order) END) AS amount_order_push"),
            DB::raw("SUM(CASE WHEN is_push = true THEN cost ELSE 0 END) AS total_cost")
        ];
        
        $query = Order::query()
            ->select($columns)
            ->whereBetween('date_push', [$start_date, $end_date])
            ->when($userType !== null, function ($q) use ($shopIds) {
                $q->whereIn('shop_id', $shopIds);
            })
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
            $teamId = Auth::user()->team_id ?? null;
            $userType = Auth::user()->user_type_id; 
            $main = DB::table('users')
                    ->leftJoin('orders', function ($join) use ($params) {
                        $join->on('users.id', '=', 'orders.approval_by')
                            ->where('orders.is_push', true)
                            ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                    })
                    ->join('shops', 'orders.shop_id', '=', 'shops.id')
                    ->whereIn('users.user_type_id', [1, 3])
                    ->where('users.id', '!=', 11)
                    ->select('users.id', 'users.name AS user_name', 'shops.id AS shop_id', 'shops.name AS shop_name')
                    ->selectRaw('COALESCE(SUM(orders.cost), 0) AS total_cost')
                    ->selectRaw('COALESCE(COUNT(DISTINCT orders.order_number_group)) AS total_order')
                    ->selectRaw('CAST(COALESCE(SUM(orders.quantity), 0) AS SIGNED) AS item_orders')
                    ->groupBy('users.id', 'users.name', 'shops.id', 'shops.name')
                    ->orderBy('users.id');

                if (!empty($teamId) && !empty($userType)) {
                    $main->where('users.team_id', $teamId);
                }

                $union = DB::table('users')
                    ->leftJoin('user_shops', 'users.id', '=', 'user_shops.user_id')
                    ->leftJoin('shops', 'user_shops.shop_id', '=', 'shops.id')
                    ->select('users.id', 'users.name AS user_name', 'shops.id AS shop_id', 'shops.name AS shop_name')
                    ->selectRaw('0 AS total_cost')
                    ->selectRaw('0 AS total_order')
                    ->selectRaw('0 AS item_orders')
                    ->whereIn('users.user_type_id', [1, 3])
                    ->where('users.id', '!=', 11)
                    ->whereNotExists(function ($query) use ($params) {
                        $query->select(DB::raw(1))
                            ->from('orders')
                            ->whereRaw('orders.approval_by = users.id')
                            ->where('orders.is_push', true)
                            ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                    });

                if (!empty($teamId) && !empty($userType)) {
                    $union->where('users.team_id', $teamId);
                }

                $query = $main->union($union);

        } 
        else if ($type === 'shop') 
        {
            $userId = Auth::user()->id;
            $userType = Auth::user()->user_type_id ?? null;
            $shopIds = User::where('id', $userId)->first()->shops()->pluck('shops.id')->toArray() ?? [];
            $main = DB::table('shops')
                ->leftJoin('orders', function ($join) use ($params) {
                    $join->on('shops.id', '=', 'orders.shop_id')
                        ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                })
                ->select('shops.id', 'shops.name AS shop_name')
                ->selectRaw('COALESCE(SUM(orders.cost), 0) AS total_cost')
                ->selectRaw('COALESCE(COUNT(DISTINCT orders.order_number_group)) AS total_order')
                ->selectRaw('CAST(COALESCE(SUM(orders.quantity), 0) AS SIGNED) AS item_orders')
                ->groupBy('shops.id', 'shops.name');

            if (!empty($shopIds) && !empty($userType)) {
                $main->whereIn('shops.id', $shopIds);
            }

            $union = DB::table('shops')
                ->select('shops.id', 'shops.name AS shop_name')
                ->selectRaw('0 AS total_cost')
                ->selectRaw('0 AS total_order')
                ->selectRaw('0 AS item_orders')
                ->whereNotExists(function ($query) use ($params) {
                    $query->select(DB::raw(1))
                        ->from('orders')
                        ->whereRaw('orders.shop_id = shops.id')
                        ->whereBetween('orders.date_push', [$params['start_date'].' 00:00:00', $params['end_date'].' 23:59:59']);
                });

            if (!empty($shopIds) && !empty($userType)) {
                $union->whereIn('shops.id', $shopIds);
            }

            $query = $main->union($union);

        } else {
            $teamId = Auth::user()->team_id ?? null;

            $main = DB::table('teams')
                ->whereNotIn('teams.id', [7, 8])
                ->leftJoin('users', 'users.team_id', '=', 'teams.id')
                ->leftJoin('orders', function ($join) use ($params) {
                    $join->on('users.id', '=', 'orders.approval_by')
                        ->where('orders.is_push', true)
                        ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                })
                ->select('teams.id', 'teams.name AS team_name')
                ->selectRaw('COALESCE(SUM(orders.cost), 0) AS total_cost')
                ->selectRaw('CAST(COALESCE(SUM(orders.quantity), 0) AS SIGNED) AS item_orders')
                ->selectRaw('COALESCE(COUNT(DISTINCT orders.order_number_group), 0) AS total_order')
                ->groupBy('teams.id', 'teams.name');

            if (!empty($teamId) && !empty($userType)) {
                $main->where('teams.id', $teamId);
            }

            $union = DB::table('teams')
                ->whereNotIn('teams.id', [7, 8])
                ->select('teams.id', 'teams.name AS team_name')
                ->selectRaw('0 AS total_cost')
                ->selectRaw('0 AS item_orders')
                ->selectRaw('0 AS total_order')
                ->whereNotExists(function ($query) use ($params) {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereRaw('users.team_id = teams.id')
                        ->whereExists(function ($sub) use ($params) {
                            $sub->select(DB::raw(1))
                                ->from('orders')
                                ->whereRaw('orders.approval_by = users.id')
                                ->where('orders.is_push', true)
                                ->whereBetween('orders.date_push', [$params['start_date'], $params['end_date']]);
                        });
                });

            if (!empty($teamId) && !empty($userType)) {
                $union->where('teams.id', $teamId);
            }

            $query = $main->union($union);
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

        $userId = Auth::user()->id;
        $shopIds = User::find($userId)->shops()->pluck('shops.id')->toArray();
        $userType = Auth::user()->user_type_id ?? null;
        
        $query = Order::query()
            ->select($columns)
            ->whereBetween('recieved_mail_at', [$start_date, $end_date])
            ->when($userType !== null, function ($q) use ($shopIds) {
                $q->whereIn('shop_id', $shopIds);
            })
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

    public function calOrderInTime($params) 
    {
        $userId = Auth::user()->id;
        $shopIds = User::find($userId)->shops()->pluck('shops.id')->toArray();
        $userType = Auth::user()->user_type_id ?? null;

        $start_date = Carbon::parse($params['start_date'])->startOfDay();
        $end_date = Carbon::parse($params['end_date'])->endOfDay(); 
        $result = Order::whereBetween('recieved_mail_at', [$start_date, $end_date])
            ->select(DB::raw("COUNT(DISTINCT order_number_group) AS total_order"))
            ->when($userType !== null, function ($q) use ($shopIds) {
                $q->whereIn('shop_id', $shopIds);
            })
            ->first();
        return $result ? $result->total_order : 0;
    }

    public function countOrderByTime($params) 
    {
        $start_date = Carbon::parse($params['start_date'])->startOfDay();
        $end_date = Carbon::parse($params['end_date'])->endOfDay();
        return Order::whereBetween('recieved_mail_at', [$start_date, $end_date])
            ->sum('quantity');
    }   
}