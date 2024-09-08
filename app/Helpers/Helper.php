<?php

namespace App\Helpers;

class Helper {
    public static function cleanText($text) 
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }
}

