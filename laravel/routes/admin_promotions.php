<?php

use App\Http\Controllers\Admin\AdminPromotionController;
use Illuminate\Support\Facades\Route;

Route::get('promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
Route::get('promotions/create', [AdminPromotionController::class, 'create'])->name('promotions.create');
Route::post('promotions', [AdminPromotionController::class, 'store'])->name('promotions.store');
Route::get('promotions/{promotion}/edit', [AdminPromotionController::class, 'edit'])->name('promotions.edit');
Route::put('promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('promotions.update');
Route::patch('promotions/{promotion}/toggle', [AdminPromotionController::class, 'toggle'])->name('promotions.toggle');
Route::delete('promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('promotions.destroy');
