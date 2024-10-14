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
use App\Imports\OrderImport;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Net\SFTP;
use finfo;
use Maatwebsite\Excel\Facades\Excel;
use Google\Client as Google_Client;
use Google\Service\Sheets;

class OrderController extends BaseController
{
    protected $shopIdPrintify;
    protected $tokenPrintify;
    protected $tokenMerchize;
    protected $uOtb;
    protected $pOtb;
    protected $uLenful;
    protected $pLenful;
    protected $shopIdLenful;
    protected $tokenHubfulfill;
    protected $uPrivate;
    protected $pPrivate;

    protected $baseUrlHubfulfill;
    protected $baseUrlLenful;
    protected $baseUrlPrivate;
    protected $baseUrlMerchize;
    protected $baseUrlPrintify;
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {   
        $this->baseUrlPrintify = 'https://api.printify.com/v1/';
        $this->baseUrlMerchize = 'https://bo-group-2-2.merchize.com/06umr3e/bo-api';
        $this->baseUrlPrivate = 'https://api.privatefulfillment.com/v1';
        $this->baseUrlHubfulfill = 'https://hubfulfill.com/api';
        $this->baseUrlLenful = 'https://s-lencam.lenful.com/api';
        $this->shopIdPrintify = env('SHOP_ID_PRINTIFY');
        $this->tokenPrintify = env('TOKEN_PRINTIFY');
        $this->tokenMerchize = env('TOKEN_MERCHIZE');
        $this->uOtb = env('U_OTB');
        $this->pOtb = env('P_OTB');
        $this->uLenful = env('U_LENFUL');
        $this->pLenful = env('P_LENFUL');
        $this->uPrivate = env('U_PRIVATE');
        $this->pPrivate = env('P_PRIVATE');
        $this->shopIdLenful = env('SHOP_ID_LENFUL');
        $this->tokenHubfulfill = env('TOKEN_HUBFULFILL');
        $this->orderRepository = $orderRepository;
    }

    function pushOrderToPrintify($data) {
        $results = [];
        foreach($data as $key => $orders) {
            try {
                $key_order_number = $key. time();
                $lineItems = [];
                $info = [];
                $check = true;
                $result = [];
                foreach($orders as $order) {
                    $params = [
                        'blueprint_id' => $order->blueprint_id,
                        'print_provider_id' => $order->print_provider_id,
                        'size' => $order->size, 
                        'color' => $order->color
                    ];
                    $variant_id = $this->getVariantId($params);
                    if ($variant_id == 0) {
                        $check = false;
                        $result[$order->order_number.' '.$order->style.' '.$order->color] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                    }else {
                        $result[$order->order_number.' '.$order->style.' '.$order->color] = 'Success!';
                    }

                    $info[$order->id] = [
                        'variant_id' => $variant_id,
                        'blueprint_id' => $order->blueprint_id,
                        'print_provider_id' => $order->print_provider_id,
                        'place_order' => 'printify',
                        'date_push' => date('Y-m-d'),
                        'is_push' => true,
                        'push_by' => Auth::user()->id,
                        'cost' => 0.00
                    ];

                    $item = [
                        "print_provider_id" =>$order->print_provider_id,
                        "blueprint_id" => $order->blueprint_id,
                        "variant_id" => $variant_id,
                        "print_areas" => [
                            "front" => $order->img_1,
                        ],
                        "quantity" => $order->quantity
                    ];

                    if (!empty($order->img_2)) {
                        $item["print_areas"]["back"] = $order->img_2; // Thêm back nếu có img_2
                    }

                    $lineItems[] = $item;
                }

                if (count($lineItems) > 0 && $check == true) {
                    $country = DB::table('countries')->where('name', $order->country)->first();
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
                            "country" => $country->iso_alpha_2,
                            "region" => $order->state,
                            "address1" => $order->address,
                            "city" => $order->city,
                            "zip" => $order->zip
                        ]
                    ];
                    
                    
                    $client = new Client();
                    $response = $client->post($this->baseUrlPrintify.'shops/'.$this->shopIdPrintify.'/orders.json', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->tokenPrintify,
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
                        $result = [];
                        $result[$key.' '] = 'Lỗi khi tạo order';
                    }
                }
                
            } catch (\Throwable $th) {
                Helper::trackingError($th->getMessage());
                $result = [];
                $result[$key.' '] = "Lỗi khi tạo order";
            }
            
