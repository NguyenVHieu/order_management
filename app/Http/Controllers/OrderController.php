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
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Http\Requests\OrderRequest;

class OrderController extends BaseController
{
    protected $baseUrlMerchize;
    protected $baseUrlPrintify;
    protected $keyPrintify;
    protected $shop_id;
    protected $keyMechize;
    protected $orderRepository;
    protected $baseUrlPrivate;
    protected $keyHubfulfill;
    protected $baseUrlHubfulfill;
    protected $baseUrlLenful;

    public function __construct(OrderRepository $orderRepository)
    {   

        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlMerchize = 'https://bo-group-2-2.merchize.com/ylbf9aa/bo-api/';
        $this->baseUrlPrivate = 'https://api.privatefulfillment.com/v1';
        $this->baseUrlHubfulfill = 'https://hubfulfill.com/api';
        $this->baseUrlLenful = 'https://s-lencam.lenful.com/api';
        $this->keyPrintify = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzN2Q0YmQzMDM1ZmUxMWU5YTgwM2FiN2VlYjNjY2M5NyIsImp0aSI6IjA1YmU0ZTVmZTNjNzAzYWMxYjI2ZTUwM2ZkYmVlNzg3YmU3NGM0ODIyNzA4ZjQyMTAxODMwMzVmN2MzMTE3MjZhMDEzODg4YzQ1NzhjYzY5IiwiaWF0IjoxNzI1OTcwNzQwLjE0MzcyMiwibmJmIjoxNzI1OTcwNzQwLjE0MzcyNCwiZXhwIjoxNzU3NTA2NzQwLjEzNjE1LCJzdWIiOiIxOTc2NzMzNiIsInNjb3BlcyI6WyJzaG9wcy5tYW5hZ2UiLCJzaG9wcy5yZWFkIiwiY2F0YWxvZy5yZWFkIiwib3JkZXJzLnJlYWQiLCJvcmRlcnMud3JpdGUiLCJwcm9kdWN0cy5yZWFkIiwicHJvZHVjdHMud3JpdGUiLCJ3ZWJob29rcy5yZWFkIiwid2ViaG9va3Mud3JpdGUiLCJ1cGxvYWRzLnJlYWQiLCJ1cGxvYWRzLndyaXRlIiwicHJpbnRfcHJvdmlkZXJzLnJlYWQiLCJ1c2VyLmluZm8iXX0.AUE02qL1aknUudYJNSN_hxF_Gg2Q3vkd9KdLM-uKxf6-yA8kTIvhOH8WuwtyYWNg7QmU5MYuP597SCVXSdg';
        $this->keyMechize ='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2NmQ5MzBhNDM2OWRhODJkYmUzN2I2NzQiLCJlbWFpbCI6ImhpZXVpY2FuaWNrMTBAZ21haWwuY29tIiwiaWF0IjoxNzI1ODkyODkzLCJleHAiOjE3Mjg0ODQ4OTN9.UCBHnw0jH0EIVzubiWlXlPbuBs3Er3PMxpPi6QywT0o';
        $this->keyHubfulfill = 'fa5677f70014b9d618d6aaa9567bab3fca9083b37b165a03cf93b2bca12737f1';
        $this->orderRepository = $orderRepository;
    }

