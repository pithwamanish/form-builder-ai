<?php

use App\Http\Controllers\AiStreamController;
use App\Http\Controllers\FormSubmissionController;
use App\Livewire\AiFormGenerator;
use App\Livewire\DocumentImporter;
use App\Livewire\FormBuilder;
use App\Livewire\FormList;
use App\Livewire\PublicFormFill;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('forms.index');
});

// All Saved Forms Dashboard
Route::get('/forms', FormList::class)->name('forms.index');

// Form Builder Studio
Route::get('/builder', FormBuilder::class)->name('forms.create');
Route::get('/builder/{uuid}', FormBuilder::class)->name('forms.edit');

// AI Generator & Importer
Route::get('/ai-generator', AiFormGenerator::class)->name('forms.ai');
Route::get('/import', DocumentImporter::class)->name('forms.import');

// Real-Time SSE Token Streaming Endpoint Proxy
Route::match(['get', 'post'], '/api/ai/stream-generate', AiStreamController::class)->name('api.ai.stream');

// Submissions Dashboard, File Downloads & CSV Export
Route::get('/builder/{uuid}/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions');
Route::get('/builder/{uuid}/submissions/export', [FormSubmissionController::class, 'exportCsv'])->name('forms.submissions.export');
Route::get('/builder/{uuid}/submissions/{submissionId}/download/{fieldKey}', [FormSubmissionController::class, 'downloadFile'])->name('forms.submissions.download');

// Sample Files Download Endpoint (Single Source of Truth from storage/samples)
Route::get('/samples/{filename}', function (string $filename) {
    $path = storage_path("samples/{$filename}");
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->download($path);
})->name('samples.download');

// Public Form Fill Endpoint with Rate Limiting (Part D Differentiator)
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/f/{slug}', PublicFormFill::class)->name('forms.public');
});

// Free Tier Diagnostics & Health Endpoint
Route::get('/api/health', function () {
    $dbConnected = false;
    $dbDriver = 'unknown';
    $dbHost = 'N/A';
    $formsCount = 0;
    $error = null;

    try {
        $connection = \Illuminate\Support\Facades\DB::connection();
        $dbDriver = $connection->getDriverName();
        $dbHost = config("database.connections.{$dbDriver}.host", 'N/A');
        $formsCount = \App\Models\Form::count();
        $dbConnected = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    $logs = [];
    $logPath = storage_path('logs/laravel.log');
    if (file_exists($logPath) && is_readable($logPath)) {
        $lines = file($logPath);
        $logs = array_slice($lines, -30);
    }

    $cloudinaryStatus = \App\Services\CloudinaryStorageService::getCredentials();

    return response()->json([
        'status' => $dbConnected ? 'ok' : 'error',
        'timestamp' => now()->toIso8601String(),
        'database' => [
            'connected' => $dbConnected,
            'driver' => $dbDriver,
            'host' => $dbHost,
            'forms_count' => $formsCount,
            'error' => $error,
        ],
        'cloudinary' => [
            'configured' => $cloudinaryStatus['configured'],
            'cloud_name' => $cloudinaryStatus['cloud_name'] ?: 'NOT_SET',
            'api_key_set' => !empty($cloudinaryStatus['api_key']),
            'api_secret_set' => !empty($cloudinaryStatus['api_secret']),
        ],
        'ai_service' => [
            'provider' => config('ai.provider', env('AI_PROVIDER', 'mistral')),
            'mistral_key_set' => !empty(config('ai.mistral.api_key', env('MISTRAL_API_KEY'))),
            'mistral_model' => config('ai.mistral.model', env('MISTRAL_MODEL', 'mistral-small-latest')),
            'openai_key_set' => !empty(config('ai.openai.api_key', env('OPENAI_API_KEY'))),
            'python_service_url' => env('FASTAPI_AI_URL', env('AI_SERVICE_URL', 'http://127.0.0.1:8000')),
        ],
        'environment' => [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
        ],
        'recent_logs' => request()->has('logs') ? implode('', $logs) : 'Pass ?logs=1 to include recent laravel.log output',
    ]);
})->name('api.health');
