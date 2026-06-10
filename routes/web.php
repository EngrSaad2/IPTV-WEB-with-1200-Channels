<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\MovieController;

// Page Views
Route::get('/', function () {
    return view('tv');
})->name('home');

Route::get('/movies', function () {
    return view('movies');
})->name('movies');

Route::get('/movies/{id}', function ($id) {
    return view('movie-detail', ['id' => $id]);
})->name('movie.detail');

Route::get('/favorites', function () {
    return view('favorites');
})->name('favorites');

Route::get('/profile', function () {
    return view('profile');
})->name('profile');

Route::get('/dev-info', function () {
    return redirect()->away('https://engr-saad.com/');
})->name('dev-info');

// API Proxy Routes
Route::prefix('api')->group(function () {
    Route::get('/channels', [ChannelController::class, 'index']);
    
    Route::prefix('movies')->group(function () {
        Route::get('/trending', [MovieController::class, 'trending']);
        Route::get('/new-releases', [MovieController::class, 'newReleases']);
        Route::get('/top-rated', [MovieController::class, 'topRated']);
        Route::get('/genre/{genreId}', [MovieController::class, 'byGenre']);
        Route::get('/search', [MovieController::class, 'search']);
        Route::get('/detail/{movieId}', [MovieController::class, 'detail']);
    });
});
