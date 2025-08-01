<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_name' => [
                'required',
                'string',
                'max:50',
                'min:2',
                'regex:/^[\p{L}\p{N}\s\-\_]+$/u',
                function ($attribute, $value, $fail) {
                    // 檢查 XSS 攻擊模式
                    if (preg_match('/<script|javascript:|on\w+\s*=|<iframe|<object|<embed/i', $value)) {
                        $fail('Invalid characters detected in customer name.');
                    }
                    
                    // 檢查 SQL 注入模式
                    if (preg_match('/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC)\b)|(\-\-)|(\#)|(\;)/i', $value)) {
                        $fail('Invalid characters detected in customer name.');
                    }
                },
            ],
            'customer_phone' => [
                'required',
                'regex:/^09\d{8}$/',
            ],
            'customer_line_user_id' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9]+$/',
            ],
            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],
            'reservation_date' => [
                'required',
                'date',
                'after_or_equal:today',
                'before:' . now()->addMonths(3)->format('Y-m-d'),
            ],
            'reservation_time' => [
                'required',
                'date_format:H:i',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    if ($value && preg_match('/<script|javascript:|on\w+\s*=|<iframe|<object|<embed/i', $value)) {
                        $fail('Invalid characters detected in notes.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => '客戶姓名為必填項目',
            'customer_name.regex' => '客戶姓名只能包含中文、英文、數字、空格、連字符和底線',
            'customer_name.max' => '客戶姓名不能超過50個字元',
            'customer_phone.required' => '電話號碼為必填項目',
            'customer_phone.regex' => '電話號碼格式不正確，請輸入09開頭的10位數字',
            'service_id.required' => '請選擇服務項目',
            'service_id.exists' => '選擇的服務項目不存在',
            'reservation_date.required' => '預約日期為必填項目',
            'reservation_date.after_or_equal' => '預約日期不能早於今天',
            'reservation_date.before' => '預約日期不能超過3個月後',
            'reservation_time.required' => '預約時間為必填項目',
            'reservation_time.date_format' => '預約時間格式不正確',
            'notes.max' => '備註不能超過500個字元',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // 清理和標準化輸入數據
        if ($this->has('customer_name')) {
            $this->merge([
                'customer_name' => trim(strip_tags($this->customer_name)),
            ]);
        }

        if ($this->has('notes')) {
            $this->merge([
                'notes' => trim(strip_tags($this->notes)),
            ]);
        }

        if ($this->has('customer_phone')) {
            $this->merge([
                'customer_phone' => preg_replace('/[^\d]/', '', $this->customer_phone),
            ]);
        }
    }
}
