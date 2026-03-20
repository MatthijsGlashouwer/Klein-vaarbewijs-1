<?php

use App\Http\Controllers\Kvb1QuizController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::controller(Kvb1QuizController::class)->group(function () {
    Route::get('oefentoets/kvb1', 'show')->name('kvb1.quiz');
    Route::get('oefentoets/kvb1/start', 'start')->name('kvb1.quiz.start');
    Route::post('oefentoets/kvb1/check', 'check')->name('kvb1.quiz.check');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
