<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller as Controller;
use Illuminate\Http\JsonResponse;

  
class BaseController extends Controller
{

    public function sendSuccess($data = [], $code = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $data
        ], $code);
    }

    // Hàm phản hồi thất bại
    public function sendError($message = '', $code = 400): JsonResponse
    {
        $msgBag = new \Illuminate\Support\MessageBag;
        $msgBag->add('exception',  $code == 500 ? 'Server Error' : $message);
        return response()->json([
            'status' => false,
            'errors' => $message
        ], $code);
    }


    public function paginate($paginator) {
        $paginate = [
            'current_page' => $paginator['current_page'],
            'path' => $paginator['path'],
            'from' => $paginator['from'],
            'last_page' => $paginator['last_page'],
            'per_page' => $paginator['per_page'],
            'total' => $paginator['total'],

        ];
        

        return $paginate;
    } 
}
