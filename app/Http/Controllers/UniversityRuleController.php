<?php

namespace App\Http\Controllers;

use App\Models\UniversityRule;
use Illuminate\Http\Request;

class UniversityRuleController extends Controller
{
    public function getUniversityRules(Request $request)
    {
        if (!\Auth::user()->can('manage university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'university_id' => 'required|integer|exists:universities,id',
                'rule_type' => 'required|string|in:restriction,document'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }


        $rules = UniversityRule::with(['created_by:id,name'])
        ->where('university_id', $request->university_id)
        ->where('rule_type', $request->rule_type)
        ->orderBy('position', 'ASC')
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rules
        ], 200);
    }

    public function addUniversityRule(Request $request)
    {
        if (!\Auth::user()->can('create university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'university_id' => 'required|integer|exists:universities,id',
                'name' => 'required|string',
                'position' => 'required|integer',
                'rule_type' => 'required|string|in:restriction,document'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $rule = UniversityRule::create([
            'university_id' => $request->university_id,
            'name' => $request->name,
            'position' => $request->position,
            'rule_type' => $request->rule_type,
            'created_by' => \Auth::id()
        ]);

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'University Rule Created',
                'message' => "A new rule '{$rule->name}' has been created successfully.",
            ]),
            'module_id' => $rule->id,
            'module_type' => 'university',
            'notification_type' => 'Rule Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully created.'),
            'data' => $rule
        ], 201);
    }

    public function updateUniversityRule(Request $request)
    {
        if (!\Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:university_rules,id',
                'name' => 'required|string',
                'position' => 'required|integer',
                'rule_type' => 'required|string|in:restriction,document'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $rule = UniversityRule::where('id', $request->id)->first();

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => __('University Rule not found or unauthorized.')
            ], 404);
        }

        // Store original data before update
        $originalData = $rule->toArray();

        // Update fields
        $rule->name = $request->name;
        $rule->position = $request->position;
        $rule->rule_type = $request->rule_type;
        $rule->created_by = \Auth::id();

        // Manually update only the updated_at timestamp
        $rule->timestamps = false;  // Disable automatic timestamp updating
        $rule->save();

        // Log changed fields only
        $changes = [];
        foreach ($originalData as $field => $oldValue) {
            if ($rule->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $rule->$field
                ];
            }
        }

        // If there are changes, log the activity
        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'University Rule Updated',
                    'message' => 'Fields updated successfully',
                    'changes' => $changes
                ]),
                'module_id' => $rule->id,
                'module_type' => 'university',
                'notification_type' => 'Rule Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully updated.'),
            'data' => $rule
        ], 200);
    }


    public function deleteUniversityRule(Request $request)
    {
        if (!\Auth::user()->can('delete university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:university_rules,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $rule = UniversityRule::where('id', $request->id)->first();

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => __('University Rule not found or unauthorized.')
            ], 404);
        }

        $ruleName = $rule->name;
        $ruleId = $rule->id;

        $rule->delete();

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'University Rule Deleted',
                'message' => "Rule '{$ruleName}' has been deleted successfully.",
            ]),
            'module_id' => $ruleId,
            'module_type' => 'university_rule',
            'notification_type' => 'Rule Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully deleted.')
        ], 200);
    }
}
