<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;


class TiktokController extends BaseController
{
    private function calculateSign($secret, $params, $path, $body = '')
    {
        // Sort the parameters by key
        ksort($params);

        // Construct the signature string
        $signString = $secret . $path;
        foreach ($params as $key => $value) {
            $signString .= $key . $value;
        }
        $signString .= $body . $secret;

        // Calculate HMAC-SHA256 signature
        return hash_hmac('sha256', $signString, $secret);
    }

    // Function to make the API request
    public function getShop()
    {
        // Set API parameters
        $appKey = '6dt7tmoe7fqrt';
        $secret = '9bf8ddd21afa7856df185607332110ee937c940d';
        $accessToken = 'ROW_M879GwAAAADU2D_Gn5T-R45y4rQ3nKeQjsQ85YGkSrWEKvvPpkdSQ0eUc1PuBdE02M0o7YAB_bM_F5s1v3YgmY7Hx5h3hTnh6ccdQ6qQzPSBWQEG-PR53IHEbU5RkTZj5P30PNzd544';
        $timestamp = time() - 100;

        // Set query parameters
        $params = [
            'app_key'   => $appKey,
            'timestamp' => $timestamp,
        ];

        // API endpoint path
        $path = '/authorization/202309/shops';

        // Calculate the signature
        $sign = $this->calculateSign($secret, $params, $path);

        // Add the sign to the parameters
        $params['sign'] = $sign;

        // Build the API URL with query parameters
        $queryString = http_build_query($params);
        $url = "https://open-api.tiktokglobalshop.com$path?$queryString";

        // Create a Guzzle client
        $client = new Client();

        try {
            // Make the GET request with Guzzle
            $response = $client->request('GET', $url, [
                'headers' => [
                    'x-tts-access-token' => $accessToken,
                ]
            ]);

            // Get the response body
            $body = $response->getBody()->getContents();

            // Return the response (or handle it as needed)
            return response()->json([
                'success' => true,
                'data' => json_decode($body),
            ]);
        } catch (\Exception $e) {
            // Handle the error
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function refreshToken()
    {
        // Set API parameters
        $appKey = '6dt7tmoe7fqrt';
        $appSecret = '9bf8ddd21afa7856df185607332110ee937c940d';
        $refreshToken = 'ROW_qm8VtQAAAACyqnSIpgCfVBWYvTXqBSLaeBLIIe7IktlN_m3tfYoICxob7TsNmZt12eLEHs7UMEY';
        $grantType = 'refresh_token';

        // Build the API URL with query parameters
        $queryParams = [
            'app_key'      => $appKey,
            'app_secret'   => $appSecret,
            'refresh_token' => $refreshToken,
            'grant_type'   => $grantType,
        ];

        $url = 'https://auth.tiktok-shops.com/api/v2/token/refresh?' . http_build_query($queryParams);

        // Create a Guzzle client
        $client = new Client();

        try {
            // Make the GET request with Guzzle
            $response = $client->request('GET', $url);

            // Get the response body
            $body = $response->getBody()->getContents();

            // Return the response (or handle it as needed)
            return response()->json([
                'success' => true,
                'data' => json_decode($body),
            ]);
        } catch (\Exception $e) {
            // Handle the error
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getAuthCode(Request $request)
    {
       // Set API parameters
       $appKey = '6dt7tmoe7fqrt';
       $appSecret = '9bf8ddd21afa7856df185607332110ee937c940d';
       $authCode = $request->code;
       $grantType = 'authorized_code';

       // Build the API URL with query parameters
       $queryParams = [
           'app_key'    => $appKey,
           'app_secret' => $appSecret,
           'auth_code'  => $authCode,
           'grant_type' => $grantType,
       ];

       $url = 'https://auth.tiktok-shops.com/api/v2/token/get?' . http_build_query($queryParams);

       // Create a Guzzle client
       $client = new Client();

       try {
           // Make the GET request with Guzzle
           $response = $client->request('GET', $url);

           // Get the response body
           $body = $response->getBody()->getContents();

           //Lưu refresh token vào DB

           // Return the response (or handle it as needed)
           return response()->json([
               'success' => true,
               'data' => json_decode($body),
           ]);
       } catch (\Exception $e) {
           // Handle the error
           return response()->json([
               'success' => false,
               'message' => $e->getMessage(),
           ]);
       }
    }

    public function getOrderTiktok()
    {
        // Set API parameters
        $appKey = '6dt7tmoe7fqrt';
        $appSecret = '9bf8ddd21afa7856df185607332110ee937c940d'; // Replace with your actual app secret
        $shopCipher = 'ROW_VYzVgQAAAABBh0086l4dwgaye1V6lSA-';
        $timestamp = time(); // Current timestamp
        $sortField = 'create_time';
        $pageSize = 20;
        $sortOrder = 'ASC';

        // Prepare the parameters for the request
        $params = [
            'sort_field' => $sortField,
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            'shop_cipher' => $shopCipher,
            'page_size' => $pageSize,
            'sort_order' => $sortOrder,
        ];

        // Define the path for the request
        $path = '/order/202309/orders/search';

        // Calculate the signature
        $sign = $this->calculateSign($appSecret, $params, $path);

        // Add the sign to the parameters
        $params['sign'] = $sign;

        // Create the query string
        $queryString = http_build_query($params);
        $url = 'https://open-api.tiktokglobalshop.com' . $path . '?' . $queryString;

        // Create a Guzzle client
        $client = new Client();

        try {
            // Make the GET request with Guzzle
            $response = $client->request('GET', $url);

            // Get the response body
            $body = $response->getBody()->getContents();

            // Return the response (or handle it as needed)
            return response()->json([
                'success' => true,
                'data' => json_decode($body),
            ]);
        } catch (\Exception $e) {
            // Handle the error
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
