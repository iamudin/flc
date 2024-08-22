<?php
use Illuminate\Support\Facades\Route;
use Leazycms\FLC\Controllers\FileManagerController;

Route::match(['post','get'],'media/destroy', [FileManagerController::class, 'destroy'])->name('media.destroy');
Route::match(['post','get'], 'media/upload', [FileManagerController::class, 'upload'])->name('media.upload');
Route::match(['post', 'get'], 'media/{slug}', [FileManagerController::class, 'stream_by_id'])
    ->where('slug', '(?!' . implode('|', ['destroy', 'upload']) . ')[a-zA-Z0-9-]+(\.('.implode('|', lw_ext()).'))$')->name('stream');
