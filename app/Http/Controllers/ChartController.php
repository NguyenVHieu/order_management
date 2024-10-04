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
            $total_order = 0;
            $total_cost = 0.00;
            $total_order_push = 0;
            $total_order_not_push = 0;

            $data = $this->orderRepository->filterOrderByTime($request->all());
            foreach ($dates as $date) {
                $results['orders'][] = $data[$date]['amount_order'] ?? 0;
                $results['cost'][] = isset($data[$date]['total_cost']) ? (float) $data[$date]['total_cost'] : 0.00;
            }

            foreach($data as $value) {
                $total_order += $value["amount_order"];
                $total_cost += $value["total_cost"];
                $total_order_push += $value["amount_order_push"];
                $total_order_not_push += $value["amount_order_not_push"];
            }
            $results['labels'] = $dates;
            $results['total_order'] = $total_order;
            $results['total_cost'] = $total_cost;
            $results['total_order_push'] = $total_order_push;
            $results['total_order_not_push'] = $total_order_not_push;

            return $this->sendSuccess($results);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }
}