<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Models\Order;
use App\Models\Shop;
use App\Repositories\OrderRepository;

class OrderController extends BaseController
{
    protected $baseUrlMerchize;
    protected $baseUrlPrintify;
    protected $keyPrintify;
    protected $shop_id;
    protected $keyMechize;
    protected $orderRepository;
    protected $baseUrlPrivate;

    public function __construct(OrderRepository $orderRepository)
    {   

        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlMerchize = 'https://bo-group-2-2.merchize.com/ylbf9aa/bo-api/';
        $this->baseUrlPrivate = 'https://api.privatefulfillment.com/v1';
        $this->keyPrintify = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzN2Q0YmQzMDM1ZmUxMWU5YTgwM2FiN2VlYjNjY2M5NyIsImp0aSI6IjA1YmU0ZTVmZTNjNzAzYWMxYjI2ZTUwM2ZkYmVlNzg3YmU3NGM0ODIyNzA4ZjQyMTAxODMwMzVmN2MzMTE3MjZhMDEzODg4YzQ1NzhjYzY5IiwiaWF0IjoxNzI1OTcwNzQwLjE0MzcyMiwibmJmIjoxNzI1OTcwNzQwLjE0MzcyNCwiZXhwIjoxNzU3NTA2NzQwLjEzNjE1LCJzdWIiOiIxOTc2NzMzNiIsInNjb3BlcyI6WyJzaG9wcy5tYW5hZ2UiLCJzaG9wcy5yZWFkIiwiY2F0YWxvZy5yZWFkIiwib3JkZXJzLnJlYWQiLCJvcmRlcnMud3JpdGUiLCJwcm9kdWN0cy5yZWFkIiwicHJvZHVjdHMud3JpdGUiLCJ3ZWJob29rcy5yZWFkIiwid2ViaG9va3Mud3JpdGUiLCJ1cGxvYWRzLnJlYWQiLCJ1cGxvYWRzLndyaXRlIiwicHJpbnRfcHJvdmlkZXJzLnJlYWQiLCJ1c2VyLmluZm8iXX0.AUE02qL1aknUudYJNSN_hxF_Gg2Q3vkd9KdLM-uKxf6-yA8kTIvhOH8WuwtyYWNg7QmU5MYuP597SCVXSdg';
        $this->keyMechize ='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2NmQ5MzBhNDM2OWRhODJkYmUzN2I2NzQiLCJlbWFpbCI6ImhpZXVpY2FuaWNrMTBAZ21haWwuY29tIiwiaWF0IjoxNzI1ODkyODkzLCJleHAiOjE3Mjg0ODQ4OTN9.UCBHnw0jH0EIVzubiWlXlPbuBs3Er3PMxpPi6QywT0o';
        $this->orderRepository = $orderRepository;
    }

    function pushOrderToPrintify($request) {
        try {
            $results = [];
            $orders = $request['orders'];
            if (!empty($orders)) {
                foreach ($orders as $data)
                {
                    $order = DB::table('orders')->where('id', $data['order_id'])->first();
                    $order_number = $order->order_number ?? 0;
                    $key_order_number = $order_number. time();
                    $linkFront = $this->saveImgeSku($data['1']);
                    $linkBack = $this->saveImgeSku($data['2']);

                    $orderData = [
                        "external_id" => "order_sku_" . $key_order_number,
                        "label" => "order_sku_" . $key_order_number,
                        "line_items" => [
                        [
                            "print_provider_id" => $data['print_provider_id'],
                            "blueprint_id" => $order->blueprint_id,
                            "variant_id" => $order->variant_id,
                            "print_areas" => [
                                "front" => $linkFront,
                                "back" => $linkBack,
                            ],
                            "quantity" => $order->quantity
                        ]
                        ],
                        "shipping_method" => 1,
                        "is_printify_express" => false,
                        "is_economy_shipping" => false,
                        "send_shipping_notification" => false,
                        "address_to" => [
                        "first_name" => $order->first_name,
                        "last_name" => $order->last_name,
                        "country" => "BE",
                        "region" => "",
                        "address1" => $order->address,
                        "city" => $order->city,
                        // "zip" => $order->zip
                        ]
                    ];
                    
                    $client = new Client();
                    $response = $client->post($this->baseUrlPrintify.'shops/'.$this->shop_id.'/orders.json', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->keyPrintify,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);        
            
                    if ($response->getStatusCode() === 200) {
                        $data = [
                            'print_provider_id' => $data['print_provider_id'], 
                            'is_push' => '1',
                            '1' => $linkFront,
                            '2' => $linkBack,
                        ];
                        DB::table('orders')->where('id', $order->id)->update($data);
                        $results[$order->order_number] = 'success';
                    } else {
                        $results[$order->order_number] = 'failed';
                    }
                }
                return $results;
            }
        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError('error', $th->getMessage());
        }
    }

