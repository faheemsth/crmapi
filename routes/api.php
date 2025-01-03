<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginRegisterController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\TaskController;
use App\Models\TaskFile;

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
Route::controller(LoginRegisterController::class)->group(function() {
    Route::post('/register', 'register');
    Route::post('/registerAgent', 'registerAgent');
    Route::post('/login', 'login');
    Route::post('/googlelogin', 'googlelogin');
    Route::post('/changePassword', 'changePassword');
});

Route::get('/appMeta', [ProductController::class, 'appMeta']);


// Protected routes of product and logout
Route::middleware('auth:sanctum')->group( function () {
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
    Route::post('/getDefaultFiltersData', [GeneralController::class, 'getDefaultFiltersData']);
    Route::post('/getRegionBrands', [GeneralController::class, 'getRegionBrands']);
    Route::post('/getFilterData', [GeneralController::class, 'getFilterData']);
    Route::post('/getFilterBranchUsers', [GeneralController::class, 'getFilterBranchUsers']);

});
