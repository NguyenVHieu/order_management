<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Rules\UserExistRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevenueMonthRequest extends BaseFormRequest
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
            'shop_id' => ['required', Rule::unique('revenue_months')->where('year', $this->input('year'))->where('month', $this->input('month'))->where('shop_id', $this->input('shop_id'))->ignore($id)],
            'year' => ['required'],
            'month' => ['required'],
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
            'year.required' => 'Năm là bắt buộc.',
            'month.required' => 'Tháng là bắt buộc.'
        ];
    }

}
