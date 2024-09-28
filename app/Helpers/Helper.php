<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class Helper {
    public static function cleanText($text) 
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }

    public static function trackingInfo($msg_log = null, $channel = 'track_info')
    {
        $action = request()->route()->getActionName() ?? "undefined";
        Log::channel($channel)->info($action . "::::" . request()->log_id . '::::' . (new self)->infoClientLogin() . '::::' . $msg_log);
    }


    public static function trackingInfoCommand($msg_log = null, $channel = 'track_info')
    {
        Log::channel($channel)->info($msg_log);
    }

    public static function trackingError($msg_log, $channel = 'track_error')
    {
        $action = request()->route()->getActionName() ?? "undefined";
        Log::channel($channel)->error($action . "::::" . request()->log_id . '::::' . (new self)->infoClientLogin() . '::::' . $msg_log);
    }

    public function infoClientLogin() {
        $info = [];
        $ip = request()->ip();
        $info['ip'] = $ip;
        $info['user_agent'] = request()->header('User-Agent');
        try {
            $auth = Auth::user();
            $info['user_id'] = $auth->id;
            $info['email'] = $auth->email;
        } catch (\Throwable $th) {
            //throw $th;
        }

        return json_encode($info);
    }

}