    function pushOrderToMerchize($request) 
    {
        try {
            $results = [];
            $client = new Client();
            $orders = $request['orders'];
            if (!empty($orders)) {
                foreach ($orders as $data)
                {
                    $urlThumb = $this->saveImgeSku($data['thumb']);
                    $urlFront = $this->saveImgeSku($data['front']);
                    $order = DB::table('orders')->select('*')->first();
   
                    $orderData = [
                        "order_id" => $order->order_number. time(),
                        "identifier" => $order->order_number. time(),
                        "shipping_info" => [
                            "full_name" => $order->first_name . "" . $order->last_name,
                            "address_1" => $order->address,
                            "address_2" => "",
                            "city" => $order->city,
                            "state" => $order->state,
                            "postcode" => $order->zip,
                            "country" => $order->country,
                            // "email" => "customer@example.com",
                            // "phone" => "0123456789"
                        ],
                        "items" => [
                            [
                                "name" => "Example product",
                                // "product_id" => $order->product_id,
                                "merchize_sku" => "CSWSVN000000EA12",
                                "quantity" => $order->quantity,
                                "price" => $order->price,
                                "currency" => "USD",
                                "image" => $urlThumb,
                                "design_front" => $urlFront,
                            ]
                        ]
                    ];
                 
                    $response = $client->post($this->baseUrlMerchize. '/order/external/orders', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->keyMechize,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);
                    
                    if ($response->getStatusCode() === 200) {
                        $results[$order->order_number] = 'success';
                    } else {
                        $results[$order->order_number] = 'failed';
                    }

                    return $results;
                }
            }
        } catch (\Throwable $th) {
            return $this->sendError('error'. $th->getMessage(), 500);
        }
        
    }

    function pushOrderToPrivate($request) 
    {
        try {
            $results = [];
            $client = new Client();
            $resLogin = $client->post($this->baseUrlPrivate. '/login', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'email' => 'lehanhhong2294@gmail.com',
                    'password' => 'cacc6dd0'
                ] // Gửi dữ liệu đơn hàng
            ]);
            $resLoginConvert = json_decode($resLogin->getBody()->getContents(), true);
            $token = $resLoginConvert['accessToken'];

            $orders = $request['orders'];
            foreach($orders as $data) 
            {
                $linkFront = $this->saveImgeSku($data['1']);
                $linkBack = $this->saveImgeSku($data['2']);
                $linkLeft = $this->saveImgeSku($data['3']);
                $linkRight = $this->saveImgeSku($data['4']);
                $linkNeck = $this->saveImgeSku($data['5']);
                $linkMockupFront = $this->saveImgeSku($data['6']);
                $linkMockupBack = $this->saveImgeSku($data['7']);


                $order = DB::table('orders')->where('id', $data['order_id'])->first();

                if (!empty($linkFront) && !empty($linkBack)) {
                    $prodNum = 2;
                }else {
                    $prodNum = 1;
                }

                $resSku = $client->get($this->baseUrlPrivate. '/sku', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'query' => [
                        'prodType' => 'HOODIE',
                        'prodSize' => str_replace("\r", "", trim($order->size)),
                        'prodNum' => $prodNum,
                        'prodColor' => $order->color,
                    ],
                ]);
                $resSkuConvert = json_decode($resSku->getBody()->getContents(), true);
                $variantId = $resSkuConvert['data'][0]['variantId'];

