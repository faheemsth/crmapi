<?php
namespace App\Http\Controllers;
use Auth;

use Illuminate\Http\Request;
use App\Models\UniversityMeta;
use Illuminate\Support\Facades\Validator;

class UniversityMetaController extends Controller
{
    public function storeOrUpdateMetas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $universityId = $request->university_id;
        $userId = Auth::id();
        $metaData = $request->except('university_id');
        $changes = [];

        foreach ($metaData as $key => $newValue) {
            $existingMeta = UniversityMeta::where([
                'university_id' => $universityId,
                'meta_key' => $key
            ])->first();

            if ($existingMeta) {
                // Meta exists - check if value changed
                if ($existingMeta->meta_value != $newValue) {
                    $changes[$key] = [
                        'old' => $existingMeta->meta_value,
                        'new' => $newValue
                    ];
                }
            } else {
                // New meta field being added
                $changes[$key] = [
                    'old' => null,
                    'new' => $newValue
                ];
            }

            // Update or create the meta record
            UniversityMeta::updateOrCreate(
                [
                    'university_id' => $universityId,
                    'meta_key' => $key,
                ],
                [
                    'meta_value' => $newValue,
                    'created_by' => $userId,
                ]
            );
        }

        // Log changes if any
        if (!empty($changes)) {
            $this->logMetaChanges($universityId, $changes, $userId);
        }

        $metadata = UniversityMeta::where('university_id', $request->university_id)
        ->get();

    $metas = new \stdClass(); // Create empty object

    foreach ($metadata as $data) {
        $key = $data->meta_key;
        $value = $data->meta_value;

        // Handle JSON values if stored as JSON strings
        $decodedValue = json_decode($value);
        $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
    }

    return response()->json([
        'status' => true,
        'message' => 'University  processed successfully',
        'data' => $metas // Returns as object
    ]);
    }

    protected function logMetaChanges($universityId, $changes, $userId)
    {
        $logDetails = [
            'title' => 'University Metadata Updated',
            'message' => 'Metadata fields were modified',
            'changes' => $changes
        ];

        addLogActivity([
            'type' => 'info',
            'note' => json_encode($logDetails),
            'module_id' => $universityId,
            'module_type' => 'university',
            'created_by' => $userId,
            'notification_type' => 'University Metadata Updated'
        ]);
    }

    public function getUniversityMeta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $metadata = UniversityMeta::where('university_id', $request->university_id)
            ->get();

        $metas = new \stdClass(); // Create empty object

        foreach ($metadata as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;

            // Handle JSON values if stored as JSON strings
            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return response()->json([
            'status' => true,
            'message' => 'University meta list retrieved successfully.',
            'data' => $metas // Returns as object
        ]);
    }
}
