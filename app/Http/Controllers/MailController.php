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
                'size' => $param['size'] ?? null,
                'blueprint_id' => $param['blueprint_id'] ?? null,
                'style' => $param['style'] != 'N/A' ? $param['style'] : null,
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
                'multi' => $param['multi'],            
            ];

            $order = DB::table('orders')->where('order_number', $data['order_number'])
                                        ->where('style', $data['style'])
                                        ->where('color', $data['color'])
                                        ->first();
            if (empty($order)) {
                DB::table('orders')->insert($data);
            }
        }
    }

    public function fetchMailOrder()
    {
        try {
            Helper::trackingInfo('fetchMailOrder start at ' . now());
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
                        'shippingAddress' => '/Shipping address\s*(.*?)\s*(?=USPS®|Shipping internationally|\z)/s',
                        'product' => '/Learn about Etsy Seller Protection.*?\n(.*?)(?=\nStyle:|\nPrimary color (Matching with color chart):|\nPersonalization:|\nShop:|\nTransaction ID:|\nQuantity:|\nPrice:|\nOrder total|$)/s',
                        'style' => '/Style:\s*(.*)/',
                        'color' => '/Primary color \(Matching with color chart\):\s*(.*)/i',
                        'personalization' => '/Personalization:\s*(.*)/',
                        'shop' => '/Shop:\s*(.*?)(?=\n|$)/i',
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
                        $data[$key] = str_replace(["\r", "\\r"], "", $data[$key]);
                    }
                    // dd($data);

                    $data['shippingAddress'] = explode("\n", str_replace("\r", "", trim($data['shippingAddress'])));
                    
                    $filteredArray = array_filter($data['shippingAddress'], function($item) {
                        return strpos($item, '*') === false; // Giữ lại các phần tử không có ký tự '*'
                    });
                    $data['shippingAddress'] = array_values($filteredArray);
                    $data['recieved_mail_at']  = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
                    $data['shop'] = is_array($data['shop']) ? $data['shop'][0] : $data['shop'];
                    $data['product'] = str_replace(['<', "\n"], '', $data['product']);
                    

                    if (is_array($data['style'])){
                        $countStyle = count($data['style']);
                        for ($i=0; $i < $countStyle; $i++) {
                            $item = [];
                            $item['style'] = $data['style'][$i];
                            $item['color'] = $data['color'][$i];
                            $item['personalization'] = $data['personalization'][$i];
                            $item['price'] = $data['price'][$i];
                            $item['quantity'] = $data['quantity'][$i]; // Uncomment this line
                            $item['thumb'] = $thumb[$i];
                            $item['size'] = $this->getSize($item['style']);
                            $item['blueprint_id'] = $this->getBlueprintId($item['style']);
                            $item['multi'] = true;
                            $mergedArray = array_merge($data, $item);
                            $list_data[] = $mergedArray;   
                        }
                        
                    }else {
                        $data['thumb'] = $thumb;
                        $data['size'] = $this->getSize($data['style']);
                        $data['blueprint_id'] = $this->getBlueprintId($data['style']);
                        $data['multi'] = false;
                        $list_data[] = $data;
                    }

                    // $data['thumb'] = is_array($thumb) ? json_encode(array_values($thumb)) : $thumb;
                    
                    
                    // $size = $this->getSize($data['style']);
                    // $blueprint_id = $this->getBlueprintId($data['style']);
                    // $data['style'] = is_array($data['style']) ? json_encode(array_values($data['style'])) : $data['style'];
                    // $data['size'] = is_array($size) ? json_encode(array_values($size)) : $size;
                    // $data['blueprint_id'] = is_array($blueprint_id) ? json_encode(array_values($blueprint_id)) : $blueprint_id;

                    // $list_data[] = $data;

                }

                $this->getInformationProduct($list_data);
                Helper::trackingInfo('fetchMailOrder end at ' . now());
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

    public function getSize($style) 
    {
        $size = explode(" ", $style);
        return $size[count($size) -1];
    }

    public function getBlueprintId($style)
    {
        $blueprint = DB::table('key_blueprints')->where('style', $style)->first();
        return $blueprint->product_printify_id ?? null;
    }

    

}
