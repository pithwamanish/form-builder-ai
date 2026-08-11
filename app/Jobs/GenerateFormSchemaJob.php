<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\AiServiceInterface;
use App\Models\AiGenerationLog;
use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFormSchemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $logId;
    public string $prompt;
    public string $formUuid;

    /**
     * Create a new job instance.
     */
    public function __construct(int $logId, string $prompt, string $formUuid)
    {
        $this->logId = $logId;
        $this->prompt = $prompt;
        $this->formUuid = $formUuid;
    }

    /**
     * Execute the job.
     */
    public function handle(AiServiceInterface $aiService): void
    {
        $log = AiGenerationLog::find($this->logId);
        $form = Form::where('uuid', $this->formUuid)->first();

        if (!$log || !$form) {
            Log::error("GenerateFormSchemaJob failed: Log #{$this->logId} or Form {$this->formUuid} not found.");
            return;
        }

        $log->update(['status' => 'processing']);

        try {
            $schema = $aiService->generateFormSchema($this->prompt, $form->id);
            
            $form->update([
                'title' => $schema['title'] ?? $form->title,
                'description' => $schema['description'] ?? $form->description,
                'schema' => $schema,
            ]);

            $log->update(['status' => 'completed']);
        } catch (\Exception $e) {
            Log::error("GenerateFormSchemaJob Exception: " . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
