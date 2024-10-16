<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;


class WebhookController extends BaseController
{
    protected $baseUrlPrintify;
    protected $baseUrlLenful;

    public function __construct()
    {   
        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlLenful = 'https://s-lencam-v3.lenful.com/api';
    }

    public function updateStatusOrderPrintify(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook:' . json_encode($request->all()));
            $resource = $request['resource'];
            $order_id = $resource['id'];
            $status = $resource['data']['status'];
            $shop_id = $resource['data']['shop_id'];
            $keyPrintify = env('TOKEN_PRINTIFY');
            $shop_id = env('SHOP_ID_PRINTIFY');
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
                $order =  Order::where('order_id', $order_id)->first();
                if ($order) {
                    $order->cost = $cost / 100;
                    $order->save();
                    Helper::trackingInfo('Webhook cập nhật cost thành công');
                }else {
                    Helper::trackingInfo('Không tim thấy order');
                }
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
            $order_id = $request['resource']['order_code'];
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_order' => $tracking_order]);
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
            $result = [];
            if (strpos($order_number, '_') !== false) {
                $base = strstr($order_number, '#', true);
                $numbers = explode('_', substr($order_number, strpos($order_number, '#') + 1));
                
                foreach ($numbers as $number) {
                    $result[] = $base . "#" . $number;
                }
            }else {
                $result[] = $order_number;
            }
            $data = [
                'order_id' => $order_id,
                'status_order' => $status,
            ];
            DB::table('orders')->whereIn('order_number', $result)->update($data);
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
            $order_id = $request['resource']['code'];
            $doneEvents = [];
            foreach ($orderProgress as $event) {
                if ($event['status'] === 'done') {
                    $doneEvents[] = $event['event'];
                }
            }

            if (!empty($doneEvents)) {
                $lastDoneEvent = end($doneEvents);
            }
            
            DB::table('orders')->where('order_id', $order_id)->update(['status_order' => $lastDoneEvent]);
            Helper::trackingInfo('Cập nhật order_status Order Merchize thành công');
        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function orderPaymentMerchize(Request $request){
        try {
            Helper::trackingInfo('Body Webhook Progress Order Merchize:' . json_encode($request->all()));
            $cost = $request['resource']['fulfillment_cost'];
            $order_id = $request['resource']['code'];
            $order =  Order::where('order_id', $order_id)->first();
                if ($order) {
                    $order->cost = $cost;
                    $order->save();
                    Helper::trackingInfo('Cập nhật cost Order Merchize thành công');
                }else {
                    Helper::trackingInfo('Không timg thấy order');
                }
            DB::table('orders')->where('order_id', $order_id)->update(['cost' => $cost]);
        } catch (\Throwable $th) {
            Helper::trackingInfo('Lỗi' . json_encode($th->getMessage()));
        }
    }

    public function updateOrderOtb()
    {
        try {
            Helper::trackingInfo('Bắt đầu cập nhật order OTB');
            $client = new Client();

            $resLogin = $client->request('POST', 'https://otbzone.com/bot/api/v1/auth/authenticate', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'password' => 'TABlAGgAYQBuAGgAJgAyADIAOQA0AA==',
                    'rememberMe' => false,
                    'username' => 'lehanhhong2294@gmail.com',
                ],
            ]);

            $resLoginConvert = json_decode($resLogin->getBody()->getContents(), true);
            $token = trim($resLoginConvert['data']['accessToken']['token']) ?? null;
            
            if (!$token) {
                return ['401' => 'Đăng nhập OTB không thành công'];
            }

            $response = $client->request('POST', 'https://otbzone.com/bot/api/v1/data-lists', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                
                'json' => [
                    'model' => 'orderdraft',
                    // 'filters' => [
                    //     [
                    //         'field' => 'createdAt',
                    //         'operation' => 'between',
                    //         'value' => ['1725123600000', '1730393999999'],
                    //         'dayAgo' => 7,
                    //     ],
                    // ],
                    'filterType' => 'AND',
                    'sorting' => [
                        'field' => 'createdAt',
                        'direction' => 'desc',
                    ],
                    // 'pagination' => [
                    //     'page' => 1,
                    //     'pageSize' => 20,
                    // ],
                    'filtersRef' => [],
                ],
            ]);

            $res = json_decode($response->getBody()->getContents(), true);

