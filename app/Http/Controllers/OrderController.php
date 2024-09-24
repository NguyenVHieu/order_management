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

    function pushOrderToPrintify($request) {
        $results = [];
        $orders = $request->orders;
        if (!empty($orders)) {
            foreach ($orders as $data)
            {
                $order = DB::table('orders')->where('id', $data['order_id'])->first();
                $provider_id = $data['print_provider_id'];
                $blueprint_id = isset($data['blueprint_id']) ? $data['blueprint_id'] : $order->blueprint_id;
                $order_number = $order->order_number ?? 0;
                $key_order_number = $order_number. time();

                if (empty($order->size) && empty($order->color)) {
                    throw new \Exception('Không tìm thấy size và color ở order'. $order->order_number);
                }

                $variant_id = $this->getVariantId($blueprint_id, $provider_id, $order->size, $order->color);
                if ($variant_id == 0) {
                    throw new \Exception('Không tìm thấy biến thể ở order ' . $order->order_number);
                }

                $orderData = [
                    "external_id" => "order_sku_" . $key_order_number,
                    "label" => "order_sku_" . $key_order_number,
                    "line_items" => [
                    [
                        "print_provider_id" => $provider_id,
                        "blueprint_id" => $blueprint_id,
                        "variant_id" => $variant_id,
                        "print_areas" => [
                            "front" => $order->img_1,
                            // "back" => $order->img_2,
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
                    "country" => "US",
                    "region" => "",
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
                    $data = [
                        'is_push' => '1',
                        'print_provider_id' => $provider_id,
                        'blueprint_id' => $blueprint_id,
                        'variant_id' => $variant_id,
                        'order_id' => $res['id'],
                    ];
                    DB::table('orders')->where('id', $order->id)->update($data);
                    $results[$order->order_number] = 'success';
                } else {
                    $results[$order->order_number] = 'failed';
                }
            }
            return $results;
        }
        
    }

    function pushOrderToMerchize($request) 
    {
        $results = [];
        $client = new Client();
        $orders = $request['orders'];
        if (!empty($orders)) {
            foreach ($orders as $data)
            {
                $order = DB::table('orders')->where('id', $data['order_id'])->first();

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
                            "image" => $order->img_6,
                            "design_front" => $order->img_1,
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
                    DB::table('orders')->where('id', $order->id)->update(['is_push' => 1]);
                    $results[$order->order_number] = 'success';
                } else {
                    $results[$order->order_number] = 'failed';
                }

                return $results;
            }
        }  
    }

    function pushOrderToPrivate($request) 
    {
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
        if (empty($resLoginConvert['accessToken'])) {
            throw new \Exception('Không tìm thấy token');
        }

        $token = $resLoginConvert['accessToken'];

        $orders = $request['orders'];
        foreach($orders as $data) 
        {
            $order = DB::table('orders')->where('id', $data)->first();

            if (!empty($order->img_1) && !empty($order->img_2)) {
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
            if (empty($resSkuConvert['data'])) {
                throw new \Exception('Không tìm thấy sku ở order'. $order->order_number);
            }

            $variantId = $resSkuConvert['data'][0]['variantId'];

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
                "product" => [
                    [
                    "variantId" => $variantId,
                    "quantity" => 1,
                    "printAreaFront" => $order->img_1,
                    "printAreaBack" => $order->img_2,
                    "mockupFront" => $order->img_6,
                    "mockupBack" => $order->img_7, 
                    "printAreaLeft" => $order->img_3 ?? '',
                    "printAreaRight" => $order->img_4 ?? '',
                    "printAreaNeck" => $order->img_5 ?? '',
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
                DB::table('orders')->where('id', $order->id)->update(['is_push' => 1]);
                $results[$order->order_number] = 'success';
            } else {
                $results[$order->order_number] = 'failed';
            }
        }
        
        return $results;    
    }

    function pushOrderToOtb($request) 
    {
        $results = [];
        $ids = [];
        $date = date('YmdHis');
        $nameOutput = 'OTB_'. $date .'.xlsx';
        $pathFileOriginal = public_path('/files/OTB-template.xlsx');
        $outputFileOtb = public_path('/files/fileExportOtb/'.$nameOutput);

        $spreadsheet = IOFactory::load($pathFileOriginal);
        $sheet = $spreadsheet->getActiveSheet();

        $orders = $request->orders;

        $row = 2; 

        foreach ($orders as $order) {
            $order = DB::table('orders')->where('id', $order['order_id'])->first();
            $ids[] = $order->id;
            $sheet->setCellValue('A' . $row, $order->order_number); // Cột A
            $sheet->setCellValue('B' . $row, $order->first_name. ' ' . $order->last_name); // Cột B
            $sheet->setCellValue('C' . $row, $order->address); // Cột C
            $sheet->setCellValue('D' . $row, $order->apartment); // Cột D
            $sheet->setCellValue('E' . $row, $order->city);
            $sheet->setCellValue('F' . $row, $order->state);
            $sheet->setCellValue('G' . $row, $order->zip);
            $sheet->setCellValue('H' . $row, $order->country);
            $sheet->setCellValue('K' . $row, $order->quantity);
            $sheet->setCellValue('L' . $row, 'CLASSIC_TSHIRT');
            $sheet->setCellValue('M' . $row, $order->first_name. ' ' . $order->last_name);
            $sheet->setCellValue('N' . $row, $order->color);
            $sheet->setCellValue('O' . $row, $order->size);
            $sheet->setCellValue('S' . $row, $order->img_1);
            // Thêm các cột khác nếu cần
            $row++;
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
            throw new \Exception('Không tìm thấy token');
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

        $statusCode = $response->getStatusCode(); // Lấy mã trạng thái HTTP
        $body = $response->getBody(); // Lấy nội dung phản hồi

        if ($statusCode == 200) {
            DB::table('orders')->whereIn('id', $ids)->update(['is_push' => 1]);
            $results[$order->order_number] = 'success';
            
        } else {
            $results[$order->order_number] = 'failed';
        }
                
        return $results;
    }

    function pushOrderToHubfulfill($request)
    {
        $results = [];
        $orders = $request->orders;
        foreach($orders as $data) {
            $order = DB::table('orders')->where('id', $data['order_id'])->first();
            $key = $order->order_number .'_'. time();
            $orderData = [
                "order_id" => $key,
                "items" => [
                    [
                        "sku" => "TS002",
                        "quantity" => (int)$order->quantity,
                        // "note" => ,
                        "design" => [
                            "mockup_url" => $order->img_6 ?? ''
                        ]
                    ]
                ],

                "shipping" => [
                    "shipping_name" => $order->first_name .' '. $order->last_name,
                    "shipping_address_1" => $order->address,
                    "shipping_city" => $order->city,
                    "shipping_zip" => $order->zip,
                    "shipping_state" => $order->state,
                    "shipping_country" => $order->country,
                ],
                "shipping_method" => "USUSPSEX"
            ];

            $client = new Client();
            $response = $client->post($this->baseUrlHubfulfill.'/orders', [
                'headers' => [
                    'X-API-KEY' => $this->keyHubfulfill,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $orderData
            ]);        
            if ($response->getStatusCode() == 200){
                DB::table('orders')->where('id', $order->id)->update(['is_push' => 1]);
                $results[$order->order_number] = 'success';
            }else {
                $results[$order->order_number] = 'failed';
            }
        }

        return $results;
        
    }

    function pushOrderToLenful($request)
    {
        $results = [];
        $orders = $request->orders;
        foreach ($orders as $data) {
            $order = DB::table('orders')->where('id', $data['order_id'])->first();
            $sku = $this->getSkuLenful($order->product_name, $order->size, $order->color);
            if ($sku == 0) {
                throw new \Exception('Không tìm thấy biến thể ở order ' . $order->order_number); 
            }
            
            $orderData = [
                "order_number" => "#1". $order->order_number,
                "first_name" => $order->first_name,
                "last_name" => $order->last_name,
                "country_code" => "US",
                "city" => $order->city,
                "zip" => $order->zip,
                "address_1" => $order->address,
                "items" => [ 
                    [
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
                    ]
                ]
                
            ];

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
                return $this->sendError('Unauthorized');
            }
            $resOrder = $client->post($this->baseUrlLenful.'/order/66e024d4682685fd3b9f35d0/create', [
                'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $orderData // Gửi dữ liệu đơn hàng
            ]);
            
            if ($resOrder->getStatusCode() === 200) {
                DB::table('orders')->where('id', $order->id)->update(['is_push' => 1]);
                $results[$order->order_number] = 'success';

            } else {
                $results[$order->order_number] = 'failed';
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
                case 'otb':
                    $result = $this->pushOrderToOtb($request);
                    return $this->sendSuccess($result);
                case 'hubfulfill':
                    $result = $this->pushOrderToHubfulfill($request);
                    return $this->sendSuccess($result);
                case 'lenful':
                    $result = $this->pushOrderToLenful($request);
                    return $this->sendSuccess($result);
                default:
                    return $this->sendError('Function not implemented', 500);
            }
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
            $place_order = $request->place_order;
            foreach ($ids as $id) {
                $data = Order::find($id);
                if (!empty($data)) {
                    if ($type == 1) {
                        $columns = [
                            'is_approval' => true,
                            'approval_by' => Auth::user()->id,
                            'place_order' => $place_order,
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
            throw new \Exception('Không tìm thấy product'); 
        }

        $matchedVariant = array_filter($resFormat['data'][0]['variants'], function($variant) use ($size, $color) {
            
            $option = $variant['option_values'];
            $resultColor = true;
            $resultSize = true;
            

            if (!empty($color)) {
                if (in_array($color, $option)) {
                    $resultColor = true;
                } else {
                    $resultColor = false;
                }
            }

            if (!empty($size)) {
                if (in_array($size, $option)) {
                    $resultSize = true;
                } else {
                    $resultSize = false;
                }
            }

            if ($resultColor && $resultSize) {
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

            $order = DB::table('orders')->where('id', $request->id)->first();

            if (!$order) {
                return $this->sendError('Không tìm thấy order', 404);
            }

            if (!empty($data)) {
                DB::table('orders')->where('id', $request->id)->update($data);
            }
            return $this->sendSuccess('ok');
        } catch (\Throwable $th) {
            return $this->sendError('error'. $th->getMessage(), 500);
        }
        

    }

}
