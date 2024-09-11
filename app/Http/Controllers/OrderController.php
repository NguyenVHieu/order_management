<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;


class OrderController extends BaseController
{
    private $baseUrlMerchize;
    private $baseUrlPrintify;
    private $keyPrintify;
    private $shop_id;
    private $keyMechize;

    public function __construct()
    {   
        if (Auth::user()) {
            $user = User::find(Auth::user()->id)->with('shop')->first();
        }
         
        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlMerchize = 'https://bo-group-2-2.merchize.com/ylbf9aa/bo-api/';
        $this->keyPrintify = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzN2Q0YmQzMDM1ZmUxMWU5YTgwM2FiN2VlYjNjY2M5NyIsImp0aSI6IjA1YmU0ZTVmZTNjNzAzYWMxYjI2ZTUwM2ZkYmVlNzg3YmU3NGM0ODIyNzA4ZjQyMTAxODMwMzVmN2MzMTE3MjZhMDEzODg4YzQ1NzhjYzY5IiwiaWF0IjoxNzI1OTcwNzQwLjE0MzcyMiwibmJmIjoxNzI1OTcwNzQwLjE0MzcyNCwiZXhwIjoxNzU3NTA2NzQwLjEzNjE1LCJzdWIiOiIxOTc2NzMzNiIsInNjb3BlcyI6WyJzaG9wcy5tYW5hZ2UiLCJzaG9wcy5yZWFkIiwiY2F0YWxvZy5yZWFkIiwib3JkZXJzLnJlYWQiLCJvcmRlcnMud3JpdGUiLCJwcm9kdWN0cy5yZWFkIiwicHJvZHVjdHMud3JpdGUiLCJ3ZWJob29rcy5yZWFkIiwid2ViaG9va3Mud3JpdGUiLCJ1cGxvYWRzLnJlYWQiLCJ1cGxvYWRzLndyaXRlIiwicHJpbnRfcHJvdmlkZXJzLnJlYWQiLCJ1c2VyLmluZm8iXX0.AUE02qL1aknUudYJNSN_hxF_Gg2Q3vkd9KdLM-uKxf6-yA8kTIvhOH8WuwtyYWNg7QmU5MYuP597SCVXSdg';
        $this->keyMechize ='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2NmQ5MzBhNDM2OWRhODJkYmUzN2I2NzQiLCJlbWFpbCI6ImhpZXVpY2FuaWNrMTBAZ21haWwuY29tIiwiaWF0IjoxNzI1ODkyODkzLCJleHAiOjE3Mjg0ODQ4OTN9.UCBHnw0jH0EIVzubiWlXlPbuBs3Er3PMxpPi6QywT0o';
        $this->shop_id = $user->shop->code ?? 0;
    }

