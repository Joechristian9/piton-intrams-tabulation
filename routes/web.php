<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\JudgeController;
use App\Http\Controllers\ScoreSubmissionController;
use App\Http\Controllers\ResultController\TopFiveSelectionResultController;
use App\Http\Controllers\ResultController\TopFiveCandidateResultController;
use App\Http\Controllers\TopFiveSelectionScoreController;
use App\Http\Controllers\TopFiveCandidateController;
use App\Http\Controllers\TopFiveScoreController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


// Categories Routes
Route::middleware('auth')->group(function () {
    Route::get('/production_number', [CandidateController::class, 'production_number'])->name('production_number');
    Route::get('/casual_wear', [CandidateController::class, 'casual_wear'])->name('casual_wear');
    Route::get('/swim_wear', [CandidateController::class, 'swim_wear'])->name('swim_wear');
    Route::get('/formal_wear', [CandidateController::class, 'formal_wear'])->name('formal_wear');
    Route::get('/closed_door_interview', [CandidateController::class, 'closed_door_interview'])->name('closed_door_interview');
});

// Scores Routes (Top 5 Selection)
Route::middleware('auth')->group(function () {
    Route::post('/production_number/scores', [TopFiveSelectionScoreController::class, 'production_number_store'])
        ->name('production_number.store');
    Route::post('/casual_wear/scores', [TopFiveSelectionScoreController::class, 'casual_wear_store'])
        ->name('casual_wear.store');
    Route::post('/swim_wear/scores', [TopFiveSelectionScoreController::class, 'swim_wear_store'])
        ->name('swim_wear.store');
    Route::post('/formal_wear/scores', [TopFiveSelectionScoreController::class, 'formal_wear_store'])
        ->name('formal_wear.store');
    Route::post('/closed_door_interview/scores', [TopFiveSelectionScoreController::class, 'closed_door_interview_store'])
        ->name('closed_door_interview.store');
});

// Admin Results Routes (Top 5 Selection)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/production_number', [TopFiveSelectionResultController::class, 'productionNumberResults'])
        ->name('admin.production_number');
    Route::get('/admin/casual_wear', [TopFiveSelectionResultController::class, 'casualWearResults'])
        ->name('admin.casual_wear');
    Route::get('/admin/swim_wear', [TopFiveSelectionResultController::class, 'swimWearResults'])
        ->name('admin.swim_wear');
    Route::get('/admin/formal_wear', [TopFiveSelectionResultController::class, 'formalWearResults'])
        ->name('admin.formal_wear');
    Route::get('/admin/closed_door_interview', [TopFiveSelectionResultController::class, 'closedDoorInterviewResults'])
        ->name('admin.closed_door_interview');
    Route::get('/admin/top_five_selection_result', [TopFiveSelectionResultController::class, 'topFiveSelectionResults'])
        ->name('admin.top_five_selection');
});

// Category Routes (Top 5 Finalists)
Route::middleware('auth')->group(function () {

    // Display Routes
    Route::get('/beauty_face_figure', [TopFiveCandidateController::class, 'faceAndFigure'])
        ->name('beauty_face_figure');
    Route::get('/delivery', [TopFiveCandidateController::class, 'delivery'])
        ->name('delivery');
    Route::get('/overall_appeal', [TopFiveCandidateController::class, 'overallAppeal'])
        ->name('overall_appeal');

    // Store Routes
    Route::post('/beauty_face_figure/store', [TopFiveScoreController::class, 'faceAndFigureStore'])
        ->name('beauty_face_figure.store');
    Route::post('/delivery/store', [TopFiveScoreController::class, 'deliveryStore'])
        ->name('delivery.store');
    Route::post('/overall_appeal/store', [TopFiveScoreController::class, 'overallAppealStore'])
        ->name('overall_appeal.store');
});


// Admin Set Top 5 Candidates Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/top-five', [TopFiveSelectionResultController::class, 'setTopFive'])
        ->name('topFive.set');
});


//Admin Top 5 Candidates Result Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/beauty_face_figure', [TopFiveCandidateResultController::class, 'beautyFaceResults'])
        ->name('admin.beauty_face_figure');
    Route::get('/admin/delivery', [TopFiveCandidateResultController::class, 'deliveryResults'])
        ->name('admin.delivery');
    Route::get('/admin/overall_appeal', [TopFiveCandidateResultController::class, 'overallAppealResults'])
        ->name('admin.overall_appeal');
    Route::get('/admin/total_results', [TopFiveCandidateResultController::class, 'totalResults'])
        ->name('admin.top_five_finalist');
});

// Admin Judges Management Routes
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/admin/judges', [JudgeController::class, 'index'])
        ->name('admin.judges.index');
    Route::post('/admin/judges', [JudgeController::class, 'store'])
        ->name('admin.judges.store');
    Route::put('/admin/judges/{judge}', [JudgeController::class, 'update'])
        ->name('admin.judges.update');
});

// Admin toast alerts for judge submissions
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/score-submissions', [ScoreSubmissionController::class, 'index'])
        ->name('admin.score_submissions');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
