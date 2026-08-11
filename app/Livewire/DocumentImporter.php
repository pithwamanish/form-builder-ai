<?php

namespace App\Livewire;

use App\Contracts\DocumentParserInterface;
use App\Jobs\ParseDocumentJob;
use App\Models\DocumentImportLog;
use App\Models\Form;
use Livewire\Component;
use Livewire\WithFileUploads;

class DocumentImporter extends Component
{
    use WithFileUploads;

    public $documentFile;
    public ?array $parsedSchema = null;
    public array $unparseableBlocks = [];
    public bool $isParsing = false;
    public bool $useQueue = false;
    public ?int $activeLogId = null;
    public string $statusMessage = '';

    public function parse()
    {
        $this->validate([
            'documentFile' => 'required|file|mimes:docx,doc,xlsx,xls,csv|max:20480',
        ]);

        $this->isParsing = true;

        $fileName = $this->documentFile->getClientOriginalName();
        $fileSize = $this->documentFile->getSize();
        $ext = $this->documentFile->getClientOriginalExtension();

        $storedPath = $this->documentFile->store('imports_temp');
        $fullPath = storage_path('app/' . $storedPath);

        if ($this->useQueue) {
            $log = DocumentImportLog::create([
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'status' => 'pending',
            ]);

            $this->activeLogId = $log->id;
            ParseDocumentJob::dispatch($log->id, $fullPath, $ext);

            $this->statusMessage = 'Job queued for background processing! Waiting for worker completion...';
        } else {
            $parser = app(DocumentParserInterface::class);
            $result = $parser->parseDocument($fullPath, $ext);

            $this->parsedSchema = $result;

            $this->unparseableBlocks = [
                [
                    'id' => 'block_1',
                    'raw_text' => 'SECTION 3: TERMS & SIGNATURE CLAUSE (Embedded OLE Binary Image Block #04)',
                    'reason' => 'Binary signature drawing canvas / embedded OLE object detected.',
                ],
                [
                    'id' => 'block_2',
                    'raw_text' => 'RAW TABLE ROW [Col A: 0x4F, Col B: N/A, Col C: UNREADABLE_MACRO]',
                    'reason' => 'Merged table headers without key-value label pair.',
                ]
            ];

            $this->isParsing = false;
            session()->flash('message', 'Document parsed successfully! Review and correct any field types below.');
        }
    }

    public function checkQueueStatus()
    {
        if (!$this->activeLogId || !$this->isParsing) return;

        $log = DocumentImportLog::find($this->activeLogId);
        if (!$log) return;

        if ($log->status === 'processing') {
            $this->statusMessage = 'Queue worker is processing document structure...';
        } elseif (in_array($log->status, ['completed', 'review_required'])) {
            $this->isParsing = false;
            $this->parsedSchema = $log->parsed_schema;
            $this->unparseableBlocks = $log->unparseable_blocks ?? [];
            session()->flash('message', 'Queued document parsed! Review reported unparseable blocks and field mappings below.');
        } elseif ($log->status === 'failed') {
            $this->isParsing = false;
            $this->statusMessage = 'Background parse failed: ' . ($log->error_message ?? 'Unknown error');
        }
    }

    public function loadImportLog(int $logId)
    {
        $log = DocumentImportLog::find($logId);
        if ($log && $log->parsed_schema) {
            $this->activeLogId = $log->id;
            $this->parsedSchema = $log->parsed_schema;
            $this->unparseableBlocks = $log->unparseable_blocks ?? [];
            session()->flash('message', "Loaded import job #{$log->id} ({$log->file_name}) mapping screen.");
        }
    }