    function pushOrderToPrintify($data) {
        $results = [];
        foreach($data as $key => $orders) {
            // dd($orders);
            try {
                $key_order_number = $key. time();
                $lineItems = [];
                $info = [];
                $check = true;

                foreach($orders as $order) {
                    $variant_id = $this->getVariantId($order->blueprint_id, $order->print_provider_id, $order->size, $order->color);
                    if ($variant_id == 0) {
                        $check = false;
                        $results[$order->order_number.' '.$order->style.' '.$order->color] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                    }else {
                        $results[$order->order_number.' '.$order->style.' '.$order->color] = 'Success!';
                    }

                    $info[$order->id] = [
                        'variant_id' => $variant_id,
                        'blueprint_id' => $order->blueprint_id,
                        'print_provider_id' => $order->print_provider_id,
                        'place_order' => 'printify',
                        'is_push' => true
                    ];

                    $item = [
                        "print_provider_id" =>$order->print_provider_id,
                        "blueprint_id" => $order->blueprint_id,
                        "variant_id" => $variant_id,
                        "print_areas" => [
                            "front" => $order->img_1,
                            // "back" => $order->img_2,
                        ],
                        "quantity" => $order->quantity
                    ];

                    $lineItems[] = $item;
                }

                if (count($lineItems) > 0 && $check == true) {
                    $orderData = [
                        "external_id" => "order_sku_" . $key_order_number,
                        "label" => "order_sku_" . $key_order_number,
                        "line_items" => array_values($lineItems),
                        "shipping_method" => 1,
                        "is_printify_express" => false,
                        "is_economy_shipping" => false,
                        "send_shipping_notification" => false,
                        "address_to" => [
                        "first_name" => $order->first_name,
                        "last_name" => $order->last_name,
                        "country" => "US",
                        "region" => $order->state,
                        "address1" => $order->address,
                        "city" => $order->city,
                        "zip" => $order->zip
                        ]
                    ];
                    
                    
                    $client = new Client();
                    $response = $client->post($this->baseUrlPrintify.'shops/18002634/orders.json', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->keyPrintify,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);        
                    $res = json_decode($response->getBody()->getContents(), true);
                    if ($response->getStatusCode() === 200) {
                        if (count($info) > 0) {
                            foreach($info as $id => $value) {
                                $value['order_id'] = $res['id'];
                                DB::table('orders')->where('id', $id)->update($value);
                            }
                        }
                    } else {
                        $results = [];
                        $results[$key] = 'Lỗi khi tạo order';
                    }
                }
            } catch (\Throwable $th) {
                $results[$key] = "Lỗi khi tạo order";
            }
        }

        return $results;
    }

    function pushOrderToMerchize($data) 
    {
        $lineItems = [];
        $results = [];
        foreach($data as $key => $orders) {
            try {
                $key_order_number = $key. time();
                foreach($orders as $order) {
                    $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                    $lineItems[] = [
                        "name" => "Product API". $order->order_number,
                        "quantity" => $order->quantity,
                        "image" => $order->img_6,
                        "design_front" => $order->img_1,
                        "attributes" =>  [
                            [
                                "name" =>  "product",
                                "option" =>  $product->merchize ?? ''
                            ],
                            [
                                "name" =>  "Color",
                                "option" =>  $order->color ?? ''
                            ],
                            [
                                "name" =>  "Size",
                                "option" =>  $order->size ?? ''
                            ]
                        ]
                    ];
                }

                if (count($lineItems) > 0) {
                    $client = new Client();
                    $orderData = [
                        "order_id" =>  $key_order_number,
                        "identifier" =>  $key_order_number,
                        "shipping_info" => [
                            "full_name" => $order->first_name . "" . $order->last_name,
                            "address_1" => $order->address,
                            "address_2" => "",
                            "city" => $order->city,
                            "state" => $order->state,
                            "postcode" => $order->zip,
                            "country" => $order->country,
                        ],
                        "items" => array_values($lineItems),
                    ];
                        
                    $response = $client->post($this->baseUrlMerchize. '/order/external/orders', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->keyMechize,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);
                    $res = json_decode($response->getBody()->getContents(), true);
                
                    if ($response->getStatusCode() === 200) {
                        DB::table('orders')->where('id', $order->id)->update(['is_push' => 1, 'order_id' => $res['data']['_id'], 'place_order' => 'merchize', 'status_order' => $res['data']['status']]); 
                        $results[$key] = 'Success';
                    } else {
                        $results[$key] = 'Failed';
                    }
                }
            } catch (\Throwable $th) {
                $results[$key] = 'Lỗi khi tạo order';
            }
            
        } 

        return $results;
    }

