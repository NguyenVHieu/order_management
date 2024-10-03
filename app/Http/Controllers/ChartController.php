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
                    // Liệt kê từng ngày trong khoảng thời gian
                    while ($start <= $end) {
                        $dates[] = $start->format('Y-m-d'); // Thêm ngày vào mảng
                        $start->addDay(); // Tăng lên 1 ngày
                    }
                    break;
        
                case 'month':
                    // Liệt kê từng tháng trong khoảng thời gian
                    while ($start <= $end) {
                        $dates[] = $start->format('Y-m'); // Thêm tháng vào mảng
                        $start->addMonth(); // Tăng lên 1 tháng
                    }
                    break;
        
                case 'year':
                    // Liệt kê từng năm trong khoảng thời gian
                    while ($start <= $end) {
                        $dates[] = $start->format('Y'); // Thêm năm vào mảng
                        $start->addYear(); // Tăng lên 1 năm
                    }
                    break;
        
                default:
                    throw new Exception('Invalid type provided.');
            }
        
            $results = [];
            $data = $this->orderRepository->filterOrderByTime($request->all());
            foreach ($dates as $date) {
                if (!isset($data[$date])) {
                    $results[$date] = $data[$date];
                } else {
                    $results[$date] = [];
                }
            }
            return $results;
        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError('Lỗi Server');
        }
    }
}