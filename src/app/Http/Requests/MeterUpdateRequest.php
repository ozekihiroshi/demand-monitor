<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Meter;

class MeterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controllerでauthorize済みなら true でもOK
        return $this->user()?->can('update', $this->route('meter')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required','string','max:255'],
            'group_id'           => ['required','exists:groups,id'],
            'threshold_override' => ['nullable','integer','min:0'],
            'rate_override'      => ['nullable'],
            // codeは今回のUIでは更新しない想定なので不要（更新するなら unique ルールを追加）
        ];
    }

     protected function prepareForValidation(): void
    {
        $th = $this->input('threshold_override');
        $this->merge([
            'threshold_override' => ($th === '' || $th === null) ? null : (int) $th,
        ]);
        // ※ rate_override は触らない
    }

    public function withValidator($validator): void
    {
        $val = $this->input('rate_override');
        if (is_string($val)) {
            $validator->sometimes('rate_override', 'json', fn() => true);
        } elseif (is_array($val)) {
            $validator->sometimes('rate_override', 'array', fn() => true);
        }
    }
}
