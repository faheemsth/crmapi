<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\EmailMarkittingFileEmail;
use App\Models\EmailSendingQueue;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\UserEmailTemplate;
use App\Models\Utility;
use Illuminate\Http\Request;
use App\Models\Pipeline;
use App\Models\Region;
use App\Models\SavedFilter;
use App\Models\User;
 
class EmailTemplateController extends Controller
{
    public function getEmailTemplatePluck(Request $request)
    {
        

        $emailTemplates = EmailTemplate::pluck('name', 'id')
                                    ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $emailTemplates
        ], 200);
    }

    public function getEmailTemplates()
    {
        if(!\Auth::user()->can('manage email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $emailTemplates = EmailTemplate::with(['created_by:id,name'])
                                    ->get();

        return response()->json([
            'status' => 'success',
            'data' => $emailTemplates
        ], 200);
    }

    public function getEmailTemplateDetail(Request $request)
    {
        if(!\Auth::user()->can('manage email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:email_templates,id'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $emailTemplate = EmailTemplate::find($request->id);

        return response()->json([
            'status' => 'success',
            'data' => $emailTemplate
        ], 200);
    }

    public function addEmailTemplate(Request $request)
    {
        if(!\Auth::user()->can('create email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string|unique:email_templates,name',
                'subject' => 'required|string',
                'template' => 'required|string',
                'type' => 'required|string'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $emailTemplate = new EmailTemplate();
        $emailTemplate->status = 1; // Default status is active
        $emailTemplate->name = $request->name;
        $emailTemplate->subject = $request->subject;
        $emailTemplate->template = $request->template; 
        $emailTemplate->type = $request->type ;
        $emailTemplate->created_by = \Auth::user()->creatorId();
        $emailTemplate->save();

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $emailTemplate->name . " email template created",
                'message' => $emailTemplate->name . " email template created",
            ]),
            'module_id' => $emailTemplate->id,
            'module_type' => 'email_template',
            'notification_type' => 'Email Template Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Email template successfully created.'),
            'data' => $emailTemplate
        ], 201);
    }

    public function updateEmailTemplate(Request $request)
    {
        if(!\Auth::user()->can('edit email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:email_templates,id',
                'name' => 'required|string|unique:email_templates,name,'.$request->id.',id',
                'subject' => 'required|string',
                'template' => 'required|string',
                'variables' => 'nullable|array'
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $emailTemplate = EmailTemplate::find($request->id);
        $originalData = $emailTemplate->toArray();

        $emailTemplate->name = $request->name;
        $emailTemplate->subject = $request->subject;
        $emailTemplate->template = $request->template; 
        $emailTemplate->type = $request->type ;
        $emailTemplate->created_by = \Auth::user()->creatorId();
        $emailTemplate->save();

        $changes = [];
        foreach ($originalData as $key => $value) {
            if ($emailTemplate->$key != $value && !in_array($key, ['created_at', 'updated_at'])) {
                $changes[$key] = [
                    'old' => $value,
                    'new' => $emailTemplate->$key
                ];
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $emailTemplate->name . " email template updated",
                    'message' => 'Fields updated: ' . implode(', ', array_keys($changes)),
                    'changes' => $changes
                ]),
                'module_id' => $emailTemplate->id,
                'module_type' => 'email_template',
                'notification_type' => 'Email Template Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Email template successfully updated.'),
            'data' => $emailTemplate
        ], 200);
    }

    public function deleteEmailTemplate(Request $request)
    {
        if(!\Auth::user()->can('delete email template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:email_templates,id,created_by,' . \Auth::user()->creatorId()
            ]
        );

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $emailTemplate = EmailTemplate::find($request->id);
        $emailTemplateName = $emailTemplate->name;
        $emailTemplateId = $emailTemplate->id;

        $emailTemplate->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $emailTemplateName . " email template deleted",
                'message' => $emailTemplateName . " email template deleted"
            ]),
            'module_id' => $emailTemplateId,
            'module_type' => 'email_template',
            'notification_type' => 'Email Template Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Email template successfully deleted.')
        ], 200);
    }
}
