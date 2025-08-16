<?php
// app/Http/Requests/MeterStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ポリシーを使っているなら true でもOK（Controllerでauthorize済み）
        return $this->user()?->can('create', Meter::class) ?? false;
        //return true;
    }

    public function rules(): array
    {
        return [
            'facility_id'        => ['required', 'exists:facilities,id'],
            'code'               => ['required', 'alpha_dash', 'max:255', 'unique:meters,code'],
            'name'               => ['required', 'string', 'max:255'],
            'group_id'           => ['nullable', 'exists:groups,id'],
            'threshold_override' => ['nullable', 'integer', 'min:0'],
            // 文字列(JSON)でも配列でも受けられるように一旦 nullable。前処理で配列へ。
            'rate_override'      => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $th = $this->input('threshold_override');
        $this->merge([
            'threshold_override' => ($th === '' || $th === null) ? null : (int) $th,
        ]);
        // ※ rate_override はここでは一切触らない（decode しない）
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
