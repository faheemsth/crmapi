<?php

namespace App\Http\Controllers;

use App\Models\MoiAccepted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MoiAcceptedController extends Controller
{
    /**
     * Add institutes to university
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMOIInstitutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
            'institute_id' => 'required|array|min:1',
            'institute_id.*' => 'integer|exists:institutes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $addedRecords = MoiAccepted::addInstitutesToUniversity(
                $request->university_id,
                $request->institute_id,
                auth()->id()
            );

            if (empty($addedRecords)) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'All specified institutes already exist for this university'
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => count($addedRecords) . ' institute(s) added to university',
                'data' => $addedRecords
            ]);

        } catch (\Exception $e) {


            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add institutes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMOIInstitutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|integer|exists:universities,id',
            'institute_id' => 'required|array',
            'institute_id.*' => 'integer|exists:institutes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = MoiAccepted::updateInstitutes(
                $request->university_id,
                $request->institute_id,
                auth()->id()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Institute associations updated successfully',
                'data' => [
                    'added' => count($results['added']),
                    'removed' => count($results['removed']),
                    'unchanged' => count($results['unchanged']),
                    'details' => $results
                ]
            ]);

        } catch (\Exception $e) {
            addLogActivity([
                'type' => 'error',
                'note' => json_encode([
                    'title' => 'Update Failed',
                    'message' => $e->getMessage(),
                    'university_id' => $request->university_id,
                    'institute_ids' => $request->institute_id
                ]),
                'module_id' => $request->university_id,
                'module_type' => 'moi_accepted_list',
                'created_by' => auth()->id(),
                'notification_type' => 'Update Error'
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update institute associations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
