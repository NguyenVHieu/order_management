<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\DB;

class WebhookController extends BaseController
{
    protected $baseUrlPrintify;

    public function __construct()
    {   
        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
    }

    public function updateStatusOrderPrintify(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook:' . json_encode($request->all()));
            $resource = $request['resource'];
            $order_id = $resource['id'];
            $status = $resource['data']['status'];
            $shop_id = $resource['data']['shop_id'];
            $keyPrintify = DB::table('shops')->where('shop_printify_id', $shop_id)->first()->token_printify;
            Helper::trackingInfo('keyPrintify:', $keyPrintify, 'shop_id:', $shop_id);
            if ($status == 'on-hold') {
                $client = new \GuzzleHttp\Client();
                $response = $client->get($this->baseUrlPrintify. "shops/{$shop_id}/orders/{$order_id}.json", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $keyPrintify,
                        'Content-Type'  => 'application/json',
                    ],
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                $cost = $data['total_price'] + $data['total_shipping'] + $data['total_tax'];
                DB::table('orders')->where('order_id', $order_id)->update(['cost' => $cost/100]);
                Helper::trackingInfo('Webhook cập nhật cost thành công');
            } 
            
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