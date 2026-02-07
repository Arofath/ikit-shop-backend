<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
abstract class Controller
{
    // Success response method
    public function sendResponse($result, $message, $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $result,
        ], $code);
    }

    // Error response method
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
