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
            $parts = explode(" ", $param['name']);

            $firstName = $parts[0];
            $lastName = implode(" ", array_slice($parts, 1));
            $shop = Shop::where('name', str_replace("\r", "", $param['shop']))->first();
            if (empty($shop)) {
                $shop = Shop::create(['name' => $param['shop']])->fresh();  
            }
            if ($param['shipping'] != 'N/A' && $param['shipping'] != '0.00'){
                $shipping = $param['shipping'];
            }else {
                $shipping = 0;
            }
            
            $data = [
                'order_number' => $param['orderNumber'] != 'N/A' ? $param['orderNumber'] : time(),
                'product_name' => $param['product'],
                'shop_id' => $shop->id ?? null,
                'size' => $param['size'] ?? null,
                'blueprint_id' => $param['blueprint_id'] ?? null,
                'style' => $param['style'] != 'N/A' ? $param['style'] : null,
                'color' => $param['color'] != 'N/A' ? $param['color'] : null,
                'personalization' => $param['personalization'] != 'N/A' ? $param['personalization'] : null,
                'thumbnail' => $param['thumb'],
                'quantity' =>  $param['quantity'],
                'sale_tax' => $param['salesTax'] != 'N/A' ? $param['salesTax'] : null,
                'shipping' => $shipping,
                'is_shipping' => (float)$shipping > 0 ? true : false,
                'order_total' => $param['orderTotal'] != 'N/A' ? $param['orderTotal'] : null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'address' => $param['address'],
                'country' => $param['country'],
                'state' => $param['state'] != 'N/A' ? $param['state'] : null,
                'apartment' => $param['apartment'] != 'N/A' ? $param['apartment'] : null,
                'recieved_mail_at' => $param['recieved_mail_at'],
                'zip' => $param['zip'],
                'city' => $param['city'],
                'is_push' => false,
                'is_approval' => false,
                'multi' => $param['multi'], 
                'order_number_group' => $param['orderNumberGroup'] ?? null,
                'category_id' => $param['category_id'] ?? null,
                'im_code' => $param['im'] != 'N/A' ? $param['im'] : null
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
            set_time_limit(-1);
            Helper::trackingInfo('fetchMailOrder start at ' . now());
            $client = \Webklex\IMAP\Facades\Client::account('default');
            $client->connect();

            $inbox = $client->getFolder('INBOX');

            $messages = $inbox->query()->subject('You made a sale on Etsy')->unseen()->get();
            $list_data = [];
            if (count($messages) > 0) {
                foreach ($messages as $message) {
                    try {
                        // Trích xuất thông tin từ email
                        $subject = $message->getSubject();
                        $from = $message->getFrom()[0]->mail;
                        $date = $message->getDate();
                        
                        $emailBody = $this->removeLinks($message->getTextBody());

                        $emailHtml = $message->getHTMLBody();
                        $thumbRegex = '/<img[^>]+src="([^"]+\.jpg)"/';
                        $thumb = $this->extractInfo($thumbRegex, $emailHtml);

                        $patterns = [
                            'orderNumber' => '/http:\/\/www\.etsy\.com\/your\/orders\/\s*(.*)/',
                            'name' => '/<span class=\'name\'>([^<]+)<\/span>/',
                            'address' => '/<span class=\'first-line\'>([^<]+)<\/span>/',
                            'city' => '/<span class=\'city\'>([^<]+)<\/span>/',
                            'state' => '/<span class=\'state\'>([^<]+)<\/span>/',
                            'zip' => '/<span class=\'zip\'>([^<]+)<\/span>/',
                            'apartment' => '/<span class=\'second-line\'>([^<]+)<\/span>/',
                            'country' => '/<span class=\'country-name\'>([^<]+)<\/span>/',
                            'product' => '/Item:\s*(.+)/',
                            'style' => '/Size:\s*(.*)/',
                            'color' => '/(?:Primary color \(Matching with color chart\)|Shirt Colors):\s*(.*)/i',
                            'quantity' => '/Quantity:\s*(.+)/',
                            'salesTax' => '/Sales Tax:\s*\$?(\d+(\.\d{2})?|US)/',
                            'shipping' => '/Shipping:\s*\$?(\d+(\.\d{2})?|US)/',
                            'orderTotal' => '/Order Total:\s*\$?(\d+(\.\d{2})?|US)/',
                            'size' => '/Sizes:\s*(.*)/',
                            'size_blanket' => '/(\d+x\d+)/',
                            'personalization' => '/Personalization:\s*(.*)/',
                            
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

                        $data['recieved_mail_at']  = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
                        $shop = $this->extractInfo('/Shop:\s*(.+)/', $emailHtml, true);
                        $data['im'] = $this->extractInfo('/IOSS number,\s*(IM\d+)/', $emailHtml, true);
                        $getShop = is_array($shop) ? $shop[0] : $shop;
                        $data['shop'] = str_replace("\r", '', $getShop);

                        
                        if (is_array($data['product'])){
                            $countStyle = count($data['product']);
                            for ($i=0; $i < $countStyle; $i++) {
                                $item = [];
                                $item['color'] = $data['color'][$i];
                                $item['personalization'] = $data['personalization'][$i];
                                $item['quantity'] = $data['quantity'][$i]; // Uncomment this line
                                $item['thumb'] = $thumb[$i];
                                $item['product'] = $data['product'][$i];
                                if (stripos($data['product'][$i], 'Blanket') !== false) {
                                    $item['style'] = $data['style'][$i];
                                    $item['size'] = $data['size_blanket'];
                                    $item['blueprint_id'] = $this->getBlueprintId($item['style'] .' '. $item['size']);
                                }else if (stripos($data['product'][$i], 'Flag') !== false){
                                    $item['style'] = $data['style'][$i];
                                    $item['size'] = $data['size'];
                                    $item['blueprint_id'] = $this->getBlueprintId($data['style'] .' '. $data['size']);
                                } else {
                                    $style = $this->extractInfo('/(?:Style|Sizes):\s*(.*)/', $emailBody);
                                    $item['style'] = str_replace("\r", "", $style[$i]);
                                    $item['size'] = $this->getSize($item['style']);
                                    $item['blueprint_id'] = $this->getBlueprintId($item['style']);
                                }
                                $item['category_id'] = DB::table('key_categories')->where('style', $item['style'])->first()->category_id ?? null;
                                
                                $item['multi'] = true;
                                $item['orderNumber'] = $data['orderNumber'].'#'.$i+1;
                                $item['orderNumberGroup'] = $data['orderNumber'];
                                $mergedArray = array_merge($data, $item);
                                $list_data[] = $mergedArray;   
                            }
                            
                        }else {

                            $data['thumb'] = $thumb;
                            if (stripos($data['product'], 'Blanket') !== false) {
                                $data['style'] = $data['style'];
                                $data['size'] = $data['size_blanket'];
                                $data['blueprint_id'] = $this->getBlueprintId($data['style'] .' '. $data['size']);
                            }else if ( stripos($data['product'], 'Flag') !== false){
                                $data['style'] = $data['style'];
                                $data['size'] = $data['size'];
                                $data['blueprint_id'] = $this->getBlueprintId($data['style'] .' '. $data['size']);
                            } else {
                                $style = $this->extractInfo('/(?:Style|Sizes):\s*(.*)/', $emailBody);
                                $data['style'] = str_replace("\r", "", $style);
                                $data['size'] = $this->getSize($data['style']);
                                $data['blueprint_id'] = $this->getBlueprintId($data['style']);
                            }
                            $data['category_id'] = DB::table('key_categories')->where('style', $data['style'])->first()->category_id ?? null;
                            $data['orderNumberGroup'] = $data['orderNumber'];
                            $data['multi'] = false;
                            $list_data[] = $data;
                        }
                        
                        $message->setFlag('SEEN');
                        $client->expunge();
                    } catch (\Throwable $th) {
                        continue;
                        Helper::trackingError('fetchMailOrder child error ' . $th->getMessage());
                    }
                    
                }
                
                $this->getInformationProduct($list_data);
                
            } 
            Helper::trackingInfo('fetchMailOrder end at ' . now());
            return $this->sendSuccess('clone order ok');
        } catch (\Throwable $th) {
            Helper::trackingError('fetchMailOrder error' . $th->getMessage());
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
        return preg_replace('/https+/', '', $body);
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
