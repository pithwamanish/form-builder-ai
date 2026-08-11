<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\AiServiceInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiStreamController extends Controller
{
    /**
     * Handle real-time SSE token streaming for AI form generation.
     */
    public function __invoke(Request $request, AiServiceInterface $aiService): StreamedResponse
    {
        $prompt = (string) $request->input('prompt', 'Simple Contact Form');

        return $aiService->streamFormGeneration($prompt);
    }
}
