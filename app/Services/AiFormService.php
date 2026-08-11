<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiServiceInterface;
use App\Models\AiGenerationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiFormService implements AiServiceInterface
{
    protected string $fastapiUrl;
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->fastapiUrl = config('ai.fastapi_url', env('FASTAPI_AI_URL', 'http://127.0.0.1:8000'));
        $mistralKey = config('ai.mistral.api_key', env('MISTRAL_API_KEY', ''));
        $openaiKey = config('ai.openai.api_key', env('OPENAI_API_KEY', ''));
        $genericKey = env('AI_API_KEY', '');

        if (!empty($mistralKey)) {
            $this->apiKey = $mistralKey;
            $this->model = config('ai.mistral.model', env('MISTRAL_MODEL', 'mistral-small-latest'));
            $this->baseUrl = config('ai.mistral.base_url', env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1/chat/completions'));
        } else {
            $this->apiKey = !empty($openaiKey) ? $openaiKey : $genericKey;
            $this->model = config('ai.openai.model', env('OPENAI_MODEL', 'gpt-4o-mini'));
            $this->baseUrl = config('ai.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1/chat/completions'));
        }
    }

    /**
     * Stream AI form schema generation as Server-Sent Events (SSE).
     * 3-Tier Resilient Streaming Architecture:
     *   Tier 1: FastAPI Python Microservice (if reachable)
     *   Tier 2: Direct Laravel HTTP Stream to Mistral / OpenAI API
     *   Tier 3: Animated Mock SSE Token Stream fallback
     */
    public function streamFormGeneration(string $prompt): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fastapiUrl = !empty($this->fastapiUrl) ? $this->fastapiUrl : env('FASTAPI_AI_URL', '');
        $hasKey = !empty($this->apiKey) && !in_array($this->apiKey, ['your_openai_api_key_here', 'your_mistral_api_key_here']);

        return response()->stream(function () use ($fastapiUrl, $prompt, $hasKey) {
            $streamed = false;

            // Tier 1: Try Python FastAPI Microservice over SSE stream (if configured)
            if (!empty($fastapiUrl) && !in_array(strtolower($fastapiUrl), ['disabled', 'false', 'none', 'off'])) {
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(30)
                        ->withOptions(['stream' => true])
                        ->post(rtrim($fastapiUrl, '/') . '/stream-generate-form', [
                            'prompt' => $prompt,
                        ]);

                    if ($response->successful()) {
                        $body = $response->toPsrResponse()->getBody();
                        while (!$body->eof()) {
                            $chunk = $body->read(1024);
                            echo $chunk;
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }
                        $streamed = true;
                    }
                } catch (\Exception $e) {
                    Log::info("FastAPI SSE Stream unavailable, falling back to direct LLM stream: " . $e->getMessage());
                }
            }

            // Tier 2: Direct Laravel Streaming to Mistral / OpenAI API
            if (!$streamed && $hasKey) {
                try {
                    $systemPrompt = $this->getSystemPrompt();
                    $userPrompt = "Create a complete, detailed form JSON schema for: \"{$prompt}\"";

                    $response = Http::withToken($this->apiKey)
                        ->timeout(45)
                        ->withOptions(['stream' => true])
                        ->post($this->baseUrl, [
                            'model' => $this->model,
                            'messages' => [
                                ['role' => 'system', 'content' => $systemPrompt],
                                ['role' => 'user', 'content' => $userPrompt],
                            ],
                            'temperature' => 0.7,
                            'stream' => true,
                        ]);

                    if ($response->successful()) {
                        $body = $response->toPsrResponse()->getBody();
                        while (!$body->eof()) {
                            $chunk = $body->read(1024);
                            echo $chunk;
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }
                        $streamed = true;
                    }
                } catch (\Exception $e) {
                    Log::error("Direct LLM SSE Stream Exception: " . $e->getMessage());
                }
            }

            // Tier 3: Mock SSE Token Stream if no external LLM API is available
            if (!$streamed) {
                $mockSchema = $this->generateMockSchema($prompt);
                $jsonStr = json_encode($mockSchema, JSON_PRETTY_PRINT);
                $chunks = str_split($jsonStr, 35);

                foreach ($chunks as $chunk) {
                    echo "data: " . json_encode(['choices' => [['delta' => ['content' => $chunk]]]]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                    usleep(40000); // 40ms typing effect
                }
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Generate a new form JSON schema from a natural language prompt with Redis Caching.
     */
    public function generateFormSchema(string $prompt, ?int $formId = null): array
    {
        $cacheKey = 'ai_form_schema_' . md5(strtolower(trim($prompt)));

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($prompt, $formId) {
            return $this->executeGenerateFormSchema($prompt, $formId);
        });
    }

    protected function executeGenerateFormSchema(string $prompt, ?int $formId = null): array
    {
        $startTime = microtime(true);

        $log = AiGenerationLog::create([
            'form_id' => $formId,
            'prompt' => $prompt,
            'model' => $this->model,
            'status' => 'processing',
        ]);

        // Tier 1: Try Python FastAPI Microservice over REST (if enabled)
        if (!empty($this->fastapiUrl) && !in_array(strtolower($this->fastapiUrl), ['disabled', 'false', 'none', 'off'])) {
            try {
                $fastapiRes = Http::timeout(45)->post($this->fastapiUrl . '/generate-form', [
                    'prompt' => $prompt,
                    'form_id' => $formId,
                ]);

            if ($fastapiRes->successful()) {
                $fastapiData = $fastapiRes->json();
                if (isset($fastapiData['schema']['sections'])) {
                    $latency = microtime(true) - $startTime;
                    $tokens = $fastapiData['tokens'] ?? [];

                    $log->update([
                        'model' => $fastapiData['model_tag'] ?? ('fastapi:instructor:' . $this->model),
                        'prompt_tokens' => $tokens['prompt_tokens'] ?? 150,
                        'completion_tokens' => $tokens['completion_tokens'] ?? 300,
                        'total_tokens' => $tokens['total_tokens'] ?? 450,
                        'latency_seconds' => round($latency, 3),
                        'status' => 'completed',
                    ]);

                    return $fastapiData['schema'];
                }
            }
            } catch (\Exception $e) {
                Log::info("FastAPI AI Layer unavailable, falling back to native Laravel HTTP service: " . $e->getMessage());
            }
        }

        // Tier 2: Native Laravel HTTP Call (Mistral / OpenAI)
        if (empty($this->apiKey) || in_array($this->apiKey, ['your_openai_api_key_here', 'your_mistral_api_key_here'])) {
            $latency = microtime(true) - $startTime;
            $schema = $this->generateMockSchema($prompt);

            $log->update([
                'model' => 'laravel:mock:default',
                'prompt_tokens' => 150,
                'completion_tokens' => 350,
                'total_tokens' => 500,
                'latency_seconds' => round($latency, 3),
                'status' => 'completed',
            ]);

            return $schema;
        }

        try {
            $systemPrompt = $this->getSystemPrompt();
            $userPrompt = "Create a complete, detailed form JSON schema for: \"{$prompt}\"";

            if (class_exists(\EchoLabs\Prism\Prism::class)) {
                $prismResponse = \EchoLabs\Prism\Prism::text()
                    ->using('openai', $this->model)
                    ->withSystemPrompt($systemPrompt)
                    ->withPrompt($userPrompt)
                    ->generate();
                $rawContent = $prismResponse->text ?? '{}';
                $usage = [
                    'prompt_tokens' => $prismResponse->usage->promptTokens ?? 150,
                    'completion_tokens' => $prismResponse->usage->completionTokens ?? 300,
                    'total_tokens' => ($prismResponse->usage->promptTokens ?? 150) + ($prismResponse->usage->completionTokens ?? 300),
                ];
            } else {
                $response = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->post($this->baseUrl, [
                        'model' => $this->model,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.7,
                    ]);

                if (!$response->successful()) {
                    throw new \Exception("LLM Provider returned error status: " . $response->status());
                }

                $data = $response->json();
                $rawContent = $data['choices'][0]['message']['content'] ?? '{}';
                $usage = $data['usage'] ?? [];
            }

            $latency = microtime(true) - $startTime;
            $schema = json_decode($rawContent, true);
            if (!is_array($schema) || !isset($schema['sections'])) {
                $schema = $this->repairSchema($rawContent);
            }

            $log->update([
                'model' => 'prism:laravel:' . $this->model,
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
                'latency_seconds' => round($latency, 3),
                'status' => 'completed',
            ]);

            return $schema;
        } catch (\Exception $e) {
            Log::error("AI Form Generation failed: " . $e->getMessage());

            $log->update([
                'model' => 'laravel:mock:fallback',
                'status' => 'completed',
                'error_message' => $e->getMessage(),
            ]);

            return $this->generateMockSchema($prompt);
        }
    }

    /**
     * Modify an existing form schema using an AI instruction prompt.
     * NEVER replaces existing schema with a static template.
     */
    public function editFormSchema(array $existingSchema, string $instruction, ?int $formId = null): array
    {
        $startTime = microtime(true);

        $log = AiGenerationLog::create([
            'form_id' => $formId,
            'prompt' => "Edit Schema: " . $instruction,
            'model' => $this->model,
            'status' => 'processing',
        ]);

        if (empty($this->apiKey) || in_array($this->apiKey, ['your_openai_api_key_here', 'your_mistral_api_key_here'])) {
            $latency = microtime(true) - $startTime;
            $schema = $this->modifyMockSchema($existingSchema, $instruction);

            $log->update([
                'prompt_tokens' => 120,
                'completion_tokens' => 200,
                'total_tokens' => 320,
                'latency_seconds' => round($latency, 3),
                'status' => 'completed',
            ]);

            return $schema;
        }

        try {
            $systemPrompt = config('ai.prompts.system_editor');
            $userPrompt = "EDIT INSTRUCTION: \"{$instruction}\"\n\nCURRENT FORM SCHEMA:\n" . json_encode($existingSchema, JSON_PRETTY_PRINT);

            if (class_exists(\EchoLabs\Prism\Prism::class)) {
                $prismResponse = \EchoLabs\Prism\Prism::text()
                    ->using('openai', $this->model)
                    ->withSystemPrompt($systemPrompt)
                    ->withPrompt($userPrompt)
                    ->generate();
                $rawContent = $prismResponse->text ?? '{}';
                $usage = [
                    'prompt_tokens' => $prismResponse->usage->promptTokens ?? 120,
                    'completion_tokens' => $prismResponse->usage->completionTokens ?? 250,
                    'total_tokens' => ($prismResponse->usage->promptTokens ?? 120) + ($prismResponse->usage->completionTokens ?? 250),
                ];
            } else {
                $response = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->post($this->baseUrl, [
                        'model' => $this->model,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.7,
                    ]);

                if (!$response->successful()) {
                    throw new \Exception("LLM Provider returned error status: " . $response->status());
                }

                $data = $response->json();
                $rawContent = $data['choices'][0]['message']['content'] ?? '{}';
                $usage = $data['usage'] ?? [];
            }

            $latency = microtime(true) - $startTime;
            $schema = json_decode($rawContent, true);
            if (!is_array($schema) || !isset($schema['sections'])) {
                $schema = $this->modifyMockSchema($existingSchema, $instruction);
            }

                // Preserve title & description
                if (empty($schema['title']) || str_starts_with($schema['title'], 'Modify The Following')) {
                    $schema['title'] = $existingSchema['title'] ?? 'Untitled Form';
                }
                if (empty($schema['description'])) {
                    $schema['description'] = $existingSchema['description'] ?? '';
                }

                $log->update([
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                    'latency_seconds' => round($latency, 3),
                    'status' => 'completed',
                ]);

                return $schema;
        } catch (\Exception $e) {
            Log::error("AI Form Schema Edit failed: " . $e->getMessage());

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // ALWAYS modify existing schema on fallback, NEVER replace with mock template
            return $this->modifyMockSchema($existingSchema, $instruction);
        }
    }

    /**
     * System Prompt with strict schema output instructions.
     */
    protected function getSystemPrompt(): string
    {
        return config('ai.prompts.system_generator');
    }

    /**
     * Validate if a schema array meets strict structural schema requirements.
     */
    public function validateSchema(?array $schema): bool
    {
        if (!is_array($schema)) {
            return false;
        }

        if (empty($schema['title']) || !is_string($schema['title'])) {
            return false;
        }

        if (!isset($schema['sections']) || !is_array($schema['sections']) || empty($schema['sections'])) {
            return false;
        }

        foreach ($schema['sections'] as $sec) {
            if (!is_array($sec) || empty($sec['title']) || !isset($sec['fields']) || !is_array($sec['fields'])) {
                return false;
            }
            foreach ($sec['fields'] as $field) {
                if (!is_array($field) || empty($field['type'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Repair truncated JSON strings (e.g. cut off midway by streaming or LLM token max limits).
     */
    public function repairTruncatedJson(string $json): string
    {
        $json = trim($json);
        if (empty($json)) return '{}';

        // Strip trailing commas, colons, or quotes that are unclosed
        $json = preg_replace('/,\s*$/', '', $json);

        // Count balance of open vs close brackets/braces
        $len = strlen($json);
        $inString = false;
        $escaped = false;
        $stack = [];

        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if (!$inString) {
                if ($char === '{' || $char === '[') {
                    $stack[] = $char;
                } elseif ($char === '}' || $char === ']') {
                    array_pop($stack);
                }
            }
        }

        // If ended inside a string literal, close the quote
        if ($inString) {
            $json .= '"';
        }

        // Strip trailing dangling colon or comma inside structures
        $json = preg_replace('/:\s*$/', ': null', $json);
        $json = preg_replace('/,\s*$/', '', $json);

        // Close all unclosed brackets/braces in reverse order
        while (!empty($stack)) {
            $openChar = array_pop($stack);
            $json .= ($openChar === '{') ? '}' : ']';
        }

        return $json;
    }

    /**
     * Defensive Schema Repair for broken, partial, or malformed JSON responses.
     */
    public function repairSchema(string $rawContent): array
    {
        if (empty(trim($rawContent))) {
            return $this->getFallbackValidSchema();
        }

        // 1. Remove markdown syntax code fence wrappers (e.g. ```json ... ```)
        $clean = preg_replace('/^```json\s*/i', '', trim($rawContent));
        $clean = preg_replace('/```$/', '', $clean);
        
        // 2. Locate boundaries between first '{' and last '}' if present
        $firstBrace = strpos($clean, '{');
        $lastBrace = strrpos($clean, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace >= $firstBrace) {
            $clean = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        } elseif ($firstBrace !== false) {
            $clean = substr($clean, $firstBrace);
        }

        // 3. Sanitize unescaped newlines/tabs inside quotes
        $clean = preg_replace_callback('/("(?:[^"\\\\]|\\\\.)*")/', function ($matches) {
            return str_replace(["\r\n", "\n", "\r", "\t"], ["\\n", "\\n", "\\n", "\\t"], $matches[1]);
        }, $clean);

        // 4. Remove trailing commas before closing braces/brackets
        $clean = preg_replace('/,\s*([\}\]])/', '$1', $clean);

        $decoded = json_decode($clean, true);

        // 5. If JSON decoding failed, attempt truncated JSON repair
        if (!is_array($decoded)) {
            $repairedJson = $this->repairTruncatedJson($clean);
            $decoded = json_decode($repairedJson, true);
        }

        // 6. Normalize structure and ensure 100% schema validity
        if (is_array($decoded)) {
            $this->normalizeAndValidateSchema($decoded);
            if ($this->validateSchema($decoded)) {
                return $decoded;
            }
        }

        return $this->getFallbackValidSchema();
    }

    /**
     * Ensure schema attributes, section IDs, field keys & labels are normalized and 100% compliant.
     */
    public function normalizeAndValidateSchema(array &$schema): void
    {
        $schema['title'] = !empty($schema['title']) && is_string($schema['title']) ? $schema['title'] : 'Generated AI Form';
        $schema['description'] = isset($schema['description']) && is_string($schema['description']) ? $schema['description'] : '';

        if (!isset($schema['sections']) || !is_array($schema['sections']) || empty($schema['sections'])) {
            $schema['sections'] = [
                [
                    'id' => 'sec_' . Str::random(5),
                    'title' => 'General Information',
                    'fields' => [
                        [
                            'id' => 'fld_' . Str::random(5),
                            'key' => 'full_name',
                            'type' => 'text',
                            'label' => 'Full Name',
                            'required' => true,
                            'col_span' => 12,
                        ]
                    ]
                ]
            ];
        }

        $usedSectionIds = [];
        $usedFieldIds = [];
        $usedKeys = [];

        foreach ($schema['sections'] as &$sec) {
            if (!is_array($sec)) {
                $sec = ['title' => 'Section', 'fields' => []];
            }

            if (empty($sec['id']) || in_array($sec['id'], $usedSectionIds)) {
                $sec['id'] = 'sec_' . Str::random(5);
            }
            $usedSectionIds[] = $sec['id'];

            if (empty($sec['title'])) {
                $sec['title'] = 'Section';
            }

            if (!isset($sec['fields']) || !is_array($sec['fields'])) {
                $sec['fields'] = [];
            }

            foreach ($sec['fields'] as &$field) {
                if (!is_array($field)) {
                    $field = ['type' => 'text'];
                }

                if (empty($field['id']) || in_array($field['id'], $usedFieldIds)) {
                    $field['id'] = 'fld_' . Str::random(6);
                }
                $usedFieldIds[] = $field['id'];

                if (empty($field['type'])) {
                    $field['type'] = 'text';
                }

                if (empty($field['label'])) {
                    $field['label'] = Str::headline($field['type']);
                }

                if (empty($field['key'])) {
                    $baseKey = Str::snake($field['label']);
                    $field['key'] = empty($baseKey) ? 'field_' . Str::random(4) : $baseKey;
                } else {
                    $field['key'] = Str::snake($field['key']);
                }

                if (in_array($field['key'], $usedKeys)) {
                    $origKey = $field['key'];
                    $c = 1;
                    while (in_array($origKey . '_' . $c, $usedKeys)) {
                        $c++;
                    }
                    $field['key'] = $origKey . '_' . $c;
                }
                $usedKeys[] = $field['key'];

                if (!isset($field['col_span'])) {
                    $field['col_span'] = 12;
                }
            }
        }
    }

    /**
     * Fallback valid schema to ensure system NEVER persists a broken schema.
     */
    public function getFallbackValidSchema(): array
    {
        return [
            'title' => 'Generated Form',
            'description' => 'Auto-repaired form schema',
            'sections' => [
                [
                    'id' => 'sec_' . Str::random(5),
                    'title' => 'General Information',
                    'fields' => [
                        [
                            'id' => 'fld_' . Str::random(5),
                            'key' => 'full_name',
                            'type' => 'text',
                            'label' => 'Full Name',
                            'placeholder' => 'John Doe',
                            'required' => true,
                            'col_span' => 12,
                            'validation' => ['min' => 2, 'max' => 100],
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Smart Mock Generator for brand new forms when API Key is absent.
     */
    protected function generateMockSchema(string $prompt): array
    {
        $jsonPath = resource_path('json/templates/default_sample_form.json');
        if (file_exists($jsonPath)) {
            $baseSchema = json_decode(file_get_contents($jsonPath), true);
            if (is_array($baseSchema)) {
                $title = Str::headline(Str::limit($prompt, 40));
                $baseSchema['title'] = $title;
                $baseSchema['description'] = "AI-generated form based on prompt: \"{$prompt}\"";
                return $baseSchema;
            }
        }

        $title = Str::headline(Str::limit($prompt, 40));
        return [
            'title' => $title,
            'description' => "AI-generated form based on prompt: \"{$prompt}\"",
            'sections' => []
        ];
    }

    /**
     * Smart Mock Schema Edit: Retains 100% of existing form sections and appends requested fields/sections.
     */
    protected function modifyMockSchema(array $existingSchema, string $instruction): array
    {
        $schema = $existingSchema;
        $lower = strtolower($instruction);
        $newFields = [];

        // 1. Detect if instruction asks for a NEW section
        $newSectionTitle = null;
        if (preg_match('/(?:add|create|append)\s+(?:a|an\s+)?(.+?)\s+section/i', $instruction, $match)) {
            $candidateTitle = trim($match[1]);
            if (!empty($candidateTitle) && !in_array(strtolower($candidateTitle), ['new', 'the', 'another'])) {
                $newSectionTitle = Str::headline($candidateTitle);
            }
        }

        // 2. Emergency / Contact fields (Name, Phone, Relation)
        if (str_contains($lower, 'emergency') || str_contains($lower, 'contact')) {
            $newFields[] = [
                'id' => 'fld_' . Str::random(5),
                'key' => 'emergency_contact_name',
                'type' => 'text',
                'label' => 'Emergency Contact Name',
                'placeholder' => 'Jane Doe',
                'required' => true,
                'col_span' => 6,
            ];
            $newFields[] = [
                'id' => 'fld_' . Str::random(5),
                'key' => 'emergency_contact_phone',
                'type' => 'phone',
                'label' => 'Emergency Phone Number',
                'placeholder' => '+1 (555) 019-2831',
                'required' => true,
                'col_span' => 6,
            ];
            if (str_contains($lower, 'relation') || str_contains($lower, 'relationship')) {
                $newFields[] = [
                    'id' => 'fld_' . Str::random(5),
                    'key' => 'emergency_contact_relation',
                    'type' => 'dropdown',
                    'label' => 'Relationship',
                    'placeholder' => 'Select relationship',
                    'options' => ['Parent', 'Spouse', 'Sibling', 'Child', 'Friend', 'Relative', 'Other'],
                    'required' => true,
                    'col_span' => 6,
                ];
            }
        }

        // 3. Standalone Relation / Relationship requested
        if (str_contains($lower, 'relation') && !str_contains($lower, 'emergency') && !str_contains($lower, 'contact')) {
            $newFields[] = [
                'id' => 'fld_' . Str::random(5),
                'key' => 'relation',
                'type' => 'dropdown',
                'label' => 'Relationship',
                'placeholder' => 'Select relationship',
                'options' => ['Parent', 'Spouse', 'Sibling', 'Child', 'Friend', 'Relative', 'Other'],
                'required' => true,
                'col_span' => 6,
            ];
        }

        // 4. Date requested
        if (str_contains($lower, 'date') && !str_contains($lower, 'update')) {
            $newFields[] = [
                'id' => 'fld_' . Str::random(5),
                'key' => 'selected_date',
                'type' => 'date',
                'label' => 'Select Date',
                'placeholder' => 'YYYY-MM-DD',
                'required' => true,
                'col_span' => 6,
            ];
        }

        // 5. Rating / Feedback requested
        if (str_contains($lower, 'rating') || str_contains($lower, 'feedback')) {
            $newFields[] = [
                'id' => 'fld_' . Str::random(5),
                'key' => 'overall_rating',
                'type' => 'rating',
                'label' => 'Overall Satisfaction Rating',
                'required' => true,
                'col_span' => 12,
            ];
        }

        // 6. Generic Fallback if no specific fields matched
        if (empty($newFields)) {
            $label = Str::headline(Str::limit($instruction, 30));
            $key = Str::snake(Str::limit($instruction, 20));
            $newFields[] = [
                'id' => 'fld_' . Str::random(5),
                'key' => 'field_' . $key,
                'type' => 'text',
                'label' => $label,
                'placeholder' => 'Enter ' . strtolower($label),
                'required' => false,
                'col_span' => 6,
            ];
        }

        // Preserve form title and description from existing schema
        $schema['title'] = $existingSchema['title'] ?? 'Untitled Form';
        $schema['description'] = $existingSchema['description'] ?? '';

        if (empty($schema['sections'])) {
            $schema['sections'] = [
                [
                    'id' => 'sec_' . Str::random(5),
                    'title' => $newSectionTitle ?? 'General Section',
                    'fields' => $newFields,
                ]
            ];
        } elseif ($newSectionTitle) {
            // Append as a BRAND NEW section as requested in user instruction
            $schema['sections'][] = [
                'id' => 'sec_' . Str::random(5),
                'title' => $newSectionTitle,
                'fields' => $newFields,
            ];
        } else {
            // Append new fields to the existing last section
            $lastSecIndex = count($schema['sections']) - 1;
            foreach ($newFields as $nf) {
                $schema['sections'][$lastSecIndex]['fields'][] = $nf;
            }
        }

        return $schema;
    }
}