            $data = $res['data']['data'];
            foreach($data as $order) {
                $data = [
                    'order_id' => $order['id'],
                    'status_order' => $order['orderSellerStatus'] != '' ? $order['orderSellerStatus'] : null,
                    'tracking_order' => $order['addedTrackingCode'] != 0 ? $order['addedTrackingCode'] : null,
                ];
                $order_number = $order['refId'];
                $arr_order_number = [];
                if (strpos($order_number, '_') !== false) {
                    $base = strstr($order_number, '#', true);
                    $numbers = explode('_', substr($order_number, strpos($order_number, '#') + 1));
                    
                    foreach ($numbers as $number) {
                        $arr_order_number[] = $base . "#" . $number;
                    }
                }else {
                    $arr_order_number[] = $order_number;
                }
                
                $value = DB::table('orders')->whereIn('order_number', $arr_order_number)->first();
                if ($value) {
                    DB::table('orders')->whereIn('order_number', $arr_order_number)->update($data);
                    $value_order =  Order::where('order_number', $arr_order_number[0])->first();
                    if ($value_order) {
                        $value_order->cost = $order['totalAmount']/100;
                        $value_order->save();
                    }else {
                        Helper::trackingInfo('Không timg thấy order');
                    }
                }
            }

            $orderFails = Order::where('is_push', true)->where('place_order', 'otb')->where('status_order', 'pending')->get();
            foreach($orderFails as $order) {
                $order->is_push = false;
                $order->save();
            }

            Helper::trackingInfo('Cập nhật order OTB thành công');
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
            Helper::trackingInfo('Cập nhật order OTB thất bại');
        }
    }

    public function updateOrderLenful()
    {
        try {
            $uLenful = env('U_LENFUL');
            $pLenful = env('P_LENFUL');
            $client = new Client();
            $resLogin = $client->post('https://s-lencam.lenful.com/api/seller/login', [
                'form_params' => [
                    'user_name' => $uLenful,
                    'password' => $pLenful,
                ],
            ]);

            $resLoginFormat = json_decode($resLogin->getBody()->getContents(), true);
            $token = $resLoginFormat['access_token'] ?? '';
            if (empty($token)) {
                Helper::trackingError('Đăng nhập thất bại');
            }

            $toDate = Carbon::now()->format('Y-m-d');
            $fromDate = Carbon::now()->subDays(7)->format('Y-m-d');

            $queryParams = [
                'page' => 1,
                'limit' => 500,
                'fields' => 'id,order_number,payment_status,total_price,status',
                'sort_by' => 'create_date_desc',
            ];

            $body = [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ];

            $response = $client->request('POST', 'https://s-lencam-v3.lenful.com/api/order/list', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'query' => $queryParams,
                'json' => $body,
            ]);

            $res = json_decode($response->getBody()->getContents(), true);
            if (count($res['data']) > 0) {
                foreach($res['data'] as $data) {
                    $order = Order::where('order_id', $data['id'])->first();
                    if ($order) {
                        $order->cost = (float)$data['total_price'];
                        $order->status = $data['status'];
                        $order->save();
                    }
                }
            }
            
            Helper::trackingInfo('Cập nhật order lenful thành công!');

        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
            Helper::trackingError('Cập nhật order lenful thất bại');
        }
    }

    public function updateOrderHubfulfill()
    {
        try {
            $toDate = Carbon::now()->format('Y-m-d');
            $fromDate = Carbon::now()->subDays(7)->format('Y-m-d');

            $orders = DB::table('orders')->where('place_order', 'hubfulfill')
                               ->where('is_push', true)
                               ->whereBetween('date_push', [$fromDate, $toDate])  
                               ->select('order_id')->distinct()   
                               ->get();

            foreach($orders as $order) {
                $client = new Client();
                $token = env('TOKEN_HUBFULFILL');
                $response = $client->get('https://hubfulfill.com/api/orders/'.$order->order_id, [
                    'headers' => [
                        'X-API-KEY' => $token,
                        'Content-Type'  => 'application/json',
                    ],
                ]);

                $res = json_decode($response->getBody()->getContents(), true);
                $order = Order::where('order_id', $order->order_id)->first();
                $order->status_order = $res['status'];
                $order->tracking_order = $res['tracking_number'] ?? null;
                $order->cost = $res['total'];
                $order->save();
            }

            Helper::trackingInfo('Cập nhật order hubfulfill thành công!');

        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
            Helper::trackingError('Cập nhật order hubfulfill thất bại');
        }
    }

}