<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Shop;
use App\Models\Team;
use App\Models\UserType;
use App\Repositories\OrderRepository;
use Exception;

class ChartController extends BaseController
{
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {  
        $this->orderRepository = $orderRepository;
    }

    public function filterOrderByTime(Request $request)
    {
        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $request->start_date);
            $end = \Carbon\Carbon::createFromFormat('Y-m-d', $request->end_date);
        
            $dates = [];
        
            switch ($request->type) {
                case 'date':
                    
                    while ($start <= $end) {
                        $dates[] = $start->format('Y-m-d'); 
                        $start->addDay();
                    }
                    break;
        
                case 'month':
                    
                    while ($start <= $end) {
                        $dates[] = $start->format('Y-m');
                        $start->addMonth(); 
                    }
                    break;
        
                case 'year':
                    while ($start <= $end) {
                        $dates[] = $start->format('Y'); 
                        $start->addYear();
                    }
                    break;
        
                default:
                    throw new Exception('Invalid type provided.');
            }
        
            $results = [];
            $total_cost = 0.00;
            $total_order_push = 0;

            $data = $this->orderRepository->filterOrderByTime($request->all());
            foreach ($dates as $date) {
                
                $results['cost'][] = isset($data[$date]['total_cost']) ? (float) $data[$date]['total_cost'] : 0.00;
            }
            if ($data) {
                foreach($data as $value) {
                    $total_cost += $value["total_cost"];
                    $total_order_push += $value["amount_order_push"];
                }
            }

            $orders = DB::table('orders')
                ->select(DB::raw("COUNT(DISTINCT CONCAT(order_number_group, '-', is_push, IF(is_push = 1, place_order, ''))) AS total_order"),
                DB::raw("COUNT(DISTINCT CASE WHEN is_push = false THEN order_number_group END) AS amount_order_not_push"))
                ->first();
            
            // $results['orders'][] = $orders->total_order ?? 0;
            $results['labels'] = $dates;
            $results['total_order'] = $orders->total_order ?? 0;
            $results['total_cost'] = $total_cost ?? 0;
            $results['total_order_push'] = $total_order_push ?? 0;
            $results['total_order_not_push'] = $orders->amount_order_not_push ?? 0;

            return $this->sendSuccess($results);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function calCostOrder(Request $request)
    {
        try {
            $data = $this->orderRepository->calCostOrder($request->all());
            if (!empty($request->user_id)) {
                $total_cost = 0;
                if (count($data) > 0) {
                    foreach ($data as $order) {
                        $total_cost += $order->total_cost;
                        $name = $order->user_name;
                        $order->total_cost = (float)$order->total_cost;
                    }

                    $res = [
                        'user_name' => $name,
                        'total_cost' => (float)$total_cost,
                        'value' => $data
                    ];
                    return $this->sendSuccess($res);
                }
                
            }
            return $this->sendSuccess($data);

        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function initData() 
    {
        try {
            $teams = DB::table('teams')->select(['id as value', 'name as label'])->get();
            $shops = DB::table('shops')->select(['id as value', 'name as label'])->get();
            $users = DB::table('users')->where('is_admin', false)->where('user_type_id', 1)->select(['id as value', 'name as label'])->get();
            $data = [
                'users' => $users,
                'shops' => $shops,
                'teams' => $teams
            ];
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }
}