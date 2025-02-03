<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Rules\UserExistRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevenueDayRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->input('id') ?? 0;

        return [
            'revenue' => ['required'], 
            'shop_id' => ['required', Rule::unique('revenue_days')->where('date', $this->input('date'))->where('shop_id', $this->input('shop_id'))->ignore($id)],
            'date' => ['required'],
        ];
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
            'revenue.required' => 'Doanh thu là bắt buộc.',
            'shop_id.required' => 'Shop ID là bắt buộc.',
            'shop_id.unique' => 'Shop này đã tồn tại cho ngày được chọn.',
            'date.required' => 'Ngày là bắt buộc.',
        ];
    }

}
