<?php

namespace App\Traits;

trait ApiResponse
{
    protected function ok($data = null, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function fail(string $error, int $status = 400, array $extra = [])
    {
        return response()->json(array_merge(['success' => false, 'error' => $error], $extra), $status);
    }
}
