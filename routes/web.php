<?php

use Illuminate\Support\Facades\Route;

// the website is the front door (the original single-page review tool is now inside the admin dashboard)
Route::redirect('/', '/v2');

require __DIR__ . '/v2.php';
