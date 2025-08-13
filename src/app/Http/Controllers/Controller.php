<?php
namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;   // ← これ追加
use Illuminate\Foundation\Validation\ValidatesRequests;     // （入っていなければ）
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;              // ← これ追加
}
