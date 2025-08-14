<?php
// app/Http/Requests/MeterUpdateRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeterUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // code は編集不可（ルール自体を出さない）
            'name' => ['required','string','max:100'],
            'group_id' => ['required','integer','exists:groups,id'],
            'threshold_override' => ['nullable','integer','min:1','max:200000'],
            'rate_override' => ['nullable','json'],
        ];
    }
}

