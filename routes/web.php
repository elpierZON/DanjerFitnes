<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\ReportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Ruta principal
Route::get('/', function () {
    return view('home');
})->name('home');

// SEO. Se sirven desde aquí y no como ficheros en public/ para que la URL
// absoluta salga de APP_URL y no haya que tocarla en cada despliegue.
Route::get('/robots.txt', function () {
    return response()
        ->view('seo.robots', ['sitemap' => url('sitemap.xml')])
        ->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/sitemap.xml', function () {
    return response()
        ->view('seo.sitemap')
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

// Rutas de autenticación (solo para invitados)
Route::middleware('guest')->group(function () {
    // Mostrar formularios
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');

    // Procesar formularios
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.post');
});

// Rutas protegidas (requieren autenticación)
Route::middleware('auth')->group(function () {
    // Dashboard Cliente - Solo para clientes (no entrenadores)
    Route::middleware('client')->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard.index');
        })->name('dashboard');
    });

    // Dashboard Entrenador - Solo para entrenadores
    Route::middleware('trainer')->group(function () {
        Route::get('/trainer/dashboard', function () {
            return view('trainer.dashboard');
        })->name('trainer.dashboard');
    });

    // Dashboard Administrador - Solo para administradores
    Route::middleware('administrator')->group(function () {
        Route::get('livewire/admin/dashboard', function () {
            return view('livewire.admin.dashboard');
        })->name('admin.dashboard');
        
        // Rutas de reportes
        Route::get('/admin/reports/download', [ReportController::class, 'downloadMembershipReport'])->name('admin.reports.download');
    });

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
