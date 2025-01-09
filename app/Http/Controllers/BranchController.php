<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Department;
use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{

    public function branchDetail(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'branch_id' =>  'required|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $Branch = Branch::findOrFail($request->branch_id);
        $region = Region::findOrFail($Branch->region_id);
        $manager = User::findOrFail($Branch->branch_manager_id);
        $brands = User::findOrFail($Branch->brands);

        // Return Complete Data as JSON
            return response()->json([
                'status' => 'success',
                'data' => [
                    'branch' => $Branch,       // Complete Branch Object
                    'region' => $region,       // Complete Region Object
                    'manager' => $manager,     // Complete Manager Object
                    'brands' => $brands        // Collection of Complete Brand Objects
                ],
            ], 200);
    }


}
