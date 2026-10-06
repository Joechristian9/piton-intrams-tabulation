<?php

use App\Http\Controllers\Admin\CandidateController as AdminCandidateController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventJudgeController;
use App\Http\Controllers\Admin\FinalistController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\NotifyController;
use App\Http\Controllers\Admin\ResultsController;
use App\Http\Controllers\Admin\ScoreSubmissionController as AdminScoreSubmissionController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Judge\HomeController;
use App\Http\Controllers\Judge\NotificationController;
use App\Http\Controllers\Judge\ScoringController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Judges land in their event; admins get the dashboard.
Route::get('/dashboard', HomeController::class)->middleware(['auth', 'verified'])->name('dashboard');

// Judges: one scoring page per category of their own event, plus notifications.
Route::middleware('auth')->group(function () {
    Route::get('/score/{category}', [ScoringController::class, 'show'])->name('score.show');
    Route::post('/score/{category}', [ScoringController::class, 'store'])->name('score.store');
    Route::get('/judge/notifications', NotificationController::class)->name('judge.notifications');
});

// Admins: events and their setup.
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/admin/events', [EventController::class, 'index'])->name('admin.events.index');
    Route::post('/admin/events', [EventController::class, 'store'])->name('admin.events.store');
    Route::get('/admin/events/{event}/edit', [EventController::class, 'edit'])->name('admin.events.edit');
    Route::put('/admin/events/{event}', [EventController::class, 'update'])->name('admin.events.update');
    Route::delete('/admin/events/{event}', [EventController::class, 'destroy'])->name('admin.events.destroy');
    Route::post('/admin/events/{event}/start', [EventController::class, 'start'])->name('admin.events.start');
    Route::post('/admin/events/{event}/close', [EventController::class, 'close'])->name('admin.events.close');
    Route::post('/admin/events/{event}/duplicate', [EventController::class, 'duplicate'])->name('admin.events.duplicate');

    Route::post('/admin/events/{event}/groups', [GroupController::class, 'store'])->name('admin.groups.store');
    Route::put('/admin/groups/{group}', [GroupController::class, 'update'])->name('admin.groups.update');
    Route::delete('/admin/groups/{group}', [GroupController::class, 'destroy'])->name('admin.groups.destroy');

    Route::post('/admin/events/{event}/categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/admin/categories/{category}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/admin/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');

    Route::post('/admin/events/{event}/candidates', [AdminCandidateController::class, 'store'])->name('admin.candidates.store');
    // Sent as POST with _method=PUT: photo uploads need multipart form data.
    Route::put('/admin/candidates/{candidate}', [AdminCandidateController::class, 'update'])->name('admin.candidates.update');
    Route::delete('/admin/candidates/{candidate}', [AdminCandidateController::class, 'destroy'])->name('admin.candidates.destroy');

    Route::post('/admin/events/{event}/judges', [EventJudgeController::class, 'store'])->name('admin.event-judges.store');
    Route::get('/admin/events/{event}/judges/slips', [EventJudgeController::class, 'slips'])->name('admin.event-judges.slips');
    Route::put('/admin/judges/{judge}', [EventJudgeController::class, 'update'])->name('admin.event-judges.update');
    Route::post('/admin/judges/{judge}/reset-password', [EventJudgeController::class, 'reset'])->name('admin.event-judges.reset');
    Route::delete('/admin/judges/{judge}', [EventJudgeController::class, 'destroy'])->name('admin.event-judges.destroy');

    // The app-wide color theme.
    Route::get('/admin/theme', [ThemeController::class, 'edit'])->name('admin.theme.edit');
    Route::put('/admin/theme', [ThemeController::class, 'update'])->name('admin.theme.update');
    Route::post('/admin/themes', [ThemeController::class, 'store'])->name('admin.themes.store');
    Route::put('/admin/themes/{customTheme}', [ThemeController::class, 'updateCustom'])->name('admin.themes.update');
    Route::delete('/admin/themes/{customTheme}', [ThemeController::class, 'destroy'])->name('admin.themes.destroy');
});

// Admins: one event's results, finalists, notifications and live submission alerts.
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin/events/{event}')->group(function () {
    Route::get('/results/categories/{category}', [ResultsController::class, 'category'])->name('admin.results.category');
    Route::get('/results/round1', [ResultsController::class, 'round1'])->name('admin.results.round1');
    Route::get('/results/standings', [ResultsController::class, 'standings'])->name('admin.results.standings');
    Route::post('/finalists', [FinalistController::class, 'store'])->name('admin.finalists.set');
    Route::get('/notify', [NotifyController::class, 'index'])->name('admin.notify');
    Route::post('/notify', [NotifyController::class, 'store'])->name('admin.notify.send');
    Route::get('/score-submissions', [AdminScoreSubmissionController::class, 'index'])->name('admin.events.score_submissions');
});

// Account page: admins only. Judge accounts are managed by the admin (deleting one
// would also delete its scores).
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