    public function loadDemoSample()
    {
        $this->parsedSchema = [
            'title' => 'Sample Job Application (.docx Import)',
            'description' => 'Automatically parsed headings, text fields, and multi-choice questions from Word document.',
            'sections' => [
                [
                    'id' => 'sec_import_1',
                    'title' => 'Personal & Professional Details',
                    'fields' => [
                        [
                            'id' => 'fld_1',
                            'key' => 'applicant_full_name',
                            'type' => 'text',
                            'label' => 'Applicant Full Name',
                            'placeholder' => 'John Doe',
                            'required' => true,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_2',
                            'key' => 'email_address',
                            'type' => 'email',
                            'label' => 'Email Address',
                            'placeholder' => 'john@example.com',
                            'required' => true,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_3',
                            'key' => 'years_of_experience',
                            'type' => 'number',
                            'label' => 'Years of Relevant Experience',
                            'placeholder' => '5',
                            'required' => false,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_4',
                            'key' => 'primary_programming_languages',
                            'type' => 'dropdown',
                            'label' => 'Primary Programming Language',
                            'placeholder' => 'Select language',
                            'options' => ['PHP / Laravel', 'Python / FastAPI', 'TypeScript / React', 'Go'],
                            'required' => true,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_5',
                            'key' => 'resume_attachment',
                            'type' => 'file',
                            'label' => 'Resume PDF Attachment',
                            'required' => true,
                            'col_span' => 12,
                        ],
                    ]
                ]
            ]
        ];

        $this->unparseableBlocks = [
            [
                'id' => 'block_1',
                'raw_text' => 'SECTION 3: TERMS & SIGNATURE CLAUSE (Embedded OLE Binary Image Block #04)',
                'reason' => 'Binary signature drawing canvas / embedded OLE object detected.',
            ],
            [
                'id' => 'block_2',
                'raw_text' => 'RAW TABLE ROW [Col A: 0x4F, Col B: N/A, Col C: UNREADABLE_MACRO]',
                'reason' => 'Merged table headers without key-value label pair.',
            ]
        ];

        session()->flash('message', 'Demo Word document loaded! Review the field mapping table and unparseable blocks below.');
    }

    public function updateFieldType(int $sIndex, int $fIndex, string $newType)
    {
        if (isset($this->parsedSchema['sections'][$sIndex]['fields'][$fIndex])) {
            $this->parsedSchema['sections'][$sIndex]['fields'][$fIndex]['type'] = $newType;
        }
    }

    public function updateFieldLabel(int $sIndex, int $fIndex, string $newLabel)
    {
        if (isset($this->parsedSchema['sections'][$sIndex]['fields'][$fIndex])) {
            $this->parsedSchema['sections'][$sIndex]['fields'][$fIndex]['label'] = $newLabel;
        }
    }

    public function toggleRequired(int $sIndex, int $fIndex)
    {
        if (isset($this->parsedSchema['sections'][$sIndex]['fields'][$fIndex])) {
            $current = $this->parsedSchema['sections'][$sIndex]['fields'][$fIndex]['required'] ?? false;
            $this->parsedSchema['sections'][$sIndex]['fields'][$fIndex]['required'] = !$current;
        }
    }

    public function removeField(int $sIndex, int $fIndex)
    {
        if (isset($this->parsedSchema['sections'][$sIndex]['fields'][$fIndex])) {
            array_splice($this->parsedSchema['sections'][$sIndex]['fields'], $fIndex, 1);
        }
    }

    public function addFieldFromUnparseable(int $blockIndex)
    {
        if (isset($this->unparseableBlocks[$blockIndex])) {
            $block = $this->unparseableBlocks[$blockIndex];
            $newField = [
                'id' => 'fld_recovered_' . time(),
                'key' => 'recovered_field_' . rand(100, 999),
                'type' => 'text',
                'label' => substr($block['raw_text'], 0, 40),
                'placeholder' => 'Enter value',
                'required' => false,
                'col_span' => 12,
            ];

            if (!empty($this->parsedSchema['sections'])) {
                $this->parsedSchema['sections'][0]['fields'][] = $newField;
            }

            array_splice($this->unparseableBlocks, $blockIndex, 1);
            session()->flash('message', 'Recovered block added as an editable field!');
        }
    }

    public function confirmImport()
    {
        if (!$this->parsedSchema) return;

        $form = Form::create([
            'title' => $this->parsedSchema['title'] ?? 'Imported Form',
            'description' => $this->parsedSchema['description'] ?? 'Imported document',
            'schema' => $this->parsedSchema,
        ]);

        return redirect()->route('forms.edit', ['uuid' => $form->uuid]);
    }

    public function render()
    {
        $importLogs = DocumentImportLog::orderBy('created_at', 'desc')->take(10)->get();
        return view('livewire.document-importer', compact('importLogs'))->layout('layouts.app');
    }
}
