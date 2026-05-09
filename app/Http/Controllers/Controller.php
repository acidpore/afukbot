<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class Controller
{
    public function sendResponse($data, $message = 'Success', $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public function sendError($message = 'Error', $errors = [], $status = 404): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $errors,
        ], $status);
    }
}
