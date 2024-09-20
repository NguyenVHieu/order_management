<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;

class WebhookController extends BaseController
{
    public function updateStatusOrder(Request $request)
    {
        try {
            dd(67);
            Helper::trackingInfo('body webhook' . $request->all());
        } catch (\Throwable $th) {
           
        }
    }
}