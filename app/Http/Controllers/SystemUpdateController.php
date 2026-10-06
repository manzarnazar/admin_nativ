<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\SystemUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SystemUpdateController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== UserRole::Admin) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthorized. Only administrators can perform system updates.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'purchase_code' => 'required|alpha_dash',
            'file' => 'required|file|mimes:zip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $service = new SystemUpdateService;

            $validation = $service->validatePurchaseCode($request->input('purchase_code'));

            if ($validation['error']) {
                return response()->json([
                    'error' => true,
                    'message' => $validation['message'],
                ]);
            }

            $result = $service->applyUpdate($request->file('file'));

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => 'An unexpected error occurred: '.$e->getMessage(),
            ]);
        }
    }
}
