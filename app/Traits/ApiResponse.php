<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ApiResponse {
    public function ok($data, $code = 200) {
        return response()->json(['data'=> $data, 'ok'=> true], $code);
    }

    public function custom($data, $code = 200) {
        return response()->json($data, $code);
    }

    public function data($data, $code = 200) {
        return response()->json(['ok'=> true, 'data'=> $data], $code);
    }

    public function success($message) {
        return response()->json(['message' => $message, 'ok' => true], 200);
    }

    protected function err($message, $code = 400) {
        return response()->json(['error'=> $message, 'code'=> $code, 'ok'=> false], $code);
    }

    protected function showAll(Collection $collection, $code = 200) {
        return $this->ok($collection, $code);
    }
    protected function showOne(Model $instance, $code = 200) {
        return $this->ok($instance, $code);
    }
}
