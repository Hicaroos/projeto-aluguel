<?php

use App\Http\Controllers\ShowcaseController;
use App\Http\Controllers\ShowcaseListingController;
use App\Http\Controllers\ShowcaseVisitController;
use App\Http\Middleware\ActAsShowcaseVisitor;
use Illuminate\Support\Facades\Route;

/*
 * The public showcase of each account, at its own address right after the domain. These routes come
 * last, so the app's own pages always win, and the showcase settings refuse the app's addresses.
 */

Route::middleware(ActAsShowcaseVisitor::class)
    ->prefix('{showcase:showcase_slug}')
    ->where(['showcase' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->name('showcase.')
    ->group(function () {
        Route::get('/', [ShowcaseController::class, 'home'])->name('home');
        Route::get('imoveis', [ShowcaseController::class, 'index'])->name('index');
        Route::get('imovel/{property}', [ShowcaseController::class, 'show'])->whereNumber('property')->name('show');
        Route::post('imovel/{property}/visita', [ShowcaseVisitController::class, 'store'])
            ->whereNumber('property')
            ->middleware('throttle:6,1')
            ->name('visits.store');
        Route::post('anunciar', [ShowcaseListingController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('listings.store');
        Route::get('fotos/{photo}', [ShowcaseController::class, 'photo'])->whereNumber('photo')->name('photos.show');
        Route::get('fotos/{photo}/miniatura', [ShowcaseController::class, 'thumbnail'])->whereNumber('photo')->name('photos.thumbnail');
        Route::get('logo', [ShowcaseController::class, 'logo'])->name('logo');
    });
