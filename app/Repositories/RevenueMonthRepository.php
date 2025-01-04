<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\RevenueMonth;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;


class RevenueMonthRepository
{
    public function index($params)
    {
        $query = RevenueMonth::with(['shop', 'createdBy', 'updatedBy']);

        if (!empty($params['keyword'])) {
            $query->where(function ($subQuery) use ($params) {
                $keyword = '%' . $params['keyword'] . '%';

                // Tìm kiếm theo tên shop
                $subQuery->whereHas('shop', function ($shopQuery) use ($keyword) {
                    $shopQuery->where('name', 'like', $keyword);
                });

                // Tìm kiếm theo tên người tạo
                $subQuery->orWhereHas('createdBy', function ($userQuery) use ($keyword) {
                    $userQuery->where('name', 'like', $keyword);
                });
            });
        }

        if (!empty($params['shop_id'])) 
        {
            $query->where('shop_id', $params['shop_id']);
        }

        return $query->paginate($params['per_page']);
    }
    
}