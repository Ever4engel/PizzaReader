<?php

use App\Http\Controllers\Reader\ReaderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

if (!function_exists('cacheable_routes')) {
    function cacheable_routes() {
        Route::get('/comics/', [ReaderController::class, 'comics'])->name('comics');
        Route::get('/recommended/', [ReaderController::class, 'recommended'])->name('recommended');
        Route::get('/comics/{comic}', [ReaderController::class, 'comic'])->name('comic');
        Route::get('/info/', [ReaderController::class, 'info'])->name('info');
    }
}

Route::name('reader.')->group(function () {
    if (config('settings.cache_proxy_enabled') && !isset($_COOKIE[config('session.cookie')])) {
        Route::middleware('cache.headers:public;max_age=' . intval(config('settings.cache_proxy_max_age', 3600)) . ';etag')->group(function () {
            Route::middleware('enforce.html')->group(function () { cacheable_routes(); });
        });
    } else {
        cacheable_routes();
    }

    // TODO if you want to cache this, you need to handle the increment views using the "vote" route, but it won't work with the current Tachiyomi extension
    Route::get('/read/{comic}/{language}/{ch?}', [ReaderController::class, 'chapter'])->name('read')->where('ch', '.*');
    Route::get('/download/{comic}/{language}/{ch?}', [ReaderController::class, 'download'])->name('download')->where('ch', '.*');
    Route::get('/pdf/{comic}/{language}/{ch?}', [ReaderController::class, 'pdf'])->name('pdf')->where('ch', '.*');
    Route::get('/vote/{chapter_id}', [ReaderController::class, 'get_vote'])->name('get_vote')->where('ch', '.*');
    Route::post('/vote/{comic}/{language}/{ch?}', [ReaderController::class, 'vote'])->name('vote')->where('ch', '.*');
    // TODO how to purge these?
    Route::get('/search/{search}', [ReaderController::class, 'search'])->name('search');
    Route::get('/targets/{target}', [ReaderController::class, 'targets'])->name('targets');
    Route::get('/genres/{genre}', [ReaderController::class, 'genres'])->name('genres');
});
