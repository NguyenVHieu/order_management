<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class MailController extends BaseController
{
    public function fetchOrders() 
    {
        try {
            $client = Client::account('default');
            $client->connect();

            $inbox = $client->getFolder('INBOX');
            $today = Carbon::yesterday();

            $messages = $inbox->query()->subject('Etsy')->since($today)->get();

            if (count($messages) > 0) {
                foreach ($messages as $message) {
                    // Trích xuất thông tin từ email
                    $subject = $message->getSubject();
                    $from = $message->getFrom()[0]->mail;
                    $date = $message->getDate();
                    $emailBody = $this->removeLinks($message->getTextBody());

                    // dd($emailBody);

                    $orderNumberPattern = '/Your order number is:\s*(.*)/';
                    $shippingAddressPattern = '/Shipping address \*(.*?)\*/s';
                    $productPattern = '/Learn about Etsy Seller Protection.*?\n(.*?)(?=\nSize:|\nColors:|\nPersonalization:|\nShop:|\nTransaction ID:|\nQuantity:|\nPrice:|\nOrder total|$)/s';

                    $orderNumber = $this->extractInfo($orderNumberPattern, $emailBody);
                    $shippingAddress = $this->extractInfo($shippingAddressPattern, $emailBody);
                    $productName = $this->extractInfo($productPattern, $emailBody);

                    dd($orderNumber, $shippingAddress, $productName);   
                }
            } else {
                Log::info('No new Etsy emails found for today.');
            }


        
            dd($messages);
        } catch (\Throwable $th) {
            dd($th);
            Log::error('Connection setup failed: ' . $th->getMessage());
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

}
