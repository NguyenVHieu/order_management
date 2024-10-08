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
            if (empty($shop)) {
                $shop = Shop::create(['name' => $param['shop']])->fresh();
            }
            
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
                'is_push' => false,
                'is_approval' => false,
                'multi' => $param['multi'], 
                'order_number_group' => $param['orderNumberGroup'] ?? null           
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

            $messages = $inbox->query()->subject('You made a sale on Etsy')->unseen()->get();
            
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
                        'size_blanket' => '/(\d+x\d+)/',
                        'orderNumber' => '/Your order number is:\s*(.*)/',
                        'shippingAddress' => '/Shipping address\s*(.*?)\s*(?=USPS®|Shipping internationally|\z)/s',
                        'product' => '/Learn about Etsy Seller Protection.*?\n(.*?)(?=\nStyle:|\nPrimary color (Matching with color chart):|\nPersonalization:|\nShop:|\nTransaction ID:|\nQuantity:|\nPrice:|\nOrder total|$)/s',
                        'product_multi' => '/([^\n<]+(?:\n[^\n<]+)*)/',
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

                    $data['shippingAddress'] = explode("\n", str_replace("\r", "", trim($data['shippingAddress'])));
                    
                    $filteredArray = array_filter($data['shippingAddress'], function($item) {
                        return strpos($item, '*') === false; // Giữ lại các phần tử không có ký tự '*'
                    });
                    $data['shippingAddress'] = array_values($filteredArray);
                    $data['recieved_mail_at']  = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
                    $data['shop'] = is_array($data['shop']) ? $data['shop'][0] : $data['shop'];
                    if (is_array($data['style'])){
                        $countStyle = count($data['style']);
                        $data['product_multi']  = $this->getProductMulti($data['product_multi'], $countStyle);
                        for ($i=0; $i < $countStyle; $i++) {
                            $item = [];
                            $item['style'] = $data['style'][$i];
                            $item['color'] = $data['color'][$i];
                            $item['personalization'] = $data['personalization'][$i];
                            $item['price'] = $data['price'][$i];
                            $item['quantity'] = $data['quantity'][$i]; // Uncomment this line
                            $item['thumb'] = $thumb[$i];
                            $item['product'] = $data['product_multi'][$i];
                            if (stripos($data['product_multi'][$i], 'Blanket') !== false) {
                                $item['size'] = $data['size_blanket'];
                            }else {
                                $item['size'] = $this->getSize($item['style']);
                            }
                            $item['blueprint_id'] = $this->getBlueprintId($item['style']);
                            $item['multi'] = true;
                            $item['orderNumber'] = $data['orderNumber'].'#'.$i+1;
                            $item['orderNumberGroup'] = $data['orderNumber'];
                            $mergedArray = array_merge($data, $item);
                            $list_data[] = $mergedArray;   
                        }
                        
                    }else {
                        $data['product'] = str_replace(['<', "\n"], '', $data['product']);
                        if (stripos($data['product'], 'Flag') !== false) {
                            continue;
                        }
                        $data['thumb'] = $thumb;
                        if (stripos($data['product'], 'Blanket') !== false) {
                            $data['size'] = $data['size_blanket'];
                        }else {
                            $data['size'] = $this->getSize($data['style']);
                        }
                        $data['blueprint_id'] = $this->getBlueprintId($data['style']);
                        $data['orderNumberGroup'] = $data['orderNumber'];
                        $data['multi'] = false;
                        $list_data[] = $data;
                    }

                    $message->setFlag('SEEN');
                    $client->expunge();
                }
                $this->getInformationProduct($list_data);
                
            } 
            Helper::trackingInfo('fetchMailOrder end at ' . now());
            return $this->sendSuccess('clone order ok');
        } catch (\Throwable $th) {
            Helper::trackingInfo('fetchMailOrder error' . $th->getMessage());
            return $this->sendError($th->getMessage(), 500);
        }
    }

    private function extractInfo($pattern, $body, $singleLine = false)
    {
        $options = $singleLine ? 's' : '';
        preg_match_all($pattern, $body, $matches);
        if (isset($matches[1]) && count($matches[1]) > 1) {
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

    public function getProductMulti($data, $count) {
        $check = 'Sell with confidence Learn about Etsy Seller Protection';
    
        $value = [];

        for($i=0 ; $i< count($data); $i++) {
            $data_val = trim(str_replace("\n", " ", $data[$i]));
            if ($data_val === $check)
            {
                $key = $i;
                break;
            }
        }

        if ($key !== null) {
            for ($i = $key + 2; count($value) < $count; $i += 2) {
                if (isset($data[$i]) && count($value) <= $count) {
                    $value[] = str_replace("\n", "", $data[$i]);
                } else {
                    break;
                }
            }
        }

        return $value;
    }

    

}
