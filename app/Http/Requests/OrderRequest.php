<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Rules\UserExistRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $type = $this->input('type') ?? 0;
        
        if ($type != 1) {
            return [
                'color' => 'required',
                'size' => 'required',
                'country' => 'required',
                'city' => 'required',
                'address' => 'required',
                'zip' => 'required',
                'style' => 'required',
                'order_number' =>'required',
                // 'state' => 'required',
            ];
        }

        return [];
        
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'color.required' => 'The color is required.',
            'size.required' => 'The size is required.',
            'country.required' => 'The country is required.',
            'city.required' => 'The city is required.',
            'address.required' => 'The address is required.',
            'zip.required' => 'The zip is required.',
            'style.required' => 'The style is required.',
            'order_number' => 'The order number is required.',
            // 'state.required' => 'The state is required.',
        ];
    }

}
