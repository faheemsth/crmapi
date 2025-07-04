<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Stage;
use App\Models\Utility;
use App\Models\Pipeline;
use App\Models\ClientDeal;
use App\Models\University;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\ApplicationNote;
use App\Models\ApplicationStage;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\Deal;
use App\Models\SavedFilter;
use App\Models\StageHistory;
use App\Models\ApplicationReport;
use Illuminate\Http\Request;
use App\Models\DealApplication;
use App\Models\DealTask;
use App\Models\LeadTag;
use Illuminate\Support\Facades\Validator;
use Session;

class ReportsController extends Controller
{
    /**
     * Visa Analysis Report
     */
    public function visaAnalysis(Request $request)
    {
        // dd($request);
        $brandIds = $request->input('brand_ids', []);
        $regionIds = $request->input('region_ids', []);
        $branchIds = $request->input('branch_ids', []);
        $institute_ids = $request->input('institute_ids', []);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $intake = $request->input('intake');
        $intakeYear = $request->input('intakeYear');
        $visaStages = $request->input('visa_stage_ids', [10, 11]); // Visa Granted + Enrolled

        // Base application query
        $query = ApplicationReport::query();

        if (!empty($brandIds)) {
            $query->whereIn('brand_id', $brandIds);
        }
        if (!empty($regionIds)) {
            $query->whereIn('region_id', $regionIds);
        }
        if (!empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (!empty($institute_ids)) {
            $query->whereIn('university_id', $institute_ids);
        }
        if (!empty($intakeYear)) {
            $query->where('intakeYear', $intakeYear);
        }
        if (!empty($intake)) {
            $query->where('intake', $intake);
        }

        if ($startDate) {
            $query->whereDate('deposit_created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('deposit_created_at', '<=', $endDate);
        }

        // Total distinct brands
        $totalBrands = ApplicationReport::distinct('brand_id')->count('brand_id');

        // Total visa count (Visa Granted or Enrolled)


        if ($startDate) {
            $totalVisas = (clone $query)->whereIn('deposit_stage_id', $visaStages)->count();
        } else {
            $totalVisas = (clone $query)->whereIn('stage_id', $visaStages)->count();
        }

        // Brand-wise visa count
        $brandVisaCounts = (clone $query)->select('brand_name', DB::raw('count(*) as visa_count'))
            ->whereIn('stage_id', $visaStages)
            ->groupBy('brand_id')
            ->get();

        // Intake-wise visa count (grouped by intake + intakeYear)
        $intakeVisaCounts = (clone $query)->select(
            'intake',
            'intakeYear',
            DB::raw('count(*) as visa_count')
        )
            ->whereIn('stage_id', $visaStages)
            ->groupBy('intake', 'intakeYear')
            ->get();

        // Institute-wise application count (>0)
        $instituteCounts = (clone $query)->select('university_name', DB::raw('count(*) as application_count'))
            ->whereIn('stage_id', $visaStages)
            ->groupBy('university_name')
            ->having('application_count', '>', 0)
            ->get();
        if ($startDate) {
            // Month-wise visa counts from ApplicationReport
            $monthWiseVisas = ApplicationReport::whereIn('deposit_stage_id', $visaStages);

            if (!empty($brandIds)) {
                $monthWiseVisas->whereIn('brand_id', $brandIds);
            }
            if (!empty($regionIds)) {
                $monthWiseVisas->whereIn('region_id', $regionIds);
            }
            if (!empty($branchIds)) {
                $monthWiseVisas->whereIn('branch_id', $branchIds);
            }
            if (!empty($institute_ids)) {
                $query->whereIn('university_id', $institute_ids);
            }

            if (!empty($intakeYear)) {
                $monthWiseVisas->where('intakeYear', $intakeYear);
            }
            if (!empty($intake)) {
                $monthWiseVisas->where('intake', $intake);
            }

            if ($startDate) {
                $monthWiseVisas->whereDate('deposit_created_at', '>=', $startDate);
            }
            if ($endDate) {
                $monthWiseVisas->whereDate('deposit_created_at', '<=', $endDate);
            }

            $monthWiseVisas = $monthWiseVisas->count();
        } else {
            $monthWiseVisas = 0;
        }

        return response()->json([
            'total_brands' => $totalBrands,
            'total_visas' => $totalVisas,
            'brand_visa_counts' => $brandVisaCounts,
            'institutes' => $instituteCounts,
            'intakeVisaCounts' => $intakeVisaCounts,
            'month_wise_visas' => $monthWiseVisas,
            'filters' => [
                'brands' => $brandIds,
                'regions' => $regionIds,
                'branches' => $branchIds,
                'visa_stage_ids' => $visaStages,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }




    /**
     * Deposit Analysis Report
     */
    public function depositAnalysis(Request $request)
    {
        $brandIds = $request->input('brand_ids', []);
        $regionIds = $request->input('region_ids', []);
        $branchIds = $request->input('branch_ids', []);
        $startDate = $request->input('start_date', '2025-01-01');
        $endDate = $request->input('end_date', now()->toDateString());

        // Deposit stage IDs as per formula
        $depositStages = [5, 6, 7, 8, 9, 10, 11]; // Deposited, Compliance Checks, CAS Approved, CAS Received, VISA Applied, VISA Granted, Enrolled

        // Filter applications on brand, region, branch if provided
        $query = ApplicationReport::query();

        if (!empty($brandIds)) {
            $query->whereIn('brand_id', $brandIds);
        }
        if (!empty($regionIds)) {
            $query->whereIn('region_id', $regionIds);
        }
        if (!empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        // Get application ids that have moved to Deposited stage (5) within date range
        $depositStageApplications = StageHistory::select('type_id', 'created_at')
            ->where('type', 'application')
            ->whereIn('stage_id', $depositStages)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->pluck('type_id')
            ->unique()
            ->toArray();

        $query->whereIn('id', $depositStageApplications);

        // Total deposits count
        $totalDeposits = $query->count();

        // Brand-wise deposits count
        $brandDepositCounts = $query->select('brand_id', DB::raw('count(*) as deposit_count'))
            ->groupBy('brand_id')
            ->get();

        return response()->json([
            'total_deposits' => $totalDeposits,
            'brand_deposit_counts' => $brandDepositCounts,
            'filters' => [
                'brands' => $brandIds,
                'regions' => $regionIds,
                'branches' => $branchIds,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }
}
