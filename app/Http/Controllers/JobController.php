<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomQuestion;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Utility;
use App\Models\JobApplication;
use App\Models\JobApplicationNote;
use App\Models\JobCategory;
use App\Models\LogActivity;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class JobController extends Controller
{

    public function getJobs(Request $request)
    {
        if (\Auth::user()->can('manage job')) {
            $user = \Auth::user();

            // Building the query
            $query = Job::select(
                'jobs.*',
                'regions.name as region',
                'branches.name as branch',
                'users.name as brand',
                'assigned_to.name as created_user'
            )
            ->leftJoin('users', 'users.id', '=', 'jobs.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'jobs.branch')
            ->leftJoin('regions', 'regions.id', '=', 'jobs.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'jobs.created_by');

            // Apply role-based filtering
            $Appraisal_query = RoleBaseTableGet($query, 'jobs.brand_id', 'jobs.region_id', 'jobs.branch', 'jobs.created_by');

            // Apply filters
            $filters = $this->jobsFilters($request);
            foreach ($filters as $column => $value) {
                if ($column == 'created_at') {
                    $Appraisal_query->whereDate('jobs.created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
                } elseif ($column == 'brand') {
                    $Appraisal_query->where('jobs.brand_id', $value);
                } elseif ($column == 'region_id') {
                    $Appraisal_query->where('jobs.region_id', $value);
                } elseif ($column == 'branch_id') {
                    $Appraisal_query->where('jobs.branch', $value);
                } elseif ($column == 'price') {
                    $Appraisal_query->where('jobs.price', $value['operator'], $value['value']);
                }
            }

            // Fetch jobs
            $jobs = $Appraisal_query->get();

            // For inactive jobs
            $data = [
                'total' => CountJob(['active', 'in_active']),
                'active' => CountJob(['active']),
                'in_active' => CountJob(['in_active']),
            ];

            // Return JSON response
            return response()->json([
                'status' => 'success',
                'data' => [
                    'jobs' => $jobs,
                    'summary' => $data,
                ],
                'message' => __('Jobs retrieved successfully'),
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 403);
        }
    }

    /**
     * Filters for jobs.
     */
    private function jobsFilters(Request $request)
    {
        $filters = [];

        if ($request->filled('name')) {
            $filters['name'] = $request->input('name');
        }

        if ($request->filled('brand')) {
            $filters['brand'] = $request->input('brand');
        }

        if ($request->filled('region_id')) {
            $filters['region_id'] = $request->input('region_id');
        }

        if ($request->filled('branch_id')) {
            $filters['branch_id'] = $request->input('branch_id');
        }

        if ($request->filled('lead_assigned_user')) {
            $filters['deal_assigned_user'] = $request->input('lead_assigned_user');
        }

        if ($request->filled('stages')) {
            $filters['stage_id'] = $request->input('stages');
        }

        if ($request->filled('users')) {
            $filters['users'] = $request->input('users');
        }

        if ($request->filled('created_at_from')) {
            $filters['created_at_from'] = $request->input('created_at_from');
        }

        if ($request->filled('created_at_to')) {
            $filters['created_at_to'] = $request->input('created_at_to');
        }

        if ($request->filled('tag')) {
            $filters['tag'] = $request->input('tag');
        }

        if ($request->filled('price')) {
            $price = $request->input('price');
            $operator = '=';
            $value = $price;

            if (preg_match('/^(<=|>=|<|>)/', $price, $matches)) {
                $operator = $matches[1];
                $value = (float) substr($price, strlen($operator));
            }

            $filters['price'] = ['operator' => $operator, 'value' => $value];
        }

        return $filters;
    }


}
