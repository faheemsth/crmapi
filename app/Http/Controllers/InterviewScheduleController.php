<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InterviewSchedule;
use App\Models\JobApplication;
use App\Models\JobStage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class InterviewScheduleController extends Controller
{
    public function getInterviews(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'      => 'required|date',
            'time'      => 'required',
        ]);
        
        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Retrieve validated input
        $validatedData = $validator->validated();
        
        // Apply filters to the query
        $schedules = InterviewSchedule::with('applications.jobs', 'users', 'scheduled_by')
            ->where('created_by', Auth::id())
            ->whereDate('date', $validatedData['date']) // Filter by date
            ->whereTime('time', $validatedData['time']) // Filter by time
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }

    public function addInterveiw(Request $request)
    {
        if (!Auth::user()->can('create interview schedule')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'candidate' => 'required|exists:job_applications,id',
            'employee'  => 'required|exists:users,id',
            'date'      => 'required|date',
            'time'      => 'required',
            'comment'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $schedule = InterviewSchedule::create([
            'candidate'  => $request->candidate,
            'employee'   => $request->employee,
            'date'       => $request->date,
            'time'       => $request->time,
            'comment'    => $request->comment,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Interview schedule successfully created.'),
            'data'    => $schedule
        ], 201);
    }

    public function show($id)
    {
        $schedule = InterviewSchedule::with('candidate', 'employee')->find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => __('Schedule not found.')
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $schedule
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->can('edit interview schedule')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission denied.')
            ], 403);
        }

        $schedule = InterviewSchedule::find($id);
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => __('Schedule not found.')
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'candidate' => 'required|exists:job_applications,id',
            'employee'  => 'required|exists:users,id',
            'date'      => 'required|date',
            'time'      => 'required',
            'comment'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $schedule->update([
            'candidate' => $request->candidate,
            'employee'  => $request->employee,
            'date'      => $request->date,
            'time'      => $request->time,
            'comment'   => $request->comment,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Interview schedule successfully updated.'),
            'data'    => $schedule
        ]);
    }

    public function destroy($id)
    {
        $schedule = InterviewSchedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => __('Schedule not found.')
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => __('Interview schedule successfully deleted.')
        ]);
    }

    public function hrmInterviewSchedule(Request $request)
    {
        $userId = $request->query('emp_id', Auth::id());
        $AuthUser = User::find($userId);

        $schedules = InterviewSchedule::where('created_by', Auth::id())->get();
        $arrSchedule = $schedules->map(function ($schedule) {
            return [
                'id'       => $schedule->id,
                'title'    => optional(optional($schedule->applications)->jobs)->title ?? '',
                'start'    => $schedule->date,
                'className'=> 'event-primary',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $arrSchedule,
            'user'    => $AuthUser
        ]);
    }
}