    function pushOrderToPrivate($data) 
    {
        try {
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
            if (empty($resLoginConvert['accessToken'])) {
                return ['401' => 'Đăng nhập Private fullfillment không thành công'];
            }

            $token = $resLoginConvert['accessToken'];
        } catch (\Throwable $th) {
            return ['401' => 'Đăng nhập Private fullfillment không thành công'];
        }
        
        $lineItems = [];
        $results = [];
        foreach($data as $key => $orders) {   
            try {
                $check = true;
                foreach($orders as $order) {
                    if (!empty($order->img_1) && !empty($order->img_2)) {
                        $prodNum = 2;
                    }else {
                        $prodNum = 1;
                    }
                    $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                    $resSku = $client->get($this->baseUrlPrivate. '/sku', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                            'Content-Type'  => 'application/json',
                        ],
                        'query' => [
                            'prodType' => $product->private,
                            'prodSize' => str_replace("\r", "", trim($order->size)),
                            'prodNum' => $prodNum,
                            'prodColor' => $order->color,
                        ],
                    ]);
    
                    $resSkuConvert = json_decode($resSku->getBody()->getContents(), true);
    
                    if (empty($resSkuConvert['data'])) {
                        $results[$order->order_number .' '. $order->color .' '. $order->size] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                        $check = false;
                    }else {
                        $results[$order->order_number .' '. $order->color .' '. $order->size] = 'Success!';
                    }
        
                    $variantId = $resSkuConvert['data'][0]['variantId'] ?? 0;
                    $lineItems[] = [
                        "variantId" => $variantId,
                        "quantity" => 1,
                        "printAreaFront" => $order->img_1,
                        "printAreaBack" => $order->img_2,
                        "mockupFront" => $order->img_6,
                        "mockupBack" => $order->img_7, 
                        "printAreaLeft" => $order->img_3 ?? '',
                        "printAreaRight" => $order->img_4 ?? '',
                        "printAreaNeck" => $order->img_5 ?? '',
                    ];
    
                }

                if (count($lineItems) > 0 && $check == true) {
                    $orderData = [
                        "order" => [
                            "orderId" => $order->order_number. time(),
                            "shippingMethod" => "STANDARD",
                            "firstName" => $order->first_name,
                            "lastName" => $order->last_name,
                            "countryCode" => "US",
                            "provinceCode" => $order->state,
                            "addressLine1" => $order->address,
                            "city" => $order->city,
                            "zipcode" => $order->zip,
                        ],
                        "product" => array_values($lineItems)
                    ];
                }
    
                $resOrder = $client->post($this->baseUrlPrivate. '/order', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $orderData // Gửi dữ liệu đơn hàng
                ]);

