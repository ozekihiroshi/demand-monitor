<?php
// app/Http/Requests/MeterStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeterStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required','alpha_dash','max:32','unique:meters,code'],
            'name' => ['required','string','max:100'],
            'group_id' => ['required','integer','exists:groups,id'],
            'threshold_override' => ['nullable','integer','min:1','max:200000'],
            'rate_override' => ['nullable','json'],
        ];
    }
}


