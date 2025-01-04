<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Http\Requests\RevenueMonthRequest;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ShopRequest;
use App\Models\RevenueMonth;
use App\Repositories\RevenueMonthRepository;
use Illuminate\Http\Request;
use App\Http\Resources\RevenueMonthResource;
use Illuminate\Support\Facades\Auth;



class RevenueMonthController extends BaseController
{
    protected $revenueMonthRepository;

    public function __construct(RevenueMonthRepository $revenueMonthRepository)
    {
        $this->revenueMonthRepository = $revenueMonthRepository;
    }
    public function index(Request $request)
    {
        try {
            $params = [
                'per_page' => $request->per_page ?? 1,
                'keyword' => $request->keyword ?? '',
                'shop_id' => $request->shop_id ?? null
            ];
            $results = $this->revenueMonthRepository->index($params);
            $revenueMonths = RevenueMonthResource::collection($results);
            $paginator = $revenueMonths->resource->toArray();
            $paginator['data'] = $paginator['data'] ?? [];
    
            $data = [
                'revenue_months' => $revenueMonths,
                'paginator' => count($paginator['data']) > 0 ? $this->paginate($paginator) : null,
            ];
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function updateOrCreate(RevenueMonthRequest $request)
    {
        try {
            $id = $request->id ?? 0;
            $data = [
                'shop_id' => $request->shop_id,
                'revenue' => $request->revenue,
                'year' => $request->year,
                'month' => $request->month
            ];
            if ($id > 0) {
                $data['updated_by'] = Auth::user()->id;
                $data['updated_at'] = now();
                RevenueMonth::find($id)->update($data);
            } else {
                $data['created_by'] = Auth::user()->id;
                $data['created_at'] = now();
                RevenueMonth::insert($data);
            }
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError('Lỗi Server');
        }
    }

    public function edit($id)
    {
        try {
            $result = RevenueMonth::find($id);
            $data = new RevenueMonthResource($result);
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function destroy($id)
    {
        try {
            RevenueMonth::find($id)->delete();
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }
}