                dd($resOrder->getBody()->getContents());

    
                if ($resOrder->getStatusCode() === 201) {
                    DB::table('orders')->where('id', $order->id)->update(['is_push' => 1, 'place_order' => 'private']);
                } else {
                    $results[$key] = 'Failed';
                }
            } catch (\Throwable $th) {
                dd($th);
                $results = [];
                $results[$key] = 'Lỗi khi tạo order';
            }    
        }
        
        return $results;    
    }

    function pushOrderToOtb($data) 
    {
        try {
            $ids = [];
            $date = date('YmdHis');
            $nameOutput = 'OTB_'. $date .'.xlsx';
            $pathFileOriginal = public_path('/files/OTB-template.xlsx');
            $outputFileOtb = public_path('/files/fileExportOtb/'.$nameOutput);

            $spreadsheet = IOFactory::load($pathFileOriginal);
            $sheet = $spreadsheet->getActiveSheet();
            $arr_type = config('constants.felix_size_otb');
            $row = 2; 


            foreach ($data as $orders) {
                foreach ($orders as $index => $order) {
                    $ids[] = $order->id;

                    $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                    if (array_key_exists($product->otb, $arr_type)) {
                        $felix_size = $arr_type[$product->otb];
                    }else {
                        $felix_size = null;
                    }

                    $sheet->setCellValue('A' . $row, $order->order_number); // Cột A
                    $sheet->setCellValue('B' . $row, $order->first_name. ' ' . $order->last_name); // Cột B
                    $sheet->setCellValue('C' . $row, $order->address); // Cột C
                    $sheet->setCellValue('D' . $row, $order->apartment); // Cột D
                    $sheet->setCellValue('E' . $row, $order->city);
                    $sheet->setCellValue('F' . $row, $order->state);
                    $sheet->setCellValue('G' . $row, $order->zip);
                    $sheet->setCellValue('H' . $row, $order->country);
                    $sheet->setCellValue('K' . $row, $order->quantity);
                    $sheet->setCellValue('L' . $row, $product->otb);
                    $sheet->setCellValue('M' . $row, $order->first_name. ' ' . $order->last_name);
                    $sheet->setCellValue('N' . $row, $order->color);
                    $sheet->setCellValue('O' . $row, $felix_size.$order->size);
                    $sheet->setCellValue('S' . $row, $order->img_1);
                    $row++;
                }
                
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($outputFileOtb);

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

            $response = $client->request('POST', 'https://otbzone.com/bot/api/v1/import-queues', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                
                'multipart' => [
                    [
                        'name'     => 'type',
                        'contents' => 'INSTANT_ORDER'
                    ],
                    [
                        'name'     => 'images',
                        'contents' => fopen($outputFileOtb, 'r'),
                        'filename' => $nameOutput
                    ],
                ],
            ]);
            // dd($response->getBody()->getContents());
            $statusCode = $response->getStatusCode(); // Lấy mã trạng thái HTTP

            if ($statusCode == 200) {
                DB::table('orders')->whereIn('id', $ids)->update(['is_push' => 1, 'place_order' => 'otb']);
                return [1 => "Order OTB Success"];
                
            } else {
                return [1 => "Order OTB Failed"];
            }
        } catch (\Throwable $th) {
            return [1 => "Order OTB Failed"];
        }      
    }

    function pushOrderToHubfulfill($data)
    {
        $results = [];
        foreach($data as $key => $orders) {
            try {
                $lineItems = [];
                $arr_shippng = config('constants.shipping_hubfulfill');
                
                foreach ($orders as $order) {
                    $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                    $lineItems[] = [
                        "sku" => 'TS002',
                        "quantity" => (int)$order->quantity,
                        "design" => [
                            "mockup_url" => $order->img_6 ?? ''
                        ]
                    ];
                }

                $shipping_method = $arr_shippng[$order->country] ?? '';
                if ($shipping_method == '') {
                    $results[$order->order_number] = 'Không tìm thấy phương thức vận chuyển';
                    continue;
                }

                if (count($lineItems) > 0) {
                    $orderData = [
                        "order_id" => $key. time(),
                        "items" => $lineItems,
                        "shipping" => [
                            "shipping_name" => $order->first_name .' '. $order->last_name,
                            "shipping_address_1" => $order->address,
                            "shipping_city" => $order->city,
                            "shipping_zip" => $order->zip,
                            "shipping_state" => $order->state,
                            "shipping_country" => $order->country,
                        ],
                        "shipping_method" => $shipping_method
                    ];
                }

                $client = new Client();
                $response = $client->post($this->baseUrlHubfulfill.'/orders', [
                    'headers' => [
                        'X-API-KEY' => $this->keyHubfulfill,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $orderData
                ]);        
                $resOrder = json_decode($response->getBody()->getContents(), true);
                if ($response->getStatusCode() == 200){
                    $data = [
                        'place_order' => 'hubfulfill',
                        'is_push' => 1,
                        'order_id' => $resOrder['order_id'],
                        'cost' => $resOrder['total'],
                        'tracking_order' => $resOrder['tracking_number']
                    ];

                    DB::table('orders')->where('order_number', $order->order_number)->update($data);
                    $results[$order->order_number] = 'Success';
                }else {
                    $results[$order->order_number] = 'Lỗi khi tạo order';
                }
            } catch (\Throwable $th) {
                dd($th);
                $results[$order->order_number] = 'Lỗi khi tạo order';
            }
            
        }

        return $results;
        
    }

    function pushOrderToLenful($data)
    {
        $results = [];
        try {
            $client = new Client();
            $resLogin = $client->post($this->baseUrlLenful.'/seller/login', [
                'form_params' => [
                    'user_name' => 'lehanhhong2294@gmail.com',
                    'password' => '928a58ecc3',
                ],
            ]);

            $resLoginFormat = json_decode($resLogin->getBody()->getContents(), true);
            $token = $resLoginFormat['access_token'] ?? '';
            if (empty($token)) {
                return ['401' => 'Đăng nhập Lenful không thành công'];
            }
        } catch (\Throwable $th) {
            return ['401' => 'Đăng nhập Lenful không thành công'];
        }
        
        foreach ($data as $key => $orders) {
            try {
                $check = true;
                $lineItems = [];

                foreach ($orders as $order) {
                    $sku = $this->getSkuLenful($order->product_name, $order->size, $order->color);
                    if ($sku == 0) {
                        $results[$order->order_number.' '. $order->size. ' '. $order->color] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                        $check = false;
                    }else {
                        $results[$order->order_number.' '. $order->size. ' '. $order->color] = 'Sucess';
                    }

                    $lineItems[] = [
                        "design_sku" => $sku."-DESIGN",
                        "product_sku" => $sku,
                        "quantity" => 1,
                        "mockups" => [
                            $order->img_6
                        ],
                        "designs" => [
                            [
                                "position" => 1,
                                "link" => $order->img_1,
                            ]
                        ],

                        "shippings" => [0]
                    ];
                }

                if (count($lineItems) > 0 && $check == true) {
                    $orderData = [
                        "order_number" => "#". $order->order_number,
                        "first_name" => $order->first_name,
                        "last_name" => $order->last_name,
                        "country_code" => "US",
                        "city" => $order->city,
                        "zip" => $order->zip,
                        "address_1" => $order->address,
                        "items" => array_values($lineItems)
                    ];

                    $resOrder = $client->post($this->baseUrlLenful.'/order/66e024d4682685fd3b9f35d0/create', [
                        'headers' => [
                                'Authorization' => 'Bearer ' . $token,
                                'Content-Type'  => 'application/json',
                            ],
                            'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);
                    $res = json_decode($resOrder->getBody()->getContents(), true);

                    if ($resOrder->getStatusCode() === 200) {
                        $data = [
                            'place_order' => 'lenful',
                            'is_push' => 1,
                            'cost' => $res['data']['total_price'],
                        ];
                        DB::table('orders')->where('id', $order->id)->update($data);
                        $results[$key] = 'Success';
    
                    } else {
                        $results[$key] = 'Lỗi khi tạo order';
                    }
                }
            } catch (\Throwable $th) {
                $results[$key] = 'Lỗi khi tạo order';
            }
        }

        return $results;
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
                        ->leftJoin('blueprints', 'key_blueprints.product_printify_name', '=', 'blueprints.name')
                        ->select('blueprints.blueprint_id as value', 'key_blueprints.product_printify_name as label')->distinct()
                        ->where('key_blueprints.product_printify_name', '!=', null)
                        ->get();

            $orders = $this->orderRepository->index($params, $columns);

            $data = [
                'orders' => $orders,
                'blueprints' => $blueprints
            ];

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

    public function pushOrder(Request $request)
    {
        try {
            $placeOrder = $request->place_order ?? null;
            $orders = $request->orders;
            $datas = [];
            $results = [];
            
            foreach($orders as $data) {
                $order = DB::table('orders')->where('id', $data['order_id'])->first();
                $platform = $placeOrder != null  ? $placeOrder : $order->place_order;
                if (!empty($data['blueprint_id'])) {
                    $order->blueprint_id = $data['blueprint_id'];
                }

                if (!empty($data['print_provider_id'])) {
                    $order->print_provider_id = $data['print_provider_id'];
                }

                $datas[$platform][$order->order_number][] = $order;
            }

            foreach ($datas as $key => $data)
                switch ($key) {
                    case 'printify':
                        $results[] = $this->pushOrderToPrintify($data);
                        break;
                    case 'merchize':
                        $results[] = $this->pushOrderToMerchize($data);
                        break;
                    case 'private':
                        $results[] = $this->pushOrderToPrivate($data);
                        break;
                    case 'otb':
                        $results[] = $this->pushOrderToOtb($data);
                        break;
                    case 'hubfulfill':
                        $results[] = $this->pushOrderToHubfulfill($data);
                        break;
                    case 'lenful':
                        $results[] = $this->pushOrderToLenful($data);
                        break;
                    default:
                        return $this->sendError('Không tìm thấy nơi đặt hàng', 404);
                        break;   
                }

                return $this->sendSuccess($results);
            
            
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage(), 500);
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
        } catch (\Exception $ex) {
            return $this->sendError('error'. $ex->getMessage(), 500);
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
            $ids = $request->ids;
            $type = $request->type ?? 0;
            foreach ($ids as $id) {
                $data = Order::find($id);
                if (!empty($data)) {
                    if ($type == 1) {
                        $columns = [
                            'is_approval' => true,
                            'approval_by' => Auth::user()->id,
                        ];
                    } else {
                        $columns['is_approval'] = false;
                    }
                    
                    DB::table('orders')->where('id', $id)->update($columns);
                    
                } else {
                    DB::rollBack();
                    return $this->sendError('Không tìm thấy order', 404);
                }
            }

            DB::commit();
            return $this->sendSuccess('Success');
        } catch (\Throwable $th) {
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
            
            $title = str_replace('″', "''", $variant['title']);
            $size = str_replace('″', '"', $size);
            $color = str_replace('″', '"', $color);
            $resultColor = true;
            $resultSize = true;

            if ($color != null) {
                $resultColor = stripos($title, ' / '.$color) !== false || stripos($title, $color.' / ') !== false;
            }

            if ($size != null) {
                $resultSize = stripos($title, ' / '.$size) !== false || stripos($title, $size.' / ') !== false;
            }

            if ($resultColor && $resultSize) {
                return $variant;
            } else {
                return false;
            }

            
        });

        $variant = array_values($matchedVariant);

        if (!empty($variant)) {

            $variant_id = $variant[0]['id'];
        }else {
            $variant_id = 0;
        }

        return $variant_id;
        
    }

    public function getSkuLenful($product_name, $size, $color)
    {
        $client = new Client();
        $response = $client->get($this->baseUrlLenful.'/product', [
            'query' => [
                'page' => 1,
                'limit' => 10,
                'fields' => 'variants',
                'keyword' => $product_name
            ],
        ]);

        $resFormat = json_decode($response->getBody()->getContents(), true);
        if (empty($resFormat['data'][0]['variants'])) {
            return 0;
        }

        $matchedVariant = array_filter($resFormat['data'][0]['variants'], function($variant) use ($size, $color) {
            
            
            $option = $variant['option_values'];
            $resultColor = true;
            $resultSize = true;

            if (!empty($color) && strpos($variant['full_name'], 'Color') !== false) {
                if (in_array($color, $option)) {
                    $resultColor = true;
                } else {
                    $resultColor = false;
                }
            }

            if (!empty($size) && strpos($variant['full_name'], 'Size') !== false) {
                if (in_array($size, $option)) {
                    $resultSize = true;
                } else {
                    $resultSize = false;
                }
            }

            if ($resultColor && $resultSize ) {
                return $variant;
            } else {
                return false;
            }
        });

        $variant = array_values($matchedVariant);

        if (!empty($variant)) {
            $sku = $variant[0]['sku'];
        }else {
            $sku = 0;
        }

        return $sku;
    }

    public function saveImgOrder(Request $request)
    {
        try {
            $data = [];

            if (isset($request->r_img_1)) {
                $data['img_1'] = $this->saveImgeSku($request->r_img_1);
            }
            if (isset($request->r_img_2)) {
                $data['img_2'] = $this->saveImgeSku($request->r_img_2);
            }
            if (isset($request->r_img_3)) {
                $data['img_3'] = $this->saveImgeSku($request->r_img_3);
            }
            if (isset($request->r_img_4)) {
                $data['img_4'] = $this->saveImgeSku($request->r_img_4);
            }
            if (isset($request->r_img_5)) {
                $data['img_5'] = $this->saveImgeSku($request->r_img_5);
            }
            if (isset($request->r_img_6)) {
                $data['img_6'] = $this->saveImgeSku($request->r_img_6);
            }
            if (isset($request->r_img_7)) {
                $data['img_7'] = $this->saveImgeSku($request->r_img_7);
            }

            $data['place_order'] = $request->place_order;

            $order = DB::table('orders')->where('id', $request->id)->first();
            if ($request->all == true) {
                DB::table('orders')->where('order_number', $order->order_number)->update($data);
            }else {
                DB::table('orders')->where('id', $request->id)->update($data);
                DB::table('orders')->where('order_number', $order->order_number)->update(['place_order' => $data['place_order']]);
            }
            
            return $this->sendSuccess('ok');
        } catch (\Throwable $th) {
            return $this->sendError('error'. $th->getMessage(), 500);
        }
        

    }

    public function update(OrderRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $order = DB::table('orders')->where('id', $id)->first();
            $data = [
                'country' => $request->country,
                'city' => $request->city,
                'address' => $request->address,
                'zip' => $request->zip,
                'state' => $request->state,
                'updated_at' => now(),
                'updated_by' => Auth::user()->id
            ];

            if ($order->multi == true) {
                DB::table('orders')->where('order_number', $order->order_number)->update($data);
            }

            $data['color'] = $request->color;
            $data['size'] = $request->size;

            DB::table('orders')->where('id', $id)->update($data);
            DB::commit();
            return $this->sendSuccess('Cập nhật order thành công!');
        } catch (\Throwable $th) {
            DB::rollBack(); 
            return $this->sendError('Cập nhật order thất bại');
        }
    }

    public function edit($id) 
    {
        try {
            $order = DB::table('orders')->where('id', $id)->first();
            if (!$order) {
                return $this->sendError('Không tìm thấy đơn hàng');
            }


            return $this->sendSuccess($order);
        } catch (\Throwable $th) {
            return $this->sendError('Hiển thị đơn hàng thất bại', 500);
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
                    'status_order' => $order['orderStatus'] != '' ? $order['orderStatus'] : null,
                    'tracking_order' => $order['addedTrackingCode'] != 0 ? $order['addedTrackingCode'] : null,
                    'cost' => $order['totalAmount']/100,
                ];
                $order_number = $order['refId'];
                
                $order = DB::table('orders')->where('order_number', $order_number)->first();
                if ($order) {
                    DB::table('orders')->where('order_number', $order_number)->update($data);
                }
            }
            Helper::trackingInfo('Cập nhật order OTB thành công');
        } catch (\Throwable $th) {
            Helper::trackingInfo('Cập nhật order OTB thất bại');
        }
    }
}
