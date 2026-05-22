<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateNoteController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\CarnetController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CounselorController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RenovationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WhatsAppWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas públicas — solo lectura, campos seguros, para el sitio web
Route::prefix('public')->group(function () {
    Route::get('doctors', [DoctorController::class, 'publicIndex']);
    Route::get('specialties', [SpecialtyController::class, 'publicIndex']);
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{department}/cities', [CityController::class, 'getByDepartment']);
});

// Webhook WhatsApp — público, Meta lo llama directamente sin autenticación
Route::prefix('webhook')->group(function () {
    Route::get('whatsapp',  [WhatsAppWebhookController::class, 'verify']);
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle']);
});

Route::middleware('auth:sanctum')->group(function () {

    // Usuarios - Franquicias
    Route::get('users/active', [UserController::class, 'activeFranchises']);
    Route::apiResource('users', UserController::class);
    Route::post('user/change-password', [UserController::class, 'changePassword']);

    //Vendedores
    Route::get('counselors/active', [CounselorController::class, 'activeCounselors']);
    Route::get('counselors/check-id-card', [CounselorController::class, 'checkIdCard']);
    Route::apiResource('counselors', CounselorController::class);

    //Convenios
    Route::get('agreements/active', [AgreementController::class, 'activeAgreements']);
    Route::apiResource('agreements', AgreementController::class);

    //Afiliados / Usuarios
    Route::get('affiliates/check-id-card',   [AffiliateController::class, 'checkIdCard']);
    Route::get('affiliates/expiring-today',  [AffiliateController::class, 'expiringToday']);
    Route::get('affiliates/by-id-card',      [AffiliateController::class, 'byIdCard']);
    Route::post('affiliates/{id}/carnet',    [CarnetController::class, 'send']);
    Route::apiResource('affiliates', AffiliateController::class);
    // Notas de afiliados
    Route::get('affiliates/{affiliate}/notes',           [AffiliateNoteController::class, 'index']);
    Route::post('affiliates/{affiliate}/notes',          [AffiliateNoteController::class, 'store']);
    Route::delete('affiliates/{affiliate}/notes/{note}', [AffiliateNoteController::class, 'destroy']);

    // Beneficiarios
    Route::apiResource('beneficiaries', BeneficiaryController::class);

    // Renovaciones
    Route::apiResource('renovations', RenovationController::class);

    // Especialidades
    Route::apiResource('specialties', SpecialtyController::class);

    // Médicos
    Route::get('doctors/by-specialty', [DoctorController::class, 'bySpecialty']);
    Route::apiResource('doctors', DoctorController::class);

    // Departamentos (solo index)
    Route::get('departments', [DepartmentController::class, 'index']);

    // Ciudades por departamento (usa Route Model Binding Department $department)
    Route::get('departments/{department}/cities', [CityController::class, 'getByDepartment']);

    // Citas
    Route::get('appointments/today', [AppointmentController::class, 'today']);
    Route::apiResource('appointments', AppointmentController::class);

    // Configuración global del panel
    Route::get('settings', [SettingController::class, 'index']);
    Route::patch('settings/{setting}', [SettingController::class, 'update']);

    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/charts', [DashboardController::class, 'charts']);
});