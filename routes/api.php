<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginRegisterController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AllowanceOptionController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\CustomQuestionController;
use App\Http\Controllers\DeductionOptionController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\PayslipTypeController;
use App\Http\Controllers\TrainingTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LoanOptionController;
use App\Http\Controllers\UserController;
use App\Models\InterviewSchedule;
use App\Models\JobCategory;
use App\Models\TaskFile;
use App\Models\TrainingType;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes of authtication
Route::controller(LoginRegisterController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/registerAgent', 'registerAgent');
    Route::post('/login', 'login');
    Route::post('/validateEmpId', 'validateEmpId');
    Route::post('/googlelogin', 'googlelogin');
    Route::post('/changePassword', 'changePassword');
});

Route::get('/appMeta', [ProductController::class, 'appMeta']);
Route::post('/jobRequirement', [JobController::class, 'jobRequirement']);
Route::post('/jobApplyData', [JobController::class, 'jobApplyData']);


// Protected routes of product and logout
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/userDetail', [LoginRegisterController::class, 'userDetail']);
    Route::post('/logout', [LoginRegisterController::class, 'logout']);
    Route::get('/agentRequestGet', [AgentController::class, 'agentRequestGet']);
    Route::post('/agentRequestPost', [AgentController::class, 'agentRequestPost']);
    Route::post('/userTasksGet', [TaskController::class, 'userTasksGet']);
    Route::post('/createtask', [TaskController::class, 'createtask']);
    Route::post('/taskUpdate', [TaskController::class, 'taskUpdate']);
    Route::post('/updateTaskStatus', [TaskController::class, 'updateTaskStatus']);
    Route::post('/ShuffleTaskOwnership', [TaskController::class, 'ShuffleTaskOwnership']);
    Route::post('/getTaskDetails', [TaskController::class, 'getTaskDetails']);
    Route::post('/taskDiscussionStore', [TaskController::class, 'taskDiscussionStore']);
    Route::post('/taskDelete', [TaskController::class, 'taskDelete']);
    Route::get('/downloadTasks', [TaskController::class, 'downloadTasks']);

    // Leads start here
    Route::post('/getLeads', [LeadController::class, 'getLeads']);
    Route::post('/fetchColumns', [LeadController::class, 'fetchColumns']);
    Route::post('/importCsv', [LeadController::class, 'importCsv']);
    Route::post('/getLeadDetails', [LeadController::class, 'getLeadDetails']);
    Route::post('/updateLead', [LeadController::class, 'updateLead']);
    Route::post('/deleteBulkLeads', [LeadController::class, 'deleteBulkLeads']);
    Route::post('/updateBulkLead', [LeadController::class, 'updateBulkLead']);
    Route::post('/addLeadTags', [LeadController::class, 'addLeadTags']);
    Route::post('/convertToApplication', [LeadController::class, 'convertToApplication']);
    Route::post('/leadsLabels', [LeadController::class, 'leadsLabels']);
    Route::post('/leadLabelStore', [LeadController::class, 'leadLabelStore']);
    Route::post('/leadsDelete', [LeadController::class, 'leadsDelete']);
    Route::post('/updateLeadStage', [LeadController::class, 'updateLeadStage']);
    Route::post('/LeadOrgnizationUpdate', [LeadController::class, 'LeadOrgnizationUpdate']);
    Route::post('/LeadDriveLinkUpdate', [LeadController::class, 'LeadDriveLinkUpdate']);
    Route::post('/notesCreateOrUpdate', [LeadController::class, 'notesCreateOrUpdate']);



    // user/employees
    Route::post('/getEmployees', [UserController::class, 'getEmployees']);
    Route::get('/employees', [UserController::class, 'employees']);
    Route::get('/get/employee/Details', [UserController::class, 'EmployeeDetails']);

    // Hrm Internal Employee Note Create
    Route::post('/EmployeeNoteGet', [UserController::class, 'HrmInternalEmployeeNoteGet']);
    Route::post('/EmployeeNoteUpdate', [UserController::class, 'HrmInternalEmployeeNoteUpdate']);
    Route::post('/EmployeeNoteStore', [UserController::class, 'HrmInternalEmployeeNoteStore']);
    Route::post('/EmployeeNoteDelete', [UserController::class, 'HrmInternalEmployeeNoteDelete']);


    // Branches
    Route::post('/branchDetail', [BranchController::class, 'branchDetail']);

    // Training type
    Route::post('/addTrainingType', [TrainingTypeController::class, 'addTrainingType']);
    Route::get('/getTrainingTypes', [TrainingTypeController::class, 'getTrainingTypes']);
    Route::post('/updateTrainType', [TrainingTypeController::class, 'updateTrainType']);
    Route::post('/deleteTrainingType', [TrainingTypeController::class, 'deleteTrainingType']);

    // Department
    Route::post('/addDepartment', [DepartmentController::class, 'addDepartment']);
    Route::get('/getDepartments', [DepartmentController::class, 'getDepartments']);
    Route::post('/updateDepartment', [DepartmentController::class, 'updateDepartment']);
    Route::post('/deleteDepartment', [DepartmentController::class, 'deleteDepartment']);



    // AllowanceOption
    Route::post('/addAllowanceOption', [AllowanceOptionController::class, 'addAllowanceOption']);
    Route::get('/getAllowanceOptions', [AllowanceOptionController::class, 'getAllowanceOptions']);
    Route::post('/updateAllowanceOption', [AllowanceOptionController::class, 'updateAllowanceOption']);
    Route::post('/deleteAllowanceOption', [AllowanceOptionController::class, 'deleteAllowanceOption']);

    // LoanOption
    Route::post('/addLoanOption', [LoanOptionController::class, 'addLoanOption']);
    Route::get('/getLoanOptions', [LoanOptionController::class, 'getLoanOptions']);
    Route::post('/updateLoanOption', [LoanOptionController::class, 'updateLoanOption']);
    Route::post('/deleteLoanOption', [LoanOptionController::class, 'deleteLoanOption']);

    // DeductionOption
    Route::post('/addDeductionOption', [DeductionOptionController::class, 'addDeductionOption']);
    Route::get('/getDeductionOptions', [DeductionOptionController::class, 'getDeductionOptions']);
    Route::post('/updateDeductionOption', [DeductionOptionController::class, 'updateDeductionOption']);
    Route::post('/deleteDeductionOption', [DeductionOptionController::class, 'deleteDeductionOption']);

    // Branches
    Route::post('/getInterviews', [InterviewScheduleController::class, 'getInterviews']);
    Route::post('/showInterviews', [InterviewScheduleController::class, 'show']);
    Route::post('/updateInterviews', [InterviewScheduleController::class, 'update']);
    Route::post('/deleteInterviews', [InterviewScheduleController::class, 'destroy']);
    Route::post('/addInterveiw', [InterviewScheduleController::class, 'addInterveiw']);

    //  Job Applications
    Route::post('/getJobApplications', [JobApplicationController::class, 'getJobApplications']);
    Route::post('/getJobApplicationDetails', [JobApplicationController::class, 'getJobApplicationDetails']);
    Route::post('/archiveJobApplication', [JobApplicationController::class, 'archiveJobApplication']);
    Route::post('/getarchiveJobApplication', [JobApplicationController::class, 'getarchiveJobApplication']);
    Route::post('/jobBoardStore', [JobApplicationController::class, 'jobBoardStore']);
    Route::post('/getJobBoardDetail', [JobApplicationController::class, 'getJobBoardDetail']);
    Route::post('/deleteJobOnBoard', [JobApplicationController::class, 'deleteJobOnBoard']);
    Route::post('/addJobApplicationSkill', [JobApplicationController::class, 'addJobApplicationSkill']);
    Route::post('/addJobApplicationNote', [JobApplicationController::class, 'addJobApplicationNote']);
    Route::post('/deleteJobApplicationNote', [JobApplicationController::class, 'deleteJobApplicationNote']);
    Route::post('/getjobBoardStore', [JobApplicationController::class, 'getjobBoardStore']);
    Route::post('archiveApplication', [JobApplicationController::class, 'archiveApplication']);
    Route::post('/job-board/update', [JobApplicationController::class, 'jobBoardUpdate']);
    // Jobs
    Route::post('/getJobs', [JobController::class, 'getJobs']);
    Route::post('/createJob', [JobController::class, 'createJob']);
    Route::post('/getJobDetails', [JobController::class, 'getJobDetails']);
    Route::post('/updateJob', [JobController::class, 'updateJob']);
    Route::post('/deleteJob', [JobController::class, 'deleteJob']);

    // Custom Question
    Route::post('/getQuestions', [CustomQuestionController::class, 'getQuestions']);
    Route::post('/createQuestion', [CustomQuestionController::class, 'createQuestion']);
    Route::post('/updateQuestion', [CustomQuestionController::class, 'updateQuestion']);
    Route::post('/deleteQuestion', [CustomQuestionController::class, 'deleteQuestion']);

    // paytype

    Route::get('/payslip-types', [PayslipTypeController::class, 'index'])->middleware('can:manage payslip type');
    Route::get('/pluckPayslip', [PayslipTypeController::class, 'pluckPayslip'])->middleware('can:manage payslip type');
    Route::post('/payslip-store', [PayslipTypeController::class, 'store'])->middleware('can:create payslip type');
    Route::get('/payslip-get/{id}', [PayslipTypeController::class, 'show'])->middleware('can:manage payslip type');
    Route::put('/payslip-update/{id}', [PayslipTypeController::class, 'update'])->middleware('can:edit payslip type');
    Route::post('/payslip-delete/{id}', [PayslipTypeController::class, 'destroy'])->middleware('can:delete payslip type');




    // general routes
    Route::get('/getAllBrands', [GeneralController::class, 'getAllBrands']);
    Route::post('/getDefaultFiltersData', [GeneralController::class, 'getDefaultFiltersData']);
    Route::post('/getRegionBrands', [GeneralController::class, 'getRegionBrands']);
    Route::post('/getFilterData', [GeneralController::class, 'getFilterData']);
    Route::post('/getFilterBranchUsers', [GeneralController::class, 'getFilterBranchUsers']);
    Route::post('/getSavedFilters', [GeneralController::class, 'getSavedFilters']);
    Route::get('/getSources', [GeneralController::class, 'getSources']);
    Route::get('/getBranches', [GeneralController::class, 'getBranches']);
    Route::get('/getStages', [GeneralController::class, 'getStages']);
    Route::get('/getTags', [GeneralController::class, 'getTags']);
    Route::get('/getJobCategories', [GeneralController::class, 'getJobCategories']);
});
