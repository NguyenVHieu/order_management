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

    public function __construct(OrderRepository $orderRepository)
    {   

        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlMerchize = 'https://bo-group-2-2.merchize.com/ylbf9aa/bo-api/';
        $this->keyPrintify = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzN2Q0YmQzMDM1ZmUxMWU5YTgwM2FiN2VlYjNjY2M5NyIsImp0aSI6IjA1YmU0ZTVmZTNjNzAzYWMxYjI2ZTUwM2ZkYmVlNzg3YmU3NGM0ODIyNzA4ZjQyMTAxODMwMzVmN2MzMTE3MjZhMDEzODg4YzQ1NzhjYzY5IiwiaWF0IjoxNzI1OTcwNzQwLjE0MzcyMiwibmJmIjoxNzI1OTcwNzQwLjE0MzcyNCwiZXhwIjoxNzU3NTA2NzQwLjEzNjE1LCJzdWIiOiIxOTc2NzMzNiIsInNjb3BlcyI6WyJzaG9wcy5tYW5hZ2UiLCJzaG9wcy5yZWFkIiwiY2F0YWxvZy5yZWFkIiwib3JkZXJzLnJlYWQiLCJvcmRlcnMud3JpdGUiLCJwcm9kdWN0cy5yZWFkIiwicHJvZHVjdHMud3JpdGUiLCJ3ZWJob29rcy5yZWFkIiwid2ViaG9va3Mud3JpdGUiLCJ1cGxvYWRzLnJlYWQiLCJ1cGxvYWRzLndyaXRlIiwicHJpbnRfcHJvdmlkZXJzLnJlYWQiLCJ1c2VyLmluZm8iXX0.AUE02qL1aknUudYJNSN_hxF_Gg2Q3vkd9KdLM-uKxf6-yA8kTIvhOH8WuwtyYWNg7QmU5MYuP597SCVXSdg';
        $this->keyMechize ='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2NmQ5MzBhNDM2OWRhODJkYmUzN2I2NzQiLCJlbWFpbCI6ImhpZXVpY2FuaWNrMTBAZ21haWwuY29tIiwiaWF0IjoxNzI1ODkyODkzLCJleHAiOjE3Mjg0ODQ4OTN9.UCBHnw0jH0EIVzubiWlXlPbuBs3Er3PMxpPi6QywT0o';
        $this->orderRepository = $orderRepository;
    }

    public function getInformationProduct($params)
    {
        foreach($params as $param) {
            $first_name = explode(" ", $param['shippingAddress'][0])[0];
            $last_name = explode(" ", $param['shippingAddress'][0])[1];
            $address = $param['shippingAddress'][1];
            $country = $param['shippingAddress'][count($param['shippingAddress']) -1];
            $infoCity = explode(", ", $param['shippingAddress'][count($param['shippingAddress']) -2]);
            $city = $infoCity[0];
            $state = explode(" ", $infoCity[1])[0];
            $zip = explode(" ", $infoCity[1])[1];   
            $apartment = null;

            if ($param['shippingAddress'][count($param['shippingAddress']) -3] != $address) {
                $apartment = $param['shippingAddress'][count($param['shippingAddress']) -3];
            }
            $shop = Shop::where('name', str_replace("\r", "", $param['shop']))->first();
            
            $data = [
                'order_number' => $param['orderNumber'],
                'product_name' => $param['product'],
                'price' => $param['price'],
                'shop_id' => $shop->id,
                'size' => $param['size'] != 'N/A' ? $param['size'] : null,
                'color' => $param['color'] != 'N/A' ? $param['color'] : null,
                'personalization' => $param['personalization'] != 'N/A' ? $param['personalization'] : null,
                'thumbnail' => $param['thumb'],
                'quantity' =>  $param['quantity'],
                'item_total' => $param['itemTotal'],
                'discount' => $param['discount'],
                'sub_total' => $param['subtotal'],
                'shipping' => $param['shipping'],
                'sale_tax' => $param['salesTax'],
                'order_total' => $param['orderTotal'],
                'first_name' => $first_name,
                'last_name' => $last_name,
                'address' => $address,
                'country' => $country,
                'state' => $state,
                'apartment' => $apartment,
                'recieved_mail_at' => $param['recieved_mail_at'],
                'zip' => $zip,
                'city' => $city,
                'user_id' => Auth::user()->id,
                'is_push' => false,
                'is_approval' => false,
            ];

            $order = DB::table('orders')->where('order_number', $data['order_number'])->first();
            if (empty($order)) {
                DB::table('orders')->insert($data);
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
                                    "print_provider_id" => $data['print_provider_id'],
                                    "blueprint_id" => 50,
                                    "variant_id" => 33721,
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
                                    "variant_id"=> 33721,
                                    "print_provider_id"=> $data['print_provider_id'],
                                    "cost"=> 414,
                                    "shipping_cost"=> 400,
                                    "status"=> "pending",
                                    "sent_to_production_at"=> "2025-04-18 13:24:28+00:00",
                                    "fulfilled_at"=> "2025-04-18 13:24:28+00:00",
                                    "blueprint_id" => 50
                                ]
                            ],
                            "shipping_method" => 1, 
                            "send_to_production" => true,
                            "address_to" => [
                                "first_name"=> $order->first_name,
                                "last_name"=> $order->last_name,
                                "region"=> "",
                                "country" => "BE",
                                "address1"=> $order->address,
                                "city"=> $order->city,
                                // "zip" => $order->zip
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
                    $order = DB::table('orders')->leftJoin('products', 'products.id', '=', 'orders.product_id')->where('orders.id', $data['order_id'])->select('orders.*', 'products.name as product_name')->first();
   
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
                                "name" => $order->product_name ?? "Example product",
                                "product_id" => $order->product_id,
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
                    $emailHtml = $message->getHTMLBody();
                    $thumbRegex = '/<img[^>]+src="([^"]+\.jpg)"/';
                    $thumb = $this->extractInfo($thumbRegex, $emailHtml);

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
                    $data['thumb'] = $thumb;
                    $data['recieved_mail_at']  = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
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
            $orders = $this->orderRepository->index($params, $columns);

            return $this->sendSuccess($orders);

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

}
