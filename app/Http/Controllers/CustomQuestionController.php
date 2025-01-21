<?php

namespace App\Http\Controllers;

use App\Models\CustomQuestion;
use Illuminate\Http\Request;

class CustomQuestionController extends Controller
{
    public function getQuestions(Request $request)
    {
        if (\Auth::user()->can('manage custom question')) {
            $questions = CustomQuestion::where('created_by', \Auth::user()->creatorId())->get();

            return response()->json([
                'status' => 'success',
                'data' => $questions,
                'message' => __('Questions retrieved successfully.'),
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.'),
            ], 403);
        }
    }

    public function createQuestion(Request $request)
    {
        if (\Auth::user()->can('create custom question')) {
            $validator = \Validator::make(
                $request->all(),
                ['question' => 'required|unique:custom_questions,question,NULL,id,created_by,' . \Auth::user()->creatorId()]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $question = new CustomQuestion();
            $question->question = $request->question;
            $question->is_required = $request->is_required ?? false;
            $question->created_by = \Auth::user()->creatorId();
            $question->save();

            // Log activity
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Custom Question Created',
                    'message' => __('Custom question successfully created.')
                ]),
                'module_id' => $question->id,
                'module_type' => 'custom_question',
                'notification_type' => 'Custom Question Created',
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $question,
                'message' => __('Question successfully created.'),
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.'),
            ], 403);
        }
    }

    public function updateQuestion(Request $request)
    {
        if (\Auth::user()->can('edit custom question')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'id' => 'required|exists:custom_questions,id',
                    'question' => 'required|unique:custom_questions,question,' . $request->id . ',id,created_by,' . \Auth::user()->creatorId()


                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $customQuestion = CustomQuestion::find($request->id);
            $customQuestion->question = $request->question;
            $customQuestion->is_required = $request->is_required ?? false;
            $customQuestion->save();

            // Log activity
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Custom Question Updated',
                    'message' => __('Custom question successfully updated.')
                ]),
                'module_id' => $customQuestion->id,
                'module_type' => 'custom_question',
                'notification_type' => 'Custom Question Updated',
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $customQuestion,
                'message' => __('Question successfully updated.'),
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.'),
            ], 403);
        }
    }

    public function deleteQuestion(Request $request)
    {
        if (\Auth::user()->can('delete custom question')) {
            $validator = \Validator::make(
                $request->all(),
                ['id' => 'required|exists:custom_questions,id']
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $customQuestion = CustomQuestion::find($request->id);
            $customQuestion->delete();

            // Log activity
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Custom Question Deleted',
                    'message' => __('Custom question successfully deleted.')
                ]),
                'module_id' => $request->id,
                'module_type' => 'custom_question',
                'notification_type' => 'Custom Question Deleted',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Question successfully deleted.'),
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.'),
            ], 403);
        }
    }
}
