<?php

use Illuminate\Support\Facades\Route;

Route::get('/app/{any?}', function () {
    $spa = public_path('app/index.html');
    abort_unless(file_exists($spa), 404, 'Frontend build missing. Run npm run build in frontend/.');

    return response()->file($spa);
})->where('any', '.*');

Route::get('/', function () {
    if (file_exists(public_path('app/index.html'))) {
        return redirect('/app/');
    }

    return response()->json([
        'name' => config('app.name'),
        'api' => url('/api'),
        'frontend_dev' => env('FRONTEND_URL', 'http://localhost:5173'),
        'login_hint' => 'admin@example.com / password',
    ]);
});
