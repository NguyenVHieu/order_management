<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseFormRequest extends FormRequest
{
    
    protected function failedValidation(Validator $validator)
    {        
        $res = [
            'status' => false,
            'data' => [],
            'errors' => $validator->errors(),
            'errorCode' => 422,            
        ];
        throw new HttpResponseException(
            response()->json($res, 200)
        ); 
    }
}
