<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = [], $message = null, $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $data['message'] ?? $message,
            'data' => $data,
        ], $code);
    }

    protected function error($message, $code = 400, $errors = [])
    {

        return response()->json(['status' => 'error', 'message' => $message, 'errors' => $errors], $code);
    }

    protected function unauthorized($message = null)
    {
        return $this->error(
            __('api.auth.unauthorized'), 401);
    }

    protected function forbidden($message = null)
    {
        return $this->error(
            __('api.auth.unauthorized'),
            403
        );
    }

    protected function notFound($message)
    {
        return $this->error($message, 404);
    }

    protected function validationError($errors)
    {
        return $this->error(
            __('api.validation.failed'),
            422,
            $errors
        );
    }

    protected function validationErrorWithUrl($message, $verification_url, $code)
    {
        return $this->error($message, $code, ['verification_url' => $verification_url]);
    }

    protected function loggedOut($message = null, $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
        ], $code);
    }
}
