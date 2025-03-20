<?php

namespace App\Http\Controllers;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveTypeController extends Controller
{
    public function index()
    {
        if (auth()->user()->can('manage leave type')) {
            
            $leavetypes = LeaveType::where('created_by', auth()->id())->get();
            return response()->json([
                'status' => 'success',
                'data' => $leavetypes,
            ], 200);
        }
        return response()->json([
            'status' => 'error',
            'errors' =>  __('Permission denied.')
        ], 422);
    }

    public function plucktitle()
    {
        if (auth()->user()->can('manage leave type')) {
            $leavetypes = LeaveType::orderBy('title', 'ASC')->pluck('title', 'id')->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $leavetypes,
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'errors' =>  __('Permission denied.')
        ], 422);
    }

    
    public function store(Request $request)
    {
        if (auth()->user()->can('create leave type')) {
            $validator = Validator::make($request->all(), [
                'title' => 'required',
                'days' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' =>  $validator->errors(),
                ], 422);
            }

            $leavetype = LeaveType::create([
                'title' => $request->title,
                'days' => $request->days,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $leavetype,
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'errors' =>  __('Permission denied.')
        ], 422);
    }

    public function show(LeaveType $leavetype)
    {
            return response()->json([
                'status' => 'success',
                'data' => $leavetype,
            ], 200);
        
    }

    public function update(Request $request, LeaveType $leavetype)
    {
            $validator = Validator::make($request->all(), [
                'title' => 'required',
                'days' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' =>  $validator->errors(),
                ], 422);;
            }

            $leavetype->update([
                'title' => $request->title,
                'days' => $request->days,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $leavetype,
            ], 200);
        }

    }

    public function destroy(LeaveType $leavetype)
    {
            $leavetype->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'LeaveType successfully deleted.',
            ], 200);
    
    }
}