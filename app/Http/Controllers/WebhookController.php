<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\DB;

class WebhookController extends BaseController
{
    public function updateStatusOrder(Request $request)
    {
        try {
            $resource = $request['resource'];
            $order_id = $resource['id'];
            $status = $resource['data']['status'];
            DB::table('orders')->where('order_id', $order_id)->update(['status' => $status]);
            Helper::trackingInfo('Webhook cập nhật status thành công');

        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }


}