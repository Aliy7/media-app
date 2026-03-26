<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Livewire\MediaLibrary;
use App\Livewire\MediaUploader;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', MediaLibrary::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/media/upload', MediaUploader::class)->name('media.upload');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::get('/media/{uuid}', [MediaController::class, 'show'])->name('media.show');
    Route::get('/media/{uuid}/thumbnail', [MediaController::class, 'thumbnail'])->name('media.thumbnail');
    Route::delete('/media/{uuid}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/media/{uuid}/retry', [MediaController::class, 'retry'])->name('media.retry');
});

require __DIR__.'/auth.php';
