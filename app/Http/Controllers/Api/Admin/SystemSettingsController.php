<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SystemSettingsController extends Controller
{
    /**
     * Get all system settings
     */
    public function index(): JsonResponse
    {
        try {
            $settings = SystemSetting::getAll();

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific setting by key
     */
    public function show(string $key): JsonResponse
    {
        try {
            $setting = SystemSetting::where('key', $key)->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found'
                ], 404);
            }

            $value = SystemSetting::castValue($setting->value, $setting->type);

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $setting->key,
                    'value' => $value,
                    'type' => $setting->type,
                    'description' => $setting->description
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update system settings
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.type' => 'sometimes|string|in:string,number,boolean,json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedSettings = [];

            foreach ($request->input('settings') as $settingData) {
                $key = $settingData['key'];
                $value = $settingData['value'];
                $type = $settingData['type'] ?? 'string';
                $description = $settingData['description'] ?? null;

                // Validate value based on type
                if ($type === 'number' && !is_numeric($value)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Value for '{$key}' must be numeric"
                    ], 422);
                }

                if ($type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                }

                if ($type === 'json') {
                    if (!is_string($value) || json_decode($value) === null) {
                        return response()->json([
                            'success' => false,
                            'message' => "Value for '{$key}' must be valid JSON"
                        ], 422);
                    }
                }

                $setting = SystemSetting::set($key, $value, $type, $description);
                $updatedSettings[$key] = SystemSetting::castValue($setting->value, $setting->type);
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $updatedSettings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a single setting
     */
    public function updateSetting(Request $request, string $key): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required',
            'type' => 'sometimes|string|in:string,number,boolean,json',
            'description' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $value = $request->input('value');
            $type = $request->input('type', 'string');
            $description = $request->input('description');

            // Validate value based on type
            if ($type === 'number' && !is_numeric($value)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Value must be numeric'
                ], 422);
            }

            if ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }

            if ($type === 'json') {
                if (!is_string($value) || json_decode($value) === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Value must be valid JSON'
                    ], 422);
                }
            }

            $setting = SystemSetting::set($key, $value, $type, $description);
            $castedValue = SystemSetting::castValue($setting->value, $setting->type);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => [
                    'key' => $setting->key,
                    'value' => $castedValue,
                    'type' => $setting->type,
                    'description' => $setting->description
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset settings to default values
     */
    public function reset(): JsonResponse
    {
        try {
            // Clear all existing settings
            SystemSetting::truncate();

            // Run the seeder to restore defaults
            $seeder = new \Database\Seeders\SystemSettingsSeeder();
            $seeder->run();

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to default values'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to cast value (moved from model for controller use)
     */
    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
}
