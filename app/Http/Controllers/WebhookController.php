<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\DB;

class WebhookController extends BaseController
{
    public function updateStatusOrderPrintify(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook:' . json_encode($request->all()));
            $resource = $request['resource'];
            $order_id = $resource['id'];
            $status = $resource['data']['status'];
            DB::table('orders')->where('order_id', $order_id)->update(['status_order' => $status]);
            Helper::trackingInfo('Webhook cập nhật status thành công');

        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function updateTrackingNumberPrintify(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook:' . json_encode($request->all()));
            $resource = $request['resource'];
            $order_id = $resource['id'];
            $tracking_number = $resource['data']['carrier']['tracking_number'];
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_number' => $tracking_number]);
            Helper::trackingInfo('Webhook cập nhật tracking number thành công');

        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }


}