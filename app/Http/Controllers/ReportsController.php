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
    $brandIds           = $request->input('brand_ids', []);
    $regionIds          = $request->input('region_ids', []);
    $branchIds          = $request->input('branch_ids', []);
    $instituteIds       = $request->input('institute_ids', []);
    $intake             = $request->input('intake');
    $intakeYear         = $request->input('intakeYear');

    $visaStages         = $request->input('visa_stage_ids', [10, 11]);       // Visa Granted + Enrolled
    $depositVisaStages  = $request->input('deposit_visa_stage_ids', [10, 11]);

    $startDate          = $request->input('start_date');
    $endDate            = $request->input('end_date');
    $depositStartDate   = $request->input('deposit_start_date');
    $depositEndDate     = $request->input('deposit_end_date');

    // Base query with filters
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
    if (!empty($instituteIds)) {
        $query->whereIn('university_id', $instituteIds);
    }
    if (!empty($intakeYear)) {
        $query->where('intakeYear', $intakeYear);
    }
    if (!empty($intake)) {
        $query->where('intake', $intake);
    }

     
    // -------------------------------
    // Total distinct brands (with filters)
    // -------------------------------
    $totalBrandsQuery = clone $query;
    $totalBrands = $totalBrandsQuery->distinct('brand_id')->count('brand_id');

    // -------------------------------
    // Total visa count
    // -------------------------------
    $totalVisasQuery = clone $query;
        if(!empty($depositVisaStages)){
             $totalVisasQuery->whereIn('deposit_stage_id', $depositVisaStages);
        }
       

        if ($depositStartDate) {
            $totalVisasQuery->whereDate('deposit_created_at', '>=', $depositStartDate);
        }
        if ($depositEndDate) {
            $totalVisasQuery->whereDate('deposit_created_at', '<=', $depositEndDate);
        }


         if(!empty($visaStages)){
            $totalVisasQuery->whereIn('stage_id', $visaStages);
        }
       
    
        

        if ($startDate) {
            $totalVisasQuery->whereDate('deposit_created_at', '>=', $startDate);
        }
        if ($endDate) {
            $totalVisasQuery->whereDate('deposit_created_at', '<=', $endDate);
        }
    
    $totalVisas = $totalVisasQuery->distinct('id')->count('id');

    // -------------------------------
    // Brand-wise visa counts
    // -------------------------------
    $brandVisaCounts = clone $query; 
        $brandVisaCounts = $brandVisaCounts
            ->select('brand_name', DB::raw('count(distinct id) as visa_count'))
            ->whereIn('deposit_stage_id', $depositVisaStages)
            ->whereIn('stage_id', $visaStages)
            ->groupBy('brand_id', 'brand_name');
     
    $brandVisaCounts = $brandVisaCounts->get();

    // -------------------------------
    // Intake-wise visa counts
    // -------------------------------
    $intakeVisaCounts = clone $query; 
        $intakeVisaCounts = $intakeVisaCounts
            ->select('intake', 'intakeYear', DB::raw('count(distinct id) as visa_count'))
            ->whereIn('deposit_stage_id', $depositVisaStages)
             ->whereIn('stage_id', $visaStages)
            ->groupBy('intake', 'intakeYear');
     
    $intakeVisaCounts = $intakeVisaCounts->get();

    // -------------------------------
    // Institute-wise visa counts
    // -------------------------------
    $instituteCounts = clone $query; 
        $instituteCounts = $instituteCounts
            ->select('university_name', DB::raw('count(distinct id) as application_count'))
            ->whereIn('deposit_stage_id', $depositVisaStages)
            ->whereIn('stage_id', $visaStages)
            ->groupBy('university_name')
            ->having('application_count', '>', 0);
    
    $instituteCounts = $instituteCounts->get();

    // -------------------------------
    // Month-wise visas
    // -------------------------------
    $monthWiseVisas = clone $query; 

        if(!empty($depositVisaStages)){
             $monthWiseVisas = $monthWiseVisas
            ->whereIn('deposit_stage_id', $depositVisaStages);
        }
        

        if ($depositStartDate) {
            $monthWiseVisas->whereDate('deposit_created_at', '>=', $depositStartDate);
        }
        if ($depositEndDate) {
            $monthWiseVisas->whereDate('deposit_created_at', '<=', $depositEndDate);
        }
   
        if(!empty($visaStages)){
             $monthWiseVisas = $monthWiseVisas
            ->whereIn('stage_id', $visaStages);
        }
        
       

        if ($startDate) {
            $monthWiseVisas->whereDate('deposit_created_at', '>=', $startDate);
        }
        if ($endDate) {
            $monthWiseVisas->whereDate('deposit_created_at', '<=', $endDate);
        }
     
    $monthWiseVisas = $monthWiseVisas->distinct('id')->count('id');

    // -------------------------------
    // Final response
    // -------------------------------
    return response()->json([
        'total_brands'      => $totalBrands,
        'total_visas'       => $totalVisas,
        'brand_visa_counts' => $brandVisaCounts,
        'institutes'        => $instituteCounts,
        'intakeVisaCounts'  => $intakeVisaCounts,
        'month_wise_visas'  => $monthWiseVisas,
        'filters' => [
            'brands'                => $brandIds,
            'regions'               => $regionIds,
            'branches'              => $branchIds,
            'institute_ids'         => $instituteIds,
            'visa_stage_ids'        => $visaStages,
            'deposit_visa_stage_ids'=> $depositVisaStages,
            'start_date'            => $startDate,
            'end_date'              => $endDate,
            'deposit_start_date'    => $depositStartDate,
            'deposit_end_date'      => $depositEndDate,
            'intake'                => $intake,
            'intakeYear'            => $intakeYear,
        ]
    ]);
}


    /**
     * Deposit Analysis Report
     */
    public function depositAnalysis(Request $request)
    {
        $brandIds  = $request->input('brand_ids', []);
        $regionIds = $request->input('region_ids', []);
        $branchIds = $request->input('branch_ids', []);
        $startDate = $request->input('start_date', '2025-01-01');
        $endDate   = $request->input('end_date', now()->toDateString());

        // Deposit stage IDs
        $depositStages = [5, 6, 7, 8, 9, 10, 11];

        // Base query
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

        // Get unique application IDs that moved to deposit stages
        $depositStageApplications = StageHistory::select('type_id')
            ->where('type', 'application')
            ->whereIn('stage_id', $depositStages)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->pluck('type_id')
            ->unique()
            ->toArray();

        $query->whereIn('id', $depositStageApplications);

        // Total deposits
        $totalDeposits = $query->distinct('id')->count('id');

        // Brand-wise deposits
        $brandDepositCounts = (clone $query)
            ->select('brand_id', DB::raw('count(distinct id) as deposit_count'))
            ->groupBy('brand_id')
            ->get();

        return response()->json([
            'total_deposits'       => $totalDeposits,
            'brand_deposit_counts' => $brandDepositCounts,
            'filters' => [
                'brands'    => $brandIds,
                'regions'   => $regionIds,
                'branches'  => $branchIds,
                'start_date'=> $startDate,
                'end_date'  => $endDate,
            ]
        ]);
    }
}
