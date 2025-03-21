<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\Order;
use Carbon\Carbon;
use Google\Service\Drive\Drive;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;

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
            // if ($status == 'on-hold') {
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
            // } 
            
            DB::table('orders')->where('order_id', $order_id)->update(['status_order' => $status]);
            Helper::trackingInfo('Webhook cập nhật status thành công');

        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi updateStatusOrderPrintify ' . json_encode($th->getMessage()));
        }
    }

    public function updateTrackingNumberPrintify(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook:' . json_encode($request->all()));
            $resource = $request['resource'];
            $order_id = $resource['id'];
            $tracking_number = $resource['data']['carrier']['tracking_number'];
            $code_tracking = $resource['data']['carrier']['code'];
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_order' => $code_tracking.'.'.$tracking_number]);
            Helper::trackingInfo('Webhook cập nhật tracking number thành công');

        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi updateTrackingNumberPrintify ' . json_encode($th->getMessage()));
        }
    }

    public function updateTrackingNumberLenful(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Lenful:' . json_encode($request->all()));
            $order_id = $request->lenful_order_short_id;
            $tracking_number = $request->tracking_numbers[0];
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_order' => $tracking_number]);
            Helper::trackingInfo('Webhook cập nhật tracking number lenful thành công');

        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi updateTrackingNumberLenful' . json_encode($th->getMessage()));
    }
    }

    public function updateTrackingNumberMerchize(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Tracking Merchize:' . json_encode($request->all()));
            $tracking_order = $request['resource']['tracking_number'];
            $code_tracking = $request['resource']['tracking_company'];
            $order_id = $request['resource']['name'];
            DB::table('orders')->where('order_id', $order_id)->update(['tracking_order' =>$code_tracking.'.'.$tracking_order]);
            Helper::trackingInfo('Webhook cập nhật tracking number merchize này');
        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi updateTrackingNumberMerchize ' . json_encode($th->getMessage()));
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
            Helper::trackingError('Lỗi createOrderMerchize' . json_encode($th->getMessage()));
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
            Helper::trackingError('Lỗi progressOrderMerchize' . json_encode($th->getMessage()));
        }
    }

    public function orderPaymentMerchize(Request $request){
        try {
            Helper::trackingInfo('Body Webhook Payment Order Merchize:' . json_encode($request->all()));
            $cost = $request['resource']['price'];
            $order_id = $request['resource']['order_code'];
            $order = Order::where('order_id', $order_id)->first();

            if ($order) {
                $order->cost = $cost;
                $order->save();
                Helper::trackingInfo('Cập nhật cost Order Merchize thành công');
            }else {
                Helper::trackingInfo('Không timg thấy order');
            }
        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi orderPaymentMerchize' . json_encode($th->getMessage()));
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
                    'password' => env('P_OTB'),
                    'rememberMe' => false,
                    'username' => env('U_OTB'),
                ],
            ]);

            $resLoginConvert = json_decode($resLogin->getBody()->getContents(), true);
            $token = trim($resLoginConvert['data']['accessToken']['token']) ?? null;
            
            if (!$token) {
                return ['401' => 'Đăng nhập OTB không thành công'];
            }
            $currentTimestampInMilliseconds = now()->timestamp * 1000;
            $sevenDaysAgoTimestampInMilliseconds = now()->subDays(10)->timestamp * 1000;

            $response = $client->request('POST', 'https://otbzone.com/bot/api/v1/data-lists', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                
                'json' => [
                    'model' => 'order',
                    'filters' => [
                        [
                            'field' => 'createdAt',
                            'operation' => 'between',
                            'value' => [$sevenDaysAgoTimestampInMilliseconds, $currentTimestampInMilliseconds],
                            'dayAgo' => 7,
                        ],
                    ],
                    'filterType' => 'AND',
                    'sorting' => [
                        'field' => 'createdAt',
                        'direction' => 'asc',
                    ],
                    'pagination' => [
                        'page' => 1,
                        'pageSize' => 1000,
                    ],
                    'filtersRef' => [],
                ],
            ]);

            $res = json_decode($response->getBody()->getContents(), true);

            $data = $res['data']['data'];
            foreach($data as $order) {
                $tracking = !empty($order['trackingCodes'][0]['trackingCode']) ? $order['trackingCodes'][0]['trackingCode'] : null;
                $code_tracking = !empty($order['trackingCodes'][0]['trackingCarrierCode']) ? $order['trackingCodes'][0]['trackingCarrierCode'] : null;
                $data = [
                    'order_id' => $order['id'],
                    'status_order' => $order['orderSellerStatus'] != '' ? $order['orderSellerStatus'] : null,
                    'tracking_order' => $code_tracking != null ? $code_tracking . '.' . $tracking : $tracking,
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
                
                $value = DB::table('orders')->whereIn('order_number', $arr_order_number)->where('place_order', 'otb')->first();
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
            Helper::trackingError('Cập nhật order OTB thất bại: '. $th->getMessage());
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
                        $order->status_order = $data['status'];
                        $order->save();
                    }
                }
            }
            
            Helper::trackingInfo('Cập nhật order lenful thành công!');

        } catch (\Throwable $th) {
            Helper::trackingError('Cập nhật order lenful thất bại' . $th->getMessage());
        }
    }

    public function updateOrderHubfulfill()
    {
        try {
            $arrayShipping = config('constants.shipping_hubfulfill');
            $toDate = Carbon::now()->format('Y-m-d');
            $fromDate = Carbon::now()->subDays(10)->format('Y-m-d');

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
                if ($order) {
                    $order->status_order = $res['status'];
                    $order->tracking_order = $res['original_tracking_number'] ?? null;
                    $order->cost = $res['total'];
                    $order->save();
                }else {
                    Helper::trackingInfo('Không timg thấy order');
                }
                
            }

            Helper::trackingInfo('Cập nhật order hubfulfill thành công!');

        } catch (\Throwable $th) {
            Helper::trackingError('Cập nhật order hubfulfill thất bại:'. $th->getMessage());
        }
    }

    function backupDB()
    {
        // Thông tin kết nối từ file .env
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        $dbHost = env('DB_HOST');
        $dbPort = env('DB_PORT');
    
        $backupPath = storage_path('backups');
    
        // Tạo thư mục lưu trữ nếu chưa tồn tại
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }
    
        // Đường dẫn lưu trữ file backup
        $backupFile = $backupPath . '/' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';
        $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    
        // Kiểm tra xem mysqldump có tồn tại không
        if (!File::exists($mysqldumpPath)) {
            Helper::trackingError('Không tìm thấy mysqldump. Vui lòng kiểm tra đường dẫn.');
        }
    
        // Lệnh để thực hiện sao lưu cơ sở dữ liệu
        $command = sprintf(
            '"%s" --user=%s --password=%s --host=%s --port=%d %s > "%s"',
            escapeshellarg($mysqldumpPath),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            (int)$dbPort,  // Chuyển đổi cổng sang số nguyên
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
    
        try {
            // Thực thi lệnh
            $output = [];
            $resultCode = null;
            exec($command, $output, $resultCode);
    
            if ($resultCode !== 0) {
                throw new \Exception("Lỗi khi thực hiện sao lưu cơ sở dữ liệu. Mã lỗi: " . $resultCode);
            }
    
            Helper::trackingInfo('Backup DB thành công');
        } catch (\Exception $e) {
            Helper::trackingError('loi khi backup db');
        }

        $this->uploadToGoogleDrive($backupFile);
    }

    function uploadToGoogleDrive($backupFile)
    {
        try {
            // Tạo client Google
            $client = new \Google\Client();
            $client->setAuthConfig(public_path('credentials.json'));
            $client->addScope(\Google\Service\Drive::DRIVE_FILE);
            $folderId = '1ojs1PnTUMQNBzaORiR8UdtBcpmRDVVIE';

            // Khởi tạo service Google Drive
            $service = new \Google\Service\Drive($client);
            // Metadata của file
            $fileMetadata = new \Google\Service\Drive\DriveFile([
                'name' => basename($backupFile), // Tên file trên Google Drive
                'parents' => [$folderId],
            ]);

            // Nội dung file
            $content = file_get_contents($backupFile);

            // Upload file lên Google Drive
            $file = $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/sql', // Định dạng file
                'uploadType' => 'multipart',
                'fields' => 'id' // Lấy ID của file sau khi upload
            ]);

            // Log thành công
            Helper::trackingInfo('File đã được upload lên Google Drive. File ID: ' . $file->id);
        } catch (\Exception $e) {
            Helper::trackingError('Lỗi khi upload file lên Google Drive: ' . $e->getMessage());
        }
    }

    public function updateOrderPrivate(Request $request)
    {
        try {
            $requests = $request->all();
            
            Helper::trackingInfo('Body Webhook Tracking Private:' . json_encode($request->all()));
            if (!empty($requests))
            {
                foreach($requests as $request) {
                    $tracking = $request['tracking'] ?? null;
                    $status = $request['orderStatus'] ?? null;
                    $order_id = $request['id'];
                    DB::table('orders')->where('order_id', $order_id)->update(['tracking_order' => $tracking, 'status_order' => $status]);  
                }
            }
            
            Helper::trackingInfo('Webhook cập nhật tracking number Private này');
        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi Webhook Tracking Private' . json_encode($th->getMessage()));
        }
    }

    public function webhookTiktok(Request $request)
    {
        Helper::trackingInfo('Body Webhook Tiktok:' . json_encode($request->all()));
    }

    public function webhookGearment(Request $request)
    {
        try {
            Helper::trackingInfo('Body Webhook Gearment:' . json_encode($request->all()));
            $order_id = $request->data['order_id'] ?? null;
            $status = $request->data['ord_status'] ?? null;
            $tracking_number = $request->data['tracking_number'] ?? null;
            $tracking_company = $request->data['tracking_company'] ?? null;

            $order = Order::where('order_id', $order_id)->first();
            if ($order) {
                $order->status_order = $status;
                $order->tracking_order = $tracking_number != null ? $tracking_company.'.'.$tracking_number : null;
                $order->save();
            }else {
                Helper::trackingInfo('Không timg thấy order');
            }
            Helper::trackingInfo('Webhook cập nhật Gearment thành công');

        } catch (\Throwable $th) {
            Helper::trackingError('Lỗi Webhook Gearment' . json_encode($th->getMessage()));
        }        
    }

}