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

class MailController extends BaseController
{

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
                'shop_id' => $shop->id ?? null,
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
                        // 'transactionID' => '/Transaction ID:\s*(\d+)/',
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
                            if (is_array($data[$key]) && $key != 'shop') {
                                $data[$key]  = json_encode(array_values($data[$key]));
                            }
                        }
                        $data[$key] = str_replace(["\r", "\\r"], "", $data[$key]);
                        
                        
                    }
                    $data['shippingAddress'] = explode("\n", str_replace("\r", "", trim($data['shippingAddress'])));
                    $data['product'] = str_replace(['<', "\n"], '', $data['product']);
                    $data['thumb'] = is_array($thumb) ? json_encode(array_values($thumb)) : $thumb;
                    $data['recieved_mail_at']  = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
                    $data['shop'] = $data['shop'][0] ?? 'N/A';
                    $list_data[] = $data;

                }

                $this->getInformationProduct($list_data);
                
                return $this->sendSuccess('clone order ok');
            } 
        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError($th->getMessage());}
    }

    private function extractInfo($pattern, $body, $singleLine = false)
    {
        $options = $singleLine ? 's' : '';
        preg_match_all($pattern, $body, $matches);
        if (count($matches[1]) > 1) {
            return $matches[1] ?? 'N/A';
        } else {
            return $matches[1][0] ?? 'N/A';
        }
    }

    private function removeLinks($body)
    {
        return preg_replace('/http[^\s]+/', '', $body);
    }

    

}
