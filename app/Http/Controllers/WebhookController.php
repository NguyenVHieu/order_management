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
            Helper::trackingInfo('body webhook:' . json_encode($request->all()));
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}