            $results[] = $result;
        }

        return array_merge(...$results);
    }

    function pushOrderToMerchize($data) 
    {
        $results = [];
        foreach($data as $key => $orders) {
            $lineItems = [];
            $items = [];
            $result = [];
            try {
                $key_order_number = $key. time();
                $check = true;
                $condition = [];
                foreach($orders as $order) {
                    $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                    if ($product->merchize == null) {
                        $check = false;
                        $result[$order->order_number.' '.$order->style.' '.$order->color] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                    }else {
                        $result[$order->order_number.' '.$order->style.' '.$order->color] = 'Success!';
                    }

                    $condition[$order->id] = [
                        'is_push' => 1,
                        'date_push' => date('Y-m-d'),
                        'place_order' => 'merchize', 
                        'push_by' => Auth::user()->id,
                        'cost' => 0.00
                    ];

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
                    $items[] = $order->order_number;
                }

                if (count($lineItems) > 0 && $check == true) {
                    $identifier = $key;
                    if (count($items) > 1) {
                        $base = strstr($items[0], '#', true); // Lấy phần trước dấu #
                        $numbers = [];
                        foreach ($items as $item) {
                            $numbers[] = substr($item, strpos($item, '#') + 1); // Lấy phần sau dấu #
                        }
                        $identifier = $base . "#" . implode('_', $numbers);
                    }

                    $client = new Client();
                    $orderData = [
                        "order_id" =>  $key_order_number,
                        "identifier" =>  $identifier,
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
                            'Authorization' => 'Bearer ' . $this->tokenMerchize,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);
                    $res = json_decode($response->getBody()->getContents(), true);
                
                    if ($response->getStatusCode() === 200) {
                        foreach ($condition as $key_user => $data) {
                            $data['order_id'] = $res['data']['_id'];
                            $data['status_order'] = $res['data']['status'];
                            DB::table('orders')->where('id', $key_user)->update($data); 
                        }
                    } else {
                        $result = [];
                        $result[$key.' '] = 'Lỗi khi tạo order';
                    }
                }
            } catch (\Throwable $th) {
                Helper::trackingError($th->getMessage());
                $result = [];
                $result[$key.' '] = 'Lỗi khi tạo order';
            }

            $results[] = $result;
        } 

        return array_merge(...$results);
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
                    'email' => $this->uPrivate,
                    'password' => $this->pPrivate
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
        
        
        $results = [];
        foreach($data as $key => $orders) {
            $lineItems = [];   
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
    
                if ($resOrder->getStatusCode() === 201) {
                    $data = [
                        'is_push' => 1, 
                        'place_order' => 'private',
                        'date_push' => date('Y-m-d'),
                        'push_by' => Auth::user()->id 
                    ];

                    DB::table('orders')->where('id', $order->id)->update($data);
                } else {
                    $results[$key] = 'Failed';
                }
            } catch (\Throwable $th) {
                Helper::trackingError($th->getMessage());
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
            $results= [];
            $check = false;

            foreach ($data as $key_order => $orders) {
                $arr_order_number = array_map(function($order) {
                    return $order->order_number;
                }, $orders);

                if (count($orders) > 0) {
                    if (count($arr_order_number) > 1) {
                        $base = strstr($arr_order_number[0], '#', true); 
                        $numbers = [];
    
                        foreach ($arr_order_number as $item) {
                            $numbers[] = substr($item, strpos($item, '#') + 1);
                        }
    
                        // Ghép lại theo định dạng yêu cầu
                        $key_order_otb = $base . "#" . implode('_', $numbers);
                    } else {
                        $key_order_otb = $orders[0]->order_number;
                    }
                    $writtenRows = [];
                    foreach ($orders as $index => $order) {
                        $ids[] = $order->id;
                        $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                        if (!$product || empty($product->otb)) {
                            $results[$order->order_number. ' '. $order->size. ' '. $order->color] = 'Không tìm thấy product';
                            $check = false;
                            if (count($writtenRows) > 0) {
                                foreach (array_reverse($writtenRows) as $writtenRow) {
                                    $sheet->removeRow($writtenRow, 1);
                                    $row = $writtenRow;
                                }
                            }
                            break;
                        }

                        if (array_key_exists($product->otb, $arr_type)) {
                            $felix_size = $arr_type[$product->otb];
                        }else {
                            $felix_size = null;
                        }
                        $sizeFormat = $felix_size ? $felix_size.$order->size : $order->size;
                        if ($product->otb == 'TODDLER_TSHIRT' && $order->size == '5T-6T') {
                            $sizeFormat = '5|6';
                        }

                        $sheet->setCellValue('A' . $row, $key_order_otb); // Cột A
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
                        $sheet->setCellValue('O' . $row, $sizeFormat);
                        $sheet->setCellValue('S' . $row, $order->img_1);
                        $writtenRows[] = $row;
                        $row++;
                        $check = true;
                    }
                }
            }

            if ($check) {
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($outputFileOtb);

                $client = new Client();

                $resLogin = $client->request('POST', 'https://otbzone.com/bot/api/v1/auth/authenticate', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'password' => $this->pOtb,
                        'rememberMe' => false,
                        'username' => $this->uOtb,
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
                $statusCode = $response->getStatusCode(); // Lấy mã trạng thái HTTP
    
                if ($statusCode == 200) {
                    $data = [
                        'is_push' => 1, 
                        'place_order' => 'otb',
                        'date_push' => date('Y-m-d')
                    ];
                    DB::table('orders')->whereIn('id', $ids)->update($data);
                    $results['1'] = "Order OTB Success";
                    
                } else {
                    $results['1'] =  "Order OTB Failed";
                }
            }
            return $results;
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
            return [1 => "Order OTB Failed"];
        }      
    }

    function pushOrderToHubfulfill($data)
    {
        $results = [];
        foreach($data as $key => $orders) {
            try {
                $lineItems = $result = $info = $arr_order_number = [];
                $arr_shippng = config('constants.shipping_hubfulfill');
                $check = true;
                foreach ($orders as $order) {
                    if ($order->color != null) {
                        $product = DB::table('hubfulfill_products')->where('style', $order->style)->where('color', $order->color)->first();
                    } else {
                        $product = DB::table('hubfulfill_products')->where('style', $order->style)->first();
                    }
                    
                    if ($product->sku == null) {
                        $check = false;
                        $result[$order->order_number.' '.$order->style.' '.$order->color] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                    }else {
                        $result[$order->order_number.' '.$order->style.' '.$order->color] = 'Success!';
                    }

                    $lineItems[] = [
                        "sku" => $product->sku,
                        "quantity" => (int)$order->quantity,
                        "design" => [
                            "mockup_url" => $order->img_6 ?? ''
                        ]
                    ];

                    $info[$order->id] = [
                        'place_order' => 'hubfulfill',
                        'is_push' => 1,
                        'date_push' => date('Y-m-d'),
                        'push_by' => Auth::user()->id
                    ];

                    $arr_order_number[] = $order->order_number;
                }

                $shipping_method = $arr_shippng[$order->country] ?? '';
                if ($shipping_method == '') {
                    $results[$key. ''] = 'Không tìm thấy phương thức vận chuyển';
                    continue;
                }

                if (count($lineItems) > 0 && $check == true) {
                    $client = new Client();
                    $resCountry = $client->post('https://countriesnow.space/api/v0.1/countries/states', [
                        'headers' => [
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => [
                            'country' => $order->country
                        ]
                    ]);   
                    $resCountry = json_decode($resCountry->getBody()->getContents(), true);
                    if ($resCountry['error'] == false && count($resCountry['data']['states']) > 0) {
                        $state = $order->state;
                        $state = collect($resCountry['data']['states'])->first(function ($item) use ($state) {
                            return strtoupper($item['state_code']) === strtoupper($state);
                        });
                    }
                        
                    $orderData = [
                        "order_id" => (string)$key. time(),
                        "items" => $lineItems,
                        "shipping" => [
                            "shipping_name" => $order->first_name .' '. $order->last_name,
                            "shipping_address_1" => $order->address,
                            "shipping_city" => $order->city,
                            "shipping_zip" => $order->zip,
                            "shipping_state" => $state['name'],
                            "shipping_country" => $order->country,
                        ],
                        "shipping_method" => $shipping_method
                    ];

                    $response = $client->post($this->baseUrlHubfulfill.'/orders', [
                        'headers' => [
                            'X-API-KEY' => $this->tokenHubfulfill,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $orderData
                    ]);        
                    $resOrder = json_decode($response->getBody()->getContents(), true);
                    if ($response->getStatusCode() == 200){
                        $first = true;
                        foreach ($info as $key_order =>  $data) {
                            $orderId = $resOrder['order_id'];
    
                            $data['order_id'] = $orderId;
                            $data['tracking_order'] = $resOrder['tracking_number'];

                            if ($first) {
                                $data['cost'] = $resOrder['total'];
                                $first = false;
                            }
    
                            DB::table('orders')->where('id', $key_order)->update($data);
                        }
                    }else {
                        $result = [];
                        $result[$key. ' '] = 'Lỗi khi tạo order';
                    }
                }
            } catch (\Throwable $th) {
                Helper::trackingError($th->getMessage());
                $result = [];
                $result[$key. ' '] = 'Lỗi khi tạo order';
            }

            $results[] = $result;
        }

        return array_merge(...$results);
        
    }

    function pushOrderToLenful($data)
    {
        $results = [];
    
        foreach ($data as $key => $orders) {
            try {
                $check = true;
                $lineItems = [];
                $items = [];
                $info = [];
                $result = [];
                foreach ($orders as $order) {
                    $product = DB::table('key_blueprints')->where('style', $order->style)->first();
                    $sku = $this->getSkuLenful($product->lenful, $order->size, $order->color);
                    if ($sku == 0) {
                        $result[$order->order_number.' '. $order->size. ' '. $order->color] = 'Order hết màu, hết size hoặc không tồn tại SKU. Vui lòng kiểm tra lại';
                        $check = false;
                    }else {
                        $result[$order->order_number.' '. $order->size. ' '. $order->color] = 'Sucess';
                    }

                    $info[$order->id] = [
                        'place_order' => 'lenful',
                        'is_push' => 1,
                        'date_push' => date('Y-m-d'),
                        'push_by' => Auth::user()->id,
                        'cost' => 0.00
                    ];

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
                    $items[] = $order->order_number;
                }

                if (count($lineItems) > 0 && $check == true) {
                    try {
                        $client = new Client();
                        $resLogin = $client->post($this->baseUrlLenful.'/seller/login', [
                            'form_params' => [
                                'user_name' => $this->uLenful,
                                'password' => $this->pLenful,
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

                    $identifier = $key;
                    if (count($items) > 1) {
                        $base = strstr($items[0], '#', true); // Lấy phần trước dấu #
                        $numbers = [];
                        foreach ($items as $item) {
                            $numbers[] = substr($item, strpos($item, '#') + 1); // Lấy phần sau dấu #
                        }
                        $identifier = $base . "#" . implode('_', $numbers);
                    }
                    $country = DB::table('countries')->where('name', $order->country)->first();
                    $orderData = [
                        "order_number" => (string)$identifier,
                        "first_name" => $order->first_name,
                        "last_name" => $order->last_name,
                        "country_code" => $country->iso_alpha_2,
                        "city" => $order->city,
                        "zip" => $order->zip,
                        "address_1" => $order->address,
                        "items" => array_values($lineItems)
                    ];

                    $resOrder = $client->post($this->baseUrlLenful.'/order/'.$this->shopIdLenful.'/create', [
                        'headers' => [
                                'Authorization' => 'Bearer ' . $token,
                                'Content-Type'  => 'application/json',
                            ],
                            'json' => $orderData // Gửi dữ liệu đơn hàng
                    ]);
                    $res = json_decode($resOrder->getBody()->getContents(), true);
                    if ($resOrder->getStatusCode() === 200) {
                        $first = true;
                        foreach($info as $key_order_id => $data) {
                            $data['status_order'] = $res['data']['status'];
                            $data['order_id'] = $res['data']['id'];
                            if ($first) {
                                $data['cost'] = $res['data']['total_price'];
                                $first = false;
                            }
                            DB::table('orders')->where('id', $key_order_id)->update($data);
                        }
    
                    } else {
                        $result = [];
                        $result[$key.' '] = 'Lỗi khi tạo order';
                    }
                }
            } catch (\Throwable $th) {
                Helper::trackingError($th->getMessage());
                $result = [];
                $result[$key.' '] = 'Lỗi khi tạo order';
            }
        }

        $results[] = $result;

        return array_merge(...$results);
    }

    public function getOrderDB(Request $req) 
    {
        try {
            $userType = Auth::user()->is_admin ? -1 : Auth::user()->user_type_id;
            $shopId = Auth::user()->shop_id;

            $params = [
                'userType' => $userType,
                'shopId' => $shopId,
                'type' => $req->type,
                'userId' => Auth::user()->id,
                'dateOrderFrom' => $req->date_order_from,
                'dateOrderTo' => $req->date_order_to,
            ];
            $columns = [
                'orders.*',
                'shops.name as shop_name',
                'orders.id as order_id', 
                'users.name as seller'
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
            Helper::trackingError($th->getMessage());
            return $this->sendError('error', 500);
        }
    }

    public function getProviders($blueprint_id, $order_id)
    {
        try {
            if ($blueprint_id != -1) {
                $client = new Client();

                $response = $client->get($this->baseUrlPrintify. "catalog/blueprints/{$blueprint_id}/print_providers.json", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->tokenPrintify,
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
            Helper::trackingError($th->getMessage());
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
                $order_number_format = $order->order_number;
                if (strpos($order_number_format, '#') !== false) {
                    $order_number_format = strstr($order_number_format, '#', true); 
                }
                $platform = $placeOrder != null  ? $placeOrder : $order->place_order;
                if (!empty($data['blueprint_id'])) {
                    $order->blueprint_id = $data['blueprint_id'];
                }

                if (!empty($data['print_provider_id'])) {
                    $order->print_provider_id = $data['print_provider_id'];
                }

                $datas[$platform][$order_number_format][] = $order;
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
                    case 'interest_print':
                        $results[] = $this->appendSheetFlag($data);
                        break;
                    default:
                        return $this->sendError('Không tìm thấy nơi đặt hàng', 404);
                        break;   
                }

                return $this->sendSuccess($results);
            
            
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
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
            Helper::trackingError($ex->getMessage());
            return $this->sendError('error'. $ex->getMessage(), 500);
        }
        
    }

    public function storeBlueprint()
    {
        $client = new Client();
        $response = $client->get($this->baseUrlPrintify. "/catalog/blueprints.json", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->tokenPrintify,
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
            Helper::trackingError($th->getMessage());
            return $this->sendError($th->getMessage());
        }
    }

    public function getVariantId($params)
    {   
        $blueprint_id = $params['blueprint_id'];
        $provider_id = $params['print_provider_id'];
        $size = $params['size'];
        $color = $params['color'];
        $client = new Client();
        $resVariant = $client->get($this->baseUrlPrintify. "/catalog/blueprints/{$blueprint_id}/print_providers/{$provider_id}/variants.json", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->tokenPrintify,
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
                DB::table('orders')->where('order_number_group', $order->order_number_group)->update($data);
            }else {
                DB::table('orders')->where('id', $request->id)->update($data);
            }
            
            return $this->sendSuccess('ok');
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
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
            Helper::trackingError($th->getMessage());
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
            Helper::trackingError($th->getMessage());
            return $this->sendError('Hiển thị đơn hàng thất bại', 500);
        }
    }

    public function appendSheetFlag($data){
        try{
            $orders = collect($data)->flatten(1);
            $spreadsheetId = '1GI6UwF1LQEBeqg9dqfG8KO5WwTuKM5bRiqqc00ASGFE';
            $range = 'Custom Flags, Banners, Yard Signs!A:A';
            $ids = [];
            foreach ($orders as $order) {
                $ids[] = $order->id;
            }
            $key = '';
            $values = $orders->map(function($item) use(&$key) {
                $seller = DB::table('users')->where('id', $item->approval_by)->first();
                $shop = DB::table('shops')->where('id', $item->shop_id)->first();
                if (!$shop || !$seller) {
                    return ['1' => 'Không tìm thấy shop hoặc seller'];
                }

                $key .= ($key ? '_' : '') . $item->order_number;
                return [
                    date('d/m/Y'),
                    '#'.$item->order_number,
                    $item->quantity,
                    $item->size,
                    $item->img_1,
                    $item->first_name. ' ' . $item->last_name,
                    $item->address,
                    $item->city,
                    $item->zip,
                    $item->state,
                    $item->country,
                    $item->img_6,
                    '',
                    '',
                    $seller->name,
                    $shop->name
                ];
            })->toArray();

            $client = new Google_Client();
            $client->setApplicationName('Laravel Google Sheets Integration');
            $client->addScope(Sheets::SPREADSHEETS);            
            $client->setAuthConfig(public_path('toolorder.json'));
            $client->setAccessType('offline');

            $service = new Sheets($client);

            $body = new \Google\Service\Sheets\ValueRange([
                'values' => $values,
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $d = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
            $data = [
                'is_push' => 1,
                'push_by' => Auth::user()->id,
                'date_push' => date('Y-m-d'),
                'status_order' => 'pushed',
                'place_order' => 'interest_print'
            ];

            DB::table('orders')->whereIn('id', $ids)->update($data);
            return [$key => 'Success'];
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
            return [$key => 'Push order cờ thất bại'];
        }
    }
}
