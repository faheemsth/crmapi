<?php

namespace App\Http\Controllers;

use App\Models\PayslipType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class PayslipTypeController extends Controller
{
    public function index()
    {
        if (!\Auth::user()->can('manage payslip type')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $paysliptypes = PayslipType::get();
        return response()->json(['status' => 'success', 'data' => $paysliptypes], 200);
    }

    public function store(Request $request)
    {
        if (!\Auth::user()->can('create payslip type')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $paysliptype = new PayslipType();
        $paysliptype->name = $request->name;
        $paysliptype->created_by = \Auth::id();
        $paysliptype->save();

        return response()->json(['status' => 'success', 'message' => __('PayslipType successfully created.')], 201);
    }

    public function update(Request $request, PayslipType $paysliptype)
    {
        if (!\Auth::user()->can('edit payslip type')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        if ($paysliptype->created_by != \Auth::id()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $paysliptype->name = $request->name;
        $paysliptype->save();

        return response()->json(['status' => 'success', 'message' => __('PayslipType successfully updated.')], 200);
    }

    public function destroy(PayslipType $paysliptype)
    {
        if (!\Auth::user()->can('delete payslip type')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        if ($paysliptype->created_by != \Auth::id()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $paysliptype->delete();

        return response()->json(['status' => 'success', 'message' => __('PayslipType successfully deleted.')], 200);
    }
}
