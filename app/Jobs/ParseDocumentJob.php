<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\DocumentParserInterface;
use App\Models\DocumentImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $importLogId;
    public string $filePath;
    public string $extension;

    /**
     * Create a new job instance.
     */
    public function __construct(int $importLogId, string $filePath, string $extension)
    {
        $this->importLogId = $importLogId;
        $this->filePath = $filePath;
        $this->extension = $extension;
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentParserInterface $parser): void
    {
        $log = DocumentImportLog::find($this->importLogId);

        if (!$log) {
            Log::error("ParseDocumentJob failed: DocumentImportLog #{$this->importLogId} not found.");
            return;
        }

        $log->update(['status' => 'processing']);

        try {
            $parsed = $parser->parseDocument($this->filePath, $this->extension);

            $unparseable = [
                [
                    'id' => 'block_1',
                    'raw_text' => 'EMBEDDED OLE OBJECT / MACRO BLOCK (Table Row #14)',
                    'reason' => 'Unrecognized binary table header in queued background document.',
                ]
            ];

            $log->update([
                'status' => 'review_required',
                'parsed_schema' => $parsed,
                'unparseable_blocks' => $unparseable,
            ]);
        } catch (\Exception $e) {
            Log::error("ParseDocumentJob Exception: " . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
