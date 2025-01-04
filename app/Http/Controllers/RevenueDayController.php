<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Http\Requests\RevenueDayRequest;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ShopRequest;
use App\Models\RevenueDay;
use App\Models\Shop;
use App\Repositories\RevenueDayRepository;
use Illuminate\Http\Request;
use App\Http\Resources\RevenueDayResource;
use Illuminate\Support\Facades\Auth;



class RevenueDayController extends BaseController
{
    protected $revenueDayRepository;

    public function __construct(RevenueDayRepository $revenueDayRepository)
    {
        $this->revenueDayRepository = $revenueDayRepository;
    }

    public function init()
    {
        try {
            $shops = Shop::select(['id as value', 'name as label'])->get();
            $data = [
                'shops' => $shops
            ];

            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }
    
    public function index(Request $request)
    {
        try {
            $params = [
                'per_page' => $request->per_page ?? 1,
                'keyword' => $request->keyword ?? '',
                'shop_id' => $request->shop_id ?? null
            ];
            $results = $this->revenueDayRepository->index($params);
            $revenueDays = RevenueDayResource::collection($results);
            $paginator = $revenueDays->resource->toArray();
            $paginator['data'] = $paginator['data'] ?? [];
    
            $data = [
                'revenue_days' => $revenueDays,
                'paginator' => count($paginator['data']) > 0 ? $this->paginate($paginator) : null,
            ];
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function updateOrCreate(RevenueDayRequest $request)
    {
        try {
            $id = $request->id ?? 0;
            $data = [
                'shop_id' => $request->shop_id,
                'revenue' => $request->revenue,
                'date' => $request->date
            ];
            if ($id > 0) {
                $data['updated_by'] = Auth::user()->id;
                $data['updated_at'] = now();
                RevenueDay::find($id)->update($data);
            } else {
                $data['created_by'] = Auth::user()->id;
                $data['created_at'] = now();
                RevenueDay::insert($data);
            }
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function edit($id)
    {
        try {
            $result = RevenueDay::find($id);
            $data = new RevenueDayResource($result);
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function destroy($id)
    {
        try {
            RevenueDay::find($id)->delete();
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }
}