    public function getAllProduct() 
    {
        if ($this->shop_id != 0)  {
            try {
                $client = new Client();
                $response = $client->get($this->baseUrlPrintify. 'shops/'.$this->shop_id.'/products.json', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->keyPrintify,
                        'Content-Type'  => 'application/json',
                    ],
                ]);
                $responseData = json_decode($response->getBody()->getContents(), true);
                foreach($responseData['data'] as $res) {
                    $data = [
                        'code' => $res['id'], 
                        'name'=> $res['title'],
                        'images' => $res['images'][0]['src']
                    ];
                    $prod = DB::table('products')->where('code', $data['code'])->first();

                    if (empty($prod)) {
                        DB::table('products')->insert($data);
                    }
                   
                }

                return $this->sendSuccess('add product success!');
            } catch (\Throwable $th) {
                return $this->sendError($th->getMessage(), 501);        
            }
        }
    }

    public function getInformationProduct($params)
    {
        $order = [];
        
        foreach($params as $param) {
            $product = DB::table('products')->where('name', Helper::cleanText($param['product']))->first();
            if ($this->shop_id != 0) 
            {
                if (!empty($product)) {
                    $client = new Client();
                    $response = $client->get("https://api.printify.com/v1/shops/{$this->shop_id}/products/{$product->code}.json", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->keyPrintify,
                        ],
                    ]);

                    if ($response->getStatusCode() == 200) {
                        $infor = json_decode($response->getBody()->getContents(), true);
                    } else {
                        return $this->sendError('error to clone email', 500);
                    }

                }

                $first = reset($param['shippingAddress']);
                $city = end($param['shippingAddress']); 
                $middle = array_slice($param['shippingAddress'], 1, -1);
                $address = implode(', ', $middle);
                
                $data = [
                    'order_number' => $param['orderNumber'],
                    'product_id' => $product->code ?? null,
                    'shop_id' => $this->shop_id,
                    'color' => $param['color'] != 'N/A' ? $param['color'] : '',
                    // 'print_provider_id' => $infor['print_provider_id'],
                    'blueprint_id' => $infor['blueprint_id'] ?? null,
                    'quantity' =>  $param['quantity'],
                    'price' => $param['price'],
                    'item_total' => $param['itemTotal'],
                    'discount' => $param['discount'],
                    'sub_total' => $param['subtotal'],
                    'shipping' => $param['shipping'],
                    'sale_tax' => $param['salesTax'],
                    'order_total' => $param['orderTotal'],
                    'first_name' => explode(" ", $param['shippingAddress'][0])[0],
                    'last_name' => explode(" ", $param['shippingAddress'][0])[1],
                    'address' =>$address,
                    'city' => $city,
                    'user_id' => Auth::user()->id,
                    'is_push' => false
                ];

                $order = DB::table('orders')->where('order_number', $data['order_number'])->first();
                if (empty($order)) {
                    DB::table('orders')->insert($data);
                }
            }
        }
        

    }

    function pushOrderToPrintify($request) {
        try {
            $results = [];
            $orders = $request['orders'];
            if (!empty($orders)) {
                foreach ($orders as $data)
                {
                    $type = $data['type'] ?? '';
                    $order = DB::table('orders')->where('id', $data['order_id'])->first();
                    $order_number = $order->order_number ?? 0;
                    
                    if ($type === 'sku')
                    {
                        $key_order_number = time();
                        $url = $this->saveImgeSku($data['image']);
                        $orderData = [
                                "external_id" => "order_sku_" . $key_order_number,
                                "label" => "order_sku_" . $key_order_number,
                                "line_items" => [
                                [
                                    "print_provider_id" => 5,
                                    "blueprint_id" => 9,
                                    "variant_id" => 17887,
                                    "print_areas" => [
                                    "front" => $url
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
                                "email" => "example@msn.com",
                                "phone" => "0574 69 21 90",
                                "country" => "BE",
                                "region" => "",
                                "address1" => $order->address,
                                "city" => $order->city,
                                // "zip" => $order->zip
                                ]
                        ];
                    } else {
                        $key_order_number = $order->order_number . time();
                        $orderData = [
                            "external_id" => "order_id_".$key_order_number,
                            "label" => "Order#".$key_order_number,
                            "line_items" => [
                                [
                                    "product_id"=> $order->product_id,
                                    "quantity"=> $order->quantity,
                                    "variant_id"=> $order->variant_id,
                                    "print_provider_id"=> $data['print_provider_id'],
                                    "cost"=> 414,
                                    "shipping_cost"=> 400,
                                    "status"=> "pending",
                                    "sent_to_production_at"=> "2025-04-18 13:24:28+00:00",
                                    "fulfilled_at"=> "2025-04-18 13:24:28+00:00",
                                    "blueprint_id" => $order->blueprint_id
                                ]
                            ],
                            "shipping_method" => 1, 
                            "send_to_production" => true,
                            "address_to" => [
                                "first_name"=> $order->first_name,
                                "last_name"=> $order->last_name,
                                "region"=> "",
                                "address1"=> $order->address,
                                "city"=> $order->city,
                            ],
                            
                        ];
                    }
                    $client = new Client();
                    $response = $client->post($this->baseUrlPrintify.'shops/'.$this->shop_id.'/orders.json', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->keyPrintify,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);        
            
                    if ($response->getStatusCode() === 200) {
                        DB::table('orders')->where('id', $order->id)->update(['print_provider_id' => $data['print_provider_id'], 'is_push' => '1']);
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

    function pushOrderToMerchize($orderData) 
    {
        $key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2NmQ5MzBhNDM2OWRhODJkYmUzN2I2NzQiLCJlbWFpbCI6ImhpZXVpY2FuaWNrMTBAZ21haWwuY29tIiwiaWF0IjoxNzI1NTA5Nzk2LCJleHAiOjE3MjgxMDE3OTZ9.TFO6ovKEft_-HWFT7knkT5Vx6ZfZ0UXxyZ3pUVnnujU';
        $client = new Client();

        $response = $client->post($this->baseUrlMerchize. '/order/external/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->keyMechize,
                'Content-Type'  => 'application/json',
            ],
            'json' => $orderData // Gửi dữ liệu đơn hàng
        ]);
        dd(json_decode($response->getBody()->getContents(), true));
        if ($response->getStatusCode() === 200) {
            return 'success';
        } else {
            return 'failed';
        }
    }

    public function fetchMailOrder()
    {
        try {
            $client = \Webklex\IMAP\Facades\Client::account('default');
            $client->connect();

            $inbox = $client->getFolder('INBOX');
            // $today = Carbon::now()->startOfMonth(); 
            // dd($today);

            $messages = $inbox->query()->subject('You made a sale on Etsy')->get();
            
            $list_data = [];
            if (count($messages) > 0) {
                foreach ($messages as $message) {
                    // Trích xuất thông tin từ email
                    $subject = $message->getSubject();
                    $from = $message->getFrom()[0]->mail;
                    $date = $message->getDate();
                    $emailBody = $this->removeLinks($message->getTextBody());

                    $patterns = [
                        'orderNumber' => '/Your order number is:\s*(.*)/',
                        'shippingAddress' => '/Shipping address \*(.*?)\*/s',
                        'product' => '/Learn about Etsy Seller Protection.*?\n(.*?)(?=\nSize:|\nColors:|\nPersonalization:|\nShop:|\nTransaction ID:|\nQuantity:|\nPrice:|\nOrder total|$)/s',
                        'size' => '/Size:\s*(.*)/',
                        'color' => '/Colors:\s*(.*)/',
                        'personalization' => '/Personalization:\s*(.*)/',
                        'shop' => '/Shop:\s*(.*)/',
                        'transactionID' => '/Transaction ID:\s*(\d+)/',
                        'quantity' => '/Quantity:\s*(\d+)/',
                        'price' => '/Price:\s*(?:US\$|\$)(\d+(?:\.\d{1,2})?)/',
                        'itemTotal' => '/Item total:\s*(?:US\$|\$)(\d+(?:\.\d{1,2})?)/',
                        'discount' => '/Discount:\s*- (?:US\$|\$)(\d+(?:\.\d{1,2})?)/',
                        'subtotal' => '/Subtotal:\s*(?:US\$|\$)(\d+(?:\.\d{1,2})?)/',
                        'shipping' => '/Shipping:\s*(?:US\$|\$)(\d+(?:\.\d{1,2})?)/',
                        'salesTax' => '/Sales tax:\s*(?:US\$|\$)(\d+(?:\.\d{1,2})?)/',
                        'orderTotal' => '/Order total:\s*(?:US\$|\$)(\d+(?:\.\d{1,2})?)/'
                    ];
            
                    $data = [];
                    foreach ($patterns as $key => $pattern) {
                        if ($key === 'shippingAddress' || $key === 'product') {
                            $data[$key] = $this->extractInfo($pattern, $emailBody, true);
                        } else {
                            $data[$key] = $this->extractInfo($pattern, $emailBody);
                        }
                        
                    }
                    $data['shippingAddress'] = explode("\n", str_replace("\r", "", trim($data['shippingAddress'])));
                    $data['product'] = str_replace('<', '', $data['product']);
                    $list_data[] = $data;

                    
                }
                $this->getInformationProduct($list_data);
                
                return $this->sendSuccess('clone order ok');
            } 
        } catch (\Throwable $th) {
            dd($th);
            // Log::error('Connection setup failed: ' . $th->getMessage());
        }
    }

    private function extractInfo($pattern, $body, $singleLine = false)
    {
        $options = $singleLine ? 's' : '';
        preg_match($pattern, $body, $matches);
        return $matches[1] ?? 'N/A';
    }

    private function removeLinks($body)
    {
        return preg_replace('/http[^\s]+/', '', $body);
    }

    public function getOrderDB(Request $req) 
    {
        $type = $req->type ?? -1;
        try {
            $query = DB::table('orders')
                    ->select('orders.*', 'products.images', 'products.name as product_name', 'users.name as user_name', 'shops.name as shop_name', 'orders.id as order_id')
                    ->leftJoin('products', 'orders.product_id', '=', 'products.code')
                    ->join('users', 'orders.user_id', '=', 'users.id')
                    ->join('shops', 'orders.shop_id', '=', 'shops.code')
                    ->where('orders.is_push', false);
            if ($type == 0) {
                $query->whereNull("product_id")->orWhere('product_id', '');
            } else if ($type == 1) {
                $query->whereNotNull("product_id");
            }
            $data = $query->get();

            return $this->sendSuccess($data);

        } catch (\Throwable $th) {
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

    public function createOrderSku(Request $request)
    {
        try {
            
            if ($request->hasFile('image')) {
                $file = $request->file('image');
        
                $dateFolder = now()->format('Ymd');
                $time = now()->format('his');
    
                $directory = public_path('uploads/' . $dateFolder);
    
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
    
                $path = $file->move($directory, $time. '_'. $file->getClientOriginalName());
    
                $url = asset('uploads/' .$dateFolder. '/'. $time. '_'. $file->getClientOriginalName());
                dd($url);
    
                $client = new Client();
                $response = $client->post($this->baseUrlPrintify.'shops/'.$this->shop_id.'/orders.json', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->keyPrintify,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                            "external_id"=> "order_sku_test_01",
                            "label"=> "order_sku_test#01",
                            "line_items"=> [
                              [
                                "print_provider_id"=> 5,
                                "blueprint_id"=> 9,
                                "variant_id"=> 17887,
                                "print_areas"=> [
                                  "front"=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e2/Printify.png/220px-Printify.png'
                                //   'front' => $url
                                ],
                                "quantity"=> 1
                              ]
                            ],
                            // "shipping_method"=> 1,
                            // "is_printify_express"=> false,
                            // "is_economy_shipping"=> false,
                            // "send_shipping_notification"=> false,
                            "address_to"=> [
                              "first_name"=> "John",
                              "last_name"=> "Smith",
                              "email"=> "example@msn.com",
                              "phone"=> "0574 69 21 90",
                              "country"=> "BE",
                              "region"=> "",
                              "address1"=> "ExampleBaan 121",
                              "address2"=> "45",
                              "city"=> "Retie",
                              "zip"=> "2470"
                            ],
                    ] // Gửi dữ liệu đơn hàng
                ]);        
    
                if ($response->getStatusCode() === 200) {
                    dd('success');
                    
                } else {
                    dd('failed');
                }
                    
            }
        } catch (\Throwable $th) {
            \Log::error('Error in createOrderSku: ' . $th->getMessage());
            dd($th->getMessage());
        }
        
    }

    public function pushOrder(Request $request)
    {
        // dd($request);
        $placeOrder = $request->place_order;
        switch ($placeOrder) {
            case 'printify':
                $result = $this->pushOrderToPrintify($request);
                return $this->sendSuccess($result);
            case 'merchize':
                $result = $this->pushOrderToMerchize($request->all());
                return $this->sendSuccess($result);
            default:
                return $this->sendError('Function not implemented', 500);
        }
    }

    public function saveImgeSku($image)
    {
        $dateFolder = now()->format('Ymd');
        $time = now()->format('his');

        $directory = public_path('uploads/' . $dateFolder);

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $image->move($directory, $time. '_'. $image->getClientOriginalName());

        $url = asset('uploads/' .$dateFolder. '/'. $time. '_'. $image->getClientOriginalName());

        return $url;
    }

}
