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
            if ($this->shop_id != 0 && !empty($product->code)) 
            {
                $client = new Client();
                $response = $client->get("https://api.printify.com/v1/shops/{$this->shop_id}/products/{$product->code}.json", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->keyPrintify,
                    ],
                ]);

                if ($response->getStatusCode() == 200) {
                    $infor = json_decode($response->getBody()->getContents(), true);
                    $data = [
                        'order_number' => $param['orderNumber'],
                        'product_id' => $product->code,
                        'shop_id' => $infor['shop_id'],
                        // 'print_provider_id' => $infor['print_provider_id'],
                        'blueprint_id' => $infor['blueprint_id'],
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
                        'address' => $param['shippingAddress'][1] . $param['shippingAddress'][2] .$param['shippingAddress'][3],
                        'city' => $param['shippingAddress'][4],
                        'user_id' => Auth::user()->id,
                        'is_push' => false
                    ];

                    $order = DB::table('orders')->where('order_number', $data['order_number'])->first();
                    if (empty($order)) {
                        DB::table('orders')->insert($data);
                    }
                } else {
                    return $this->sendError('error to clone email', 500);
                }
            }
        }
        

    }

    function pushOrderToPrintify($orderData) {
        $client = new Client();
        $response = $client->post($this->baseUrlPrintify.'shops/5926629/orders.json', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->keyPrintify,
                'Content-Type'  => 'application/json',
            ],
            'json' => $orderData // Gửi dữ liệu đơn hàng
        ]);        

        if ($response->getStatusCode() === 200) {
            return 'success';
            
        } else {
            return 'failed';
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

    public function createOrder(Request $req)
    {
        $results = [];
        $orders = DB::table('orders')->whereIn('id', $req->ids)->get()->toArray();
        $placeOrder = $req->place_order;
        $function = '';
        switch ($placeOrder) {
            case 'printify':
                $function = 'pushOrderToPrintify';
                break;
            case 'merchize':
                $function = 'pushOrderToMerchize';
                break;
            default:
                $function = '';
                break;
        }
        try {
            foreach($orders as $order){
                if ($function === 'pushOrderToPrintify') {
                    $orderData = [
                        "external_id" => "order_id_ne323".$order->order_number,
                        "label" => "Order1#".$order->order_number,
                        "line_items" => [
                            [
                                "product_id"=> $order->product_id,
                                "quantity"=> $order->quantity,
                                "variant_id"=> 81810,
                                "print_provider_id"=> 228,
                                "cost"=> 414,
                                "shipping_cost"=> 400,
                                "status"=> "pending",
                                // "metadata"=> [
                                //     "title"=> "3.5\" x 4.9\" (Vertical) / Coated (both sides) / 1 pc",
                                //     "price"=> 622,
                                //     "variant_label"=> "Golden indigocoin",
                                //     "sku"=> "97122532902512964757",
                                //     "country"=> "United States"
                                // ],
                                "sent_to_production_at"=> "2025-04-18 13:24:28+00:00",
                                "fulfilled_at"=> "2025-04-18 13:24:28+00:00",
                                "blueprint_id" => 1094
                            ]
                        ],
                        "shipping_method" => 1, // Bạn cần tham khảo ID phương thức vận chuyển từ Printify
                        "send_to_production" => true,
                        "address_to" => [
                            "first_name"=> $order->first_name,
                            "last_name"=> $order->last_name,
                            "region"=> "",
                            "address1"=> $order->address,
                            "city"=> $order->city,
                            // "zip"=> "2470",
                            // "email"=> "vanhieuisme01@msn.com",
                            // "phone"=> "0574 69 21 90",
                            // "country"=> "BE",
                            // "company"=> "MSN"
                        ],
                        
                    ];
                }else {
                    $orderData = [
                        "order_id" => "test123",
                        "identifier" => "test.com",
                        "shipping_info" => [
                            "full_name" => "John",
                            "address_1" => "123 ABC",
                            "address_2" => "",
                            "city" => "California",
                            "state" => "CA",
                            "postcode" => "12345",
                            "country" => "US",
                            "email" => "customer@example.com",
                            "phone" => "0123456789"
                        ],
                        "tax" => "", // optional, example: "123456789",
                        "tags" => ["tag A", "tag B"],
                        "items" => [
                            [
                                "name" => "Example product",
                                "product_id" => '66daa582fb8f3665a75fe65d',
                                "sku" => "180BUS000DKHAA00",
                                "merchize_sku" => "1C-VGY09T-1725605242KK34V-048D",
                                "quantity" => 1,
                                "price" => 35.3,
                                "currency" => "USD",
                                "image" => "https://example.com/products/hello-product/thumb.jpg",
                                "design_front" => "https://example.com/your-private-artwork-front.png",
                                "design_back" => "https://example.com/your-private-artwork-back.png",
                                "design_sleeve" => "https://example.com/your-private-artwork-sleeve.png",
                                "design_hood" => "https://example.com/your-private-artwork-hood.png",
                                "attributes" => [
                                    [
                                        "name" => "product",
                                        "option" => "T-shirt"
                                    ],
                                    [
                                        "name" => "Color",
                                        "option" => "Black"
                                    ],
                                    [
                                        "name" => "Size",
                                        "option" => "M"
                                    ]
                                ]
                            ]
                        ]
                        
                    ];
                }

                if ($function) {
                    $result = $this->$function($orderData);
                    if ($result === 'success' && $function === 'pushOrderToPrintify') {
                        DB::table('orders')->where('id', $order->id)->update(['print_provider_id' => $orderData['line_items'][0]['print_provider_id'], 'is_push' => '1']);
                    }
                    $results[$order->id] = $result;
                    return $this->sendSuccess($results);
                    
                } else {
                    return $this->sendError('Function not implemented', 500);
                }

            }
            
            return $this->sendSuccess($results);
        } catch (\Throwable $th) {
            return $this->sendError('error', $th->getMessage());
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
                        'price' => '/Price:\s*US\$(.*)/',
                        'itemTotal' => '/Item total:\s*US\$(.*)/',
                        'discount' => '/Discount:\s*- US\$(.*)/',
                        'subtotal' => '/Subtotal:\s*US\$(.*)/',
                        'shipping' => '/Shipping:\s*US\$(.*)/',
                        'salesTax' => '/Sales tax:\s*US\$(.*)/',
                        'orderTotal' => '/Order total:\s*US\$(.*)/'
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
                    $data['orderNumber'] = Helper::cleanText($data['orderNumber']);
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

    public function getOrderDB() 
    {
        try {
            $data = DB::table('orders')
                    ->select('orders.*', 'products.images', 'products.name as product_name', 'users.name as user_name', 'shops.name as shop_name')
                    ->join('products', 'orders.product_id', '=', 'products.code')
                    ->join('users', 'orders.user_id', '=', 'users.id')
                    ->join('shops', 'orders.shop_id', '=', 'shops.code')
                    ->where('orders.is_push', false)
                    ->get();

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
}
