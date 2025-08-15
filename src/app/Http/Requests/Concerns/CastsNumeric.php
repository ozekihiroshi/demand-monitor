<?php
// app/Http/Requests/Concerns/CastsNumeric.php
namespace App\Http\Requests\Concerns;
trait CastsNumeric {
    protected function castNumeric(array $data, array $keys): array {
        foreach ($keys as $k) if (isset($data[$k])) $data[$k] = is_numeric($data[$k]) ? $data[$k] + 0 : $data[$k];
        return $data;
    }
}