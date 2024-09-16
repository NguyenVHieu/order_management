<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Rules\UserExistRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->input('id') ?? 0;
        if ($id > 0) {    //update
            return [
                'name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $id,
                'password' => 'required|max:255',
                'user_type_id' => 'required',
                'shop_id' => 'required', 
            ];
        } else {
            return [
                'name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|max:255',
                'user_type_id' => 'required',
                'shop_id' => 'required',    
            ];
        }
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
            'email.unique' => 'The email has already been taken.',
            'email.required' => 'The email is required.',
            'password.required' => 'The password is required.',
            'user_type_id.required' => 'The user type is required.',
            'shop_id.required' => 'The shop is required.',
            'name.required' => 'The name is required.',
        ];
    }

}
