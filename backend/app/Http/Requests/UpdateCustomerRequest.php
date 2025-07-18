<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $customerId = $this->route('customer')->id ?? null;

        return [
            'name' => 'sometimes|required|string|max:255',
            'line_user_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('customers', 'line_user_id')->ignore($customerId)
            ],
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'gender' => 'sometimes|nullable|in:male,female,other',
            'birthday' => 'sometimes|nullable|date|before:today',
            'address' => 'sometimes|nullable|string|max:500',
            'notes' => 'sometimes|nullable|string|max:1000',
            'status' => 'sometimes|in:active,inactive,blocked',
            'referral_source' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => '客戶姓名為必填項目',
            'name.max' => '客戶姓名不得超過255個字符',
            'line_user_id.unique' => '此LINE用戶ID已被使用',
            'phone.max' => '電話號碼不得超過20個字符',
            'email.email' => '請輸入有效的電子信箱格式',
            'email.max' => '電子信箱不得超過255個字符',
            'gender.in' => '性別必須為男性、女性或其他',
            'birthday.date' => '請輸入有效的生日日期',
            'birthday.before' => '生日必須早於今天',
            'address.max' => '地址不得超過500個字符',
            'notes.max' => '備註不得超過1000個字符',
            'status.in' => '狀態必須為有效、無效或封鎖',
            'referral_source.max' => '推薦來源不得超過255個字符',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => '客戶姓名',
            'line_user_id' => 'LINE用戶ID',
            'phone' => '電話號碼',
            'email' => '電子信箱',
            'gender' => '性別',
            'birthday' => '生日',
            'address' => '地址',
            'notes' => '備註',
            'status' => '狀態',
            'referral_source' => '推薦來源',
        ];
    }
}
