<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;


class OrderController extends BaseController
{
    private $baseUrlMerchize;
    private $baseUrlPrintify;

    public function __construct()
    {
        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlMerchize = 'https://bo-group-2-2.merchize.com/ylbf9aa/bo-api/';
    }

    function pushOrderToPrintify($orderData) {
        $key = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzN2Q0YmQzMDM1ZmUxMWU5YTgwM2FiN2VlYjNjY2M5NyIsImp0aSI6IjE2YTcyNDJkZjU4ZDRkNzMyMDI5ZDc4ZDBmOTVkNzEzOGYxMzVkNmIyNDVmNDk4NGQ3ODBmNDRhZWJjYzkzNzBiMTU0MGU5MTUwMzhjZjRjIiwiaWF0IjoxNzI1NDk5NjIxLjM1OTIxLCJuYmYiOjE3MjU0OTk2MjEuMzU5MjEyLCJleHAiOjE3NTcwMzU2MjEuMzUxODUxLCJzdWIiOiIxMDg2OTM1NyIsInNjb3BlcyI6WyJzaG9wcy5tYW5hZ2UiLCJzaG9wcy5yZWFkIiwiY2F0YWxvZy5yZWFkIiwib3JkZXJzLnJlYWQiLCJvcmRlcnMud3JpdGUiLCJwcm9kdWN0cy5yZWFkIiwicHJvZHVjdHMud3JpdGUiLCJ3ZWJob29rcy5yZWFkIiwid2ViaG9va3Mud3JpdGUiLCJ1cGxvYWRzLnJlYWQiLCJ1cGxvYWRzLndyaXRlIiwicHJpbnRfcHJvdmlkZXJzLnJlYWQiLCJ1c2VyLmluZm8iXX0.Am_nZqDHguRVb5TnwkhXyh-v_oJA7WyU5moazWprlZZN7jpXklxGH5VRO8rLRB5hwk9Bu5lmOcHfrM048yY';
        $client = new Client();

        $response = $client->post($this->baseUrlPrintify.'shops/5926629/orders.json', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'json' => $orderData // Gửi dữ liệu đơn hàng
        ]);
            
        if ($response->getStatusCode() === 200) {
            return true;
        } else {
            return false;
        }
    }

    function pushOrderToMerchize($orderData) 
    {
        $key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2NmQ5MzBhNDM2OWRhODJkYmUzN2I2NzQiLCJlbWFpbCI6ImhpZXVpY2FuaWNrMTBAZ21haWwuY29tIiwiaWF0IjoxNzI1NTA5Nzk2LCJleHAiOjE3MjgxMDE3OTZ9.TFO6ovKEft_-HWFT7knkT5Vx6ZfZ0UXxyZ3pUVnnujU';
        $client = new Client();

        $response = $client->post($this->baseUrlMerchize. '/order/external/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'json' => $orderData // Gửi dữ liệu đơn hàng
        ]);
            
        if ($response->getStatusCode() === 200) {
            return true;
        } else {
            return false;
        }
    }

    public function createOrder(Request $req) 
    {
        try {
            $orderDataPrintify = [
                "external_id" => "order_id_12346",
                "label" => "Order#1234",
                "line_items" => [
                    [
                        "product_id"=> "6397f58fb31571bb5c0556f9",
                        "quantity"=> 1,
                        "variant_id"=> 81810,
                        "print_provider_id"=> 228,
                        "cost"=> 414,
                        "shipping_cost"=> 400,
                        "status"=> "pending",
                        "metadata"=> [
                            "title"=> "3.5\" x 4.9\" (Vertical) / Coated (both sides) / 1 pc",
                            "price"=> 622,
                            "variant_label"=> "Golden indigocoin",
                            "sku"=> "97122532902512964757",
                            "country"=> "United States"
                        ],
                        "sent_to_production_at"=> "2025-04-18 13:24:28+00:00",
                        "fulfilled_at"=> "2025-04-18 13:24:28+00:00",
                        "blueprint_id" => 1094
                    ]
                ],
                "shipping_method" => 1, // Bạn cần tham khảo ID phương thức vận chuyển từ Printify
                "send_to_production" => true,
                "address_to" => [
                    "first_name"=> "John",
                    "last_name"=> "Smith",
                    "region"=> "",
                    "address1"=> "ExampleBaan 121",
                    "city"=> "Retie",
                    "zip"=> "2470",
                    "email"=> "vanhieuisme01@msn.com",
                    "phone"=> "0574 69 21 90",
                    "country"=> "BE",
                    "company"=> "MSN"
                ],
                
            ];
            
            $placeOrder = $req->place_order;
            switch ($placeOrder) {
                case 'printify':
                    $orderData = $req->order_data;
                    break;
                case 'merchize':
                    $orderData = $req->order_data;
                    break;
                default:
                    return $this->sendError('Invalid place order', 400);
                    break;
            }
            
            $result = $this->pushOrderToPrintify($orderData);
            return $result;
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
        
    }

}
