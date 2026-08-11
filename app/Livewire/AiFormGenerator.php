<?php

namespace App\Livewire;

use App\Contracts\AiServiceInterface;
use App\Jobs\GenerateFormSchemaJob;
use App\Models\AiGenerationLog;
use App\Models\Form;
use Livewire\Component;

class AiFormGenerator extends Component
{
    public string $prompt = '';
    public bool $isGenerating = false;
    public bool $useQueue = false;
    public string $statusMessage = '';
    public ?int $logId = null;
    public ?string $targetFormUuid = null;
    public ?array $logDetails = null;

    public function generate()
    {
        $this->validate([
            'prompt' => 'required|string|min:5',
        ]);

        $this->isGenerating = true;

        if ($this->useQueue) {
            // Queued Job Mode (Asynchronous background processing)
            $this->statusMessage = 'Dispatching job to Laravel Queue...';

            $form = Form::create([
                'title' => 'Generating Form...',
                'description' => 'AI generation in progress via queued worker',
                'schema' => ['title' => 'Pending AI Generation', 'sections' => []],
            ]);

            $log = AiGenerationLog::create([
                'form_id' => $form->id,
                'prompt' => $this->prompt,
                'model' => config('ai.mistral.model', env('MISTRAL_MODEL', 'mistral-small-latest')),
                'status' => 'pending',
            ]);

            $this->logId = $log->id;
            $this->targetFormUuid = $form->uuid;

            GenerateFormSchemaJob::dispatch($log->id, $this->prompt, $form->uuid);

            $this->statusMessage = 'Job queued! Waiting for queue worker completion...';
        } else {
            // Immediate Sync Mode
            $this->statusMessage = 'Generating form schema with AI service...';

            $aiService = app(AiServiceInterface::class);
            $schema = $aiService->generateFormSchema($this->prompt);

            $form = Form::create([
                'title' => $schema['title'] ?? 'AI Form',
                'description' => $schema['description'] ?? 'Generated from prompt',
                'schema' => $schema,
            ]);

            $this->isGenerating = false;
            session()->flash('message', 'Form generated successfully!');
            return redirect()->route('forms.edit', ['uuid' => $form->uuid]);
        }
    }

    public function checkQueueStatus()
    {
        if (!$this->logId || !$this->isGenerating) {
            return;
        }

        $log = AiGenerationLog::find($this->logId);

        if (!$log) {
            return;
        }

        $this->logDetails = [
            'status' => $log->status,
            'model' => $log->model,
            'prompt_tokens' => $log->prompt_tokens,
            'completion_tokens' => $log->completion_tokens,
            'total_tokens' => $log->total_tokens,
            'latency_seconds' => $log->latency_seconds,
            'error_message' => $log->error_message,
        ];

        if ($log->status === 'processing') {
            $this->statusMessage = 'Queue worker picked up job! Calling LLM model (' . $log->model . ')...';
        } elseif ($log->status === 'completed') {
            $this->isGenerating = false;
            session()->flash('message', "Queued AI Job completed in {$log->latency_seconds}s! Model: {$log->model}, Tokens: {$log->total_tokens}");
            return redirect()->route('forms.edit', ['uuid' => $this->targetFormUuid]);
        } elseif ($log->status === 'failed') {
            $this->isGenerating = false;
            $this->statusMessage = 'AI Job Failed: ' . ($log->error_message ?? 'Unknown error');
        }
    }

    public function saveStreamedFormSchema(array|string $schema): string
    {
        $parsedSchema = is_array($schema) ? $schema : json_decode($schema, true);
        if (!is_array($parsedSchema) || !isset($parsedSchema['sections'])) {
            $aiService = app(AiServiceInterface::class);
            $parsedSchema = $aiService->repairSchema(is_string($schema) ? $schema : json_encode($schema));
        }

        $form = Form::create([
            'title' => $parsedSchema['title'] ?? 'Streamed AI Form',
            'description' => $parsedSchema['description'] ?? 'Generated via Real-Time SSE Token Stream',
            'schema' => $parsedSchema,
            'slug' => \Illuminate\Support\Str::slug($parsedSchema['title'] ?? 'ai-form') . '-' . \Illuminate\Support\Str::random(6),
        ]);

        session()->flash('message', 'Form generated via SSE stream successfully!');
        return $form->uuid;
    }

    public function render()
    {
        return view('livewire.ai-form-generator')->layout('layouts.app');
    }
}
