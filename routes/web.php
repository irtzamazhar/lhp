<?php

use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/events')->name('home');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/visual-data', [EventController::class, 'visualData'])->name('events.visual-data');
Route::post('events', [EventController::class, 'store'])->name('events.store');
Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

Route::get('events-visual-1', [EventController::class, 'visualOne'])->name('events.visual1');
Route::get('events-visual-2', [EventController::class, 'visualTwo'])->name('events.visual2');

Route::get('attendees', [AttendeeController::class, 'index'])->name('attendees.index');
Route::post('attendees', [AttendeeController::class, 'store'])->name('attendees.store');
