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
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_order' => $tracking_number]);
            Helper::trackingInfo('Webhook cập nhật tracking number thành công');

        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function updateTrackingNumberLenful(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Lenful:' . json_encode($request->all()));
            $order_id = $request->lenful_order_short_id;
            $tracking_number = $request->tracking_numbers;
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_number' => $tracking_number]);
            Helper::trackingInfo('Webhook cập nhật tracking number lenful thành công');

        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function updateTrackingNumberMerchize(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Tracking Merchize:' . json_encode($request->all()));
            $tracking_order = $request['resource']['tracking_number'];
            $order_number = $order_number = $request['resource']['identifier'];
            DB::table('orders')->where('order_number', $order_number)->update(['tracking_order' => $tracking_order]);
            Helper::trackingInfo('Webhook cập nhật tracking number merchize này');
        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function createOrderMerchize(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Created Order Merchize:' . json_encode($request->all()));
            $order_id = $request['resource']['code'];
            $status = 'created';
            $order_number = $request['resource']['identifier'];
            $data = [
                'order_id' => $order_id,
                'status_order' => $status,
            ];
            DB::table('orders')->where('order_number', $order_number)->update($data);
            Helper::trackingInfo('Webhook Created Order Merchize thành công');
        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function progressOrderMerchize(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Progress Order Merchize:' . json_encode($request->all()));
            $orderProgress = $request['resource']['order_progress'];
            $order_number = $request['resource']['identifier'];
            $doneEvents = [];
            foreach ($orderProgress as $event) {
                if ($event['status'] === 'done') {
                    $doneEvents[] = $event['event'];
                }
            }

            if (!empty($doneEvents)) {
                $lastDoneEvent = end($doneEvents);
            }
            
            DB::table('orders')->where('order_number', $order_number)->update(['status_order' => $lastDoneEvent]);
            Helper::trackingInfo('Cập nhật order_status Order Merchize thành công');
        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function orderPaymentMerchize(Request $request){
        try {
            Helper::trackingInfo('Body Webhook Progress Order Merchize:' . json_encode($request->all()));
            $cost = $request['resource']['fulfillment_cost'];
            $order_number = $request['resource']['identifier'];
            DB::table('orders')->where('order_number', $order_number)->update(['cost' => $cost]);
            Helper::trackingInfo('Cập nhật cost Order Merchize thành công');
        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

}