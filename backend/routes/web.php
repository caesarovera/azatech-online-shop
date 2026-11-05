<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Backend ini murni API. Halaman root diarahkan ke dokumentasi OpenAPI (Scramble).
Route::redirect('/', '/docs/api');