                $orderData = [
                    "order" => [
                        "orderId" => $order->order_number. time(),
                        "shippingMethod" => "STANDARD",
                        "firstName" => $order->first_name,
                        "lastName" => $order->last_name,
                        "countryCode" => "US",
                        "provinceCode" => "AL",
                        "addressLine1" => $order->address,
                        "city" => $order->city,
                        "zipcode" => $order->zip,
                    ],
                    "product" => [
                        [
                        "variantId" => $variantId,
                        "quantity" => 1,
                        "printAreaFront" => $linkFront,
                        "printAreaBack" => $linkBack,
                        "mockupFront" => $linkMockupFront,
                        "mockupBack" => $linkMockupBack,
                        "printAreaLeft" => $linkLeft,
                        "printAreaRight" => $linkRight,
                        "printAreaNeck" => $linkNeck,
                        "customNote" => $order->personalization
                        ]
                    ]
                ];

                $resOrder = $client->post($this->baseUrlPrivate. '/order', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $orderData // Gửi dữ liệu đơn hàng
                ]);

                if ($resOrder->getStatusCode() === 201) {
                    $results[$order->order_number] = 'success';
                } else {
                    $results[$order->order_number] = 'failed';
                }
            }
            
            return $results;    

        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError('error'. $th->getMessage(), 500);
        }
    }

    public function getOrderDB(Request $req) 
    {
        try {
            $userType = Auth::user()->is_admin ? -1 : Auth::user()->user_type_id;
            $shopId = Auth::user()->shop_id;
            $params = [
                'userType' => $userType,
                'shopId' => $shopId,
                'userId' => Auth::user()->id
            ];
            $columns = [
                'orders.*',
                'shops.name as shop_name',
                'orders.id as order_id',
            ];
            $blueprints = DB::table('key_blueprints')
                        ->leftJoin('blueprints', 'key_blueprints.printify', '=', 'blueprints.name')
                        ->select('blueprints.blueprint_id as value', 'key_blueprints.printify as label')->distinct()
                        ->where('key_blueprints.printify', '!=', null)
                        ->get();

            $orders = $this->orderRepository->index($params, $columns);

            $data = [
                'orders' => $orders,
                'blueprints' => $blueprints
            ];

            return $this->sendSuccess($data);

        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError('error', 500);
        }
    }

    public function getProviders($blueprint_id)
    {
        try {
            if ($blueprint_id != -1) {
                $client = new Client();

                $response = $client->get($this->baseUrlPrintify. "catalog/blueprints/{$blueprint_id}/print_providers.json", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->keyPrintify,
                        'Content-Type'  => 'application/json',
                    ],
                ]);
                    
                if ($response->getStatusCode() === 200) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    return $this->sendSuccess($data);
                } else {
                    return $this->sendError('error', $response->getStatusCode());
                }
            }else {
                return $this->sendSuccess([]);
            }
            
        } catch (\Throwable $th) {
            return $this->sendError('error', $th->getMessage());
        }
        
    }

    public function pushOrder(Request $request)
    {
        $placeOrder = $request->place_order;
        switch ($placeOrder) {
            case 'printify':
                $result = $this->pushOrderToPrintify($request);
                return $this->sendSuccess($result);
            case 'merchize':
                $result = $this->pushOrderToMerchize($request);
                return $this->sendSuccess($result);
            case 'private':
                $result = $this->pushOrderToPrivate($request);
                return $this->sendSuccess($result);
            default:
                return $this->sendError('Function not implemented', 500);
        }
    }

    public function saveImgeSku($image)
    {
        try {
            $dateFolder = now()->format('Ymd');
            $time = now()->format('his');

            $directory = public_path('uploads/' . $dateFolder);

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = $image->move($directory, $time. '_'. $image->getClientOriginalName());

            $url = asset('uploads/' .$dateFolder. '/'. $time. '_'. $image->getClientOriginalName());

            return $url;
        } catch (\Throwable $th) {
            return $this->sendError('error'. $th->getMessage(), 500);
        }
        
    }

    public function storeBlueprint()
    {
        $client = new Client();
        $response = $client->get($this->baseUrlPrintify. "/catalog/blueprints.json", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->keyPrintify,
                'Content-Type'  => 'application/json',
            ],
        ]);

        $data_save = [];

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody()->getContents(), true);
            foreach ($data as $blueprint) {
                $blue = [
                    'blueprint_id' => $blueprint['id'],
                    'name'  => $blueprint['title'],
                ];
                $data_save[] = $blue;
            }
            DB::table('blueprints')->insert($data_save);
            return $this->sendSuccess('ok');
        } else {
            return $this->sendError('error', $response->getStatusCode());
        }
    }

    public function approvalOrder(Request $request)
    {
        try {
            DB::beginTransaction();
            $orders = $request['orders'];
            foreach ($orders as $order) {
                $img_1 = isset($order->r_img_1) ? $this->saveImgeSku($order->r_img_1) : null;
                $img_2 = isset($order->r_img_2) ? $this->saveImgeSku($order->r_img_2) : null;
                $img_3 = isset($order->r_img_3) ? $this->saveImgeSku($order->r_img_3) : null;
                $img_4 = isset($order->r_img_4) ? $this->saveImgeSku($order->r_img_4) : null;
                $img_5 = isset($order->r_img_5) ? $this->saveImgeSku($order->r_img_5) : null;
                $img_6 = isset($order->r_img_6) ? $this->saveImgeSku($order->r_img_6) : null;
                $img_7 = isset($order->r_img_7) ? $this->saveImgeSku($order->r_img_7) : null;

                $print_provider_id = $order['print_provider_id'];
                $data = Order::find($order['id']);

                if (!empty($data)) {
                    $size = isset($data->size) ? $data->size : null;
                    $color = isset($data->color) ? $data->color : null;
                    $blueprint_id = $order['blueprint_id'] ?? $data->blueprint_id;
                    $variant_id = $this->getVariantId($blueprint_id, $print_provider_id, $size, $color);

                    if (!$variant_id) {
                        DB::rollBack();
                        return $this->sendError('Không tìm thể variant ở order'. $order->order_number);
                    }

                    $data = [
                        'img_1' => $img_1,
                        'img_2' => $img_2,
                        'img_3' => $img_3,
                        'img_4' => $img_4,
                        'img_5' => $img_5,
                        'img_6' => $img_6,
                        'img_7' => $img_7,
                        'variant_id' => $variant_id,
                        'print_provider_id' => $print_provider_id,
                        'approval_by' => Auth::user()->id,
                    ];

                    DB::table('orders')->where('id', $order['id'])->update($data);
                    
                } else {
                    DB::rollBack();
                    return $this->sendError('Không tìm thấy order', 404);
                }
                DB::commit();
                return $this->sendSuccess('Approval ok');

            }
        } catch (\Throwable $th) {
            dd($th);
            DB::rollBack();
            return $this->sendError($th->getMessage());
        }
    }

    public function getVariantId($blueprint_id, $provider_id, $size, $color)
    {
        $client = new Client();
        $resVariant = $client->get($this->baseUrlPrintify. "/catalog/blueprints/{$blueprint_id}/print_providers/{$provider_id}/variants.json", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->keyPrintify,
                'Content-Type'  => 'application/json',
            ],
        ]);
        $resFormat = json_decode($resVariant->getBody()->getContents(), true);
        $matchedVariant = array_filter($resFormat['variants'], function($variant) use ($size, $color) {
            
            $title = str_replace('″', '"', $variant['title']);
            $size = str_replace('″', '"', $size);
            $color = str_replace('″', '"', $color);
            if ($color != null) {
                $result = stripos($title, $color) !== false;
            }

            if ($size != null) {
                $result = stripos($title, $size) !== false;
            }
            
            return $result;
        });

        if (!empty($matchedVariant)) {
            $variant_id = $matchedVariant[0]['id'];
        }

        return $variant_id;
        
    }
}
