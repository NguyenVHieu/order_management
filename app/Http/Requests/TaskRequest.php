<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',   // Tiêu đề task: bắt buộc, chuỗi, tối đa 255 ký tự
            'description' => 'nullable|string',      // Mô tả task: không bắt buộc, là chuỗi
            'status_id' => 'required|integer|between:1,6', // Trạng thái: bắt buộc, số nguyên, giá trị từ 1 đến 4
            'category_design_id' => 'nullable|integer', // ID loại thiết kế: không bắt buộc, là số nguyên
            'designer_id' => 'nullable|integer',     // ID nhà thiết kế: không bắt buộc, là số nguyên
            'deadline' => 'nullable|date|after:today', // Thời hạn: không bắt buộc, là ngày, phải sau hôm nay
            'level_task' => 'required|integer|min:1', // Mức độ task: bắt buộc, số nguyên, tối thiểu 1
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Ảnh: không bắt buộc, là ảnh, định dạng cho phép và tối đa 2MB
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Tiêu đề là bắt buộc.',
            'status_id.required' => 'Trạng thái là bắt buộc.',
            'status_id.between' => 'Trạng thái phải nằm trong khoảng từ 1 đến 4.',
            'deadline.after' => 'Thời hạn phải là một ngày trong tương lai.',
            'images.*.image' => 'Tất cả file tải lên phải là hình ảnh.',
            'images.*.mimes' => 'Chỉ chấp nhận các định dạng hình ảnh: jpeg, png, jpg, gif.',
            'images.*.max' => 'Mỗi hình ảnh không được vượt quá 2MB.',
        ];
    }
}
