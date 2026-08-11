<?php

declare(strict_types=1);

namespace App\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface AiServiceInterface
{
    /**
     * Stream AI form schema generation as Server-Sent Events (SSE).
     */
    public function streamFormGeneration(string $prompt): StreamedResponse;

    /**
     * Generate a form JSON schema from a natural language prompt.
     */
    public function generateFormSchema(string $prompt, ?int $formId = null): array;

    /**
     * Modify an existing form schema using an AI instruction prompt.
     */
    public function editFormSchema(array $existingSchema, string $instruction, ?int $formId = null): array;

    /**
     * Validate if a schema array meets strict structural schema requirements.
     */
    public function validateSchema(?array $schema): bool;

    /**
     * Repair truncated or broken JSON strings.
     */
    public function repairSchema(string $rawContent): array;
}
