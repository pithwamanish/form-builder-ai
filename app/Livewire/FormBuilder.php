<?php

namespace App\Livewire;

use App\Contracts\AiServiceInterface;
use App\Models\Form;
use App\Models\FormTemplate;
use App\Services\AiFormService;
use Illuminate\Support\Str;
use Livewire\Component;

class FormBuilder extends Component
{
    public ?Form $form = null;
    public string $title = 'Untitled Form';
    public string $description = '';
    public array $schema = [];
    public string $rawJson = '';
    public string $activeTab = 'visual'; // 'visual', 'json', 'ai', 'import', 'templates'
    public string $jsonError = '';
    public string $selectedSectionId = '';
    public ?string $successMessage = null;
    public ?string $activeFieldConfigId = null;

    public function toggleFieldConfig(string $fieldId)
    {
        $this->activeFieldConfigId = ($this->activeFieldConfigId === $fieldId) ? null : $fieldId;
    }

    // AI Edit prompt
    public string $aiEditPrompt = '';
    public bool $isAiProcessing = false;

    protected $listeners = ['formSchemaUpdated' => 'loadSchema'];

    public function mount(?string $uuid = null)
    {
        if ($uuid) {
            $this->form = Form::where('uuid', $uuid)->firstOrFail();
            $this->title = $this->form->title;
            $this->description = $this->form->description ?? '';
            $this->schema = $this->form->schema;
        } else {
            $this->form = null;
            $this->schema = [
                'title' => 'Untitled Form',
                'description' => '',
                'settings' => [
                    'display_mode' => 'single_page',
                    'layout_mode' => 'freeform'
                ],
                'sections' => [
                    [
                        'id' => 'sec_' . Str::random(5),
                        'title' => 'Main Section',
                        'fields' => [
                            [
                                'id' => 'fld_' . Str::random(6),
                                'key' => 'field_1',
                                'type' => 'text',
                                'label' => 'Field 1',
                                'placeholder' => 'Enter value...',
                                'required' => false,
                                'col_span' => 12,
                                'row_span' => 1,
                                'valign' => 'center'
                            ]
                        ]
                    ]
                ]
            ];
            $this->title = 'Untitled Form';
            $this->description = '';
        }

        if (!isset($this->schema['settings']['display_mode'])) {
            $this->schema['settings']['display_mode'] = 'wizard'; // 'wizard' or 'single_page'
        }

        $this->selectedSectionId = $this->schema['sections'][0]['id'] ?? '';
        $this->normalizeSchema();
        $this->syncRawJson();
    }

    public function normalizeSchema()
    {
        if (!isset($this->schema['sections']) || !is_array($this->schema['sections'])) {
            return;
        }

        $usedSectionIds = [];
        $usedFieldIds = [];
        $usedKeys = [];

        foreach ($this->schema['sections'] as &$sec) {
            // 1. Auto-generate section ID if omitted or duplicate
            if (empty($sec['id']) || in_array($sec['id'], $usedSectionIds)) {
                $sec['id'] = 'sec_' . Str::random(5);
            }
            $usedSectionIds[] = $sec['id'];

            if (!isset($sec['title'])) {
                $sec['title'] = 'Section';
            }

            if (isset($sec['fields']) && is_array($sec['fields'])) {
                foreach ($sec['fields'] as &$field) {
                    // 2. Auto-generate field ID if omitted or duplicate
                    if (empty($field['id']) || in_array($field['id'], $usedFieldIds)) {
                        $field['id'] = 'fld_' . Str::random(6);
                    }
                    $usedFieldIds[] = $field['id'];

                    // 2b. Auto-generate field label if omitted or null
                    if (!isset($field['label']) || $field['label'] === null || trim($field['label']) === '') {
                        $field['label'] = Str::headline($field['type'] ?? 'field');
                    }

                    // 3. Auto-generate storage key from label if omitted
                    if (empty($field['key'])) {
                        $baseKey = Str::snake($field['label'] ?? ($field['type'] ?? 'field'));
                        $field['key'] = empty($baseKey) ? 'field_' . Str::random(4) : $baseKey;
                    } else {
                        $field['key'] = Str::snake($field['key']);
                    }

                    // 4. Guarantee 100% unique field keys across entire schema
                    if (in_array($field['key'], $usedKeys)) {
                        $originalKey = $field['key'];
                        $counter = 1;
                        while (in_array($originalKey . '_' . $counter, $usedKeys)) {
                            $counter++;
                        }
                        $field['key'] = $originalKey . '_' . $counter;
                    }
                    $usedKeys[] = $field['key'];

                    // 5. Grid col_span normalization
                    if (!isset($field['col_span']) || $field['col_span'] === null || $field['col_span'] === '') {
                        $field['col_span'] = 12;
                    } else {
                        $field['col_span'] = (int) $field['col_span'];
                    }

                    if (!isset($field['align']) || empty($field['align'])) {
                        $field['align'] = 'left';
                    }

                    if (!isset($field['rows']) || empty($field['rows'])) {
                        $field['rows'] = ($field['type'] ?? '') === 'textarea' ? 4 : 1;
                    } else {
                        $field['rows'] = (int) $field['rows'];
                    }

                    if (!isset($field['row_span']) || empty($field['row_span'])) {
                        $field['row_span'] = ($field['type'] ?? '') === 'textarea' ? 2 : 1;
                    } else {
                        $field['row_span'] = (int) $field['row_span'];
                    }
                }
            } else {
                $sec['fields'] = [];
            }
        }
    }

    public function updatedSchema()
    {
        $this->normalizeSchema();
        $this->syncRawJson();
    }

    public function updatedTitle()
    {
        $this->schema['title'] = $this->title;
        $this->syncRawJson();
    }

    public function updatedDescription()
    {
        $this->schema['description'] = $this->description;
        $this->syncRawJson();
    }

    public function setDisplayMode(string $mode)
    {
        if (in_array($mode, ['wizard', 'single_page'])) {
            $schema = $this->schema;
            $schema['settings']['display_mode'] = $mode;
            $this->schema = $schema;
            $this->syncRawJson();
        }
    }

    public function setLayoutMode(string $mode)
    {
        if (in_array($mode, ['grid', 'freeform'])) {
            $schema = $this->schema;
            $schema['settings']['layout_mode'] = $mode;
            $this->schema = $schema;
            $this->syncRawJson();
        }
    }

    public function updateFieldProp(string $fieldId, string $key, $value)
    {
        foreach ($this->schema['sections'] as $sIndex => $sec) {
            foreach ($sec['fields'] as $fIndex => $field) {
                if (($field['id'] ?? '') === $fieldId) {
                    $this->schema['sections'][$sIndex]['fields'][$fIndex][$key] = $value;
                    $this->syncRawJson();
                    return;
                }
            }
        }
    }

    public function resizeField2D(string $fieldId, int $colSpan, int $rowSpan)
    {
        foreach ($this->schema['sections'] as $sIndex => $sec) {
            foreach ($sec['fields'] as $fIndex => $field) {
                if (($field['id'] ?? '') === $fieldId) {
                    $this->schema['sections'][$sIndex]['fields'][$fIndex]['col_span'] = max(1, min(12, $colSpan));
                    $this->schema['sections'][$sIndex]['fields'][$fIndex]['row_span'] = max(1, min(6, $rowSpan));
                    $this->syncRawJson();
                    return;
                }
            }
        }
    }

    public function syncRawJson()
    {
        $this->rawJson = json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->jsonError = '';
    }

    public function updatedRawJson($value)
    {
        $this->validateAndApplyRawJson($value);
    }

    public function validateAndApplyRawJson(?string $jsonString = null): bool
    {
        $jsonString = $jsonString ?? $this->rawJson;

        if (empty(trim($jsonString))) {
            $this->jsonError = 'JSON schema cannot be empty.';
            return false;
        }

        $decoded = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError = 'JSON Syntax Error: ' . json_last_error_msg();
            return false;
        }

        if (!is_array($decoded)) {
            $this->jsonError = 'Schema Validation Error: Root element must be a JSON object.';
            return false;
        }

        if (!isset($decoded['sections']) || !is_array($decoded['sections'])) {
            $this->jsonError = 'Schema Validation Error: Schema must contain a "sections" array.';
            return false;
        }

        foreach ($decoded['sections'] as $i => $section) {
            if (!is_array($section)) {
                $this->jsonError = "Schema Validation Error: Section #" . ($i + 1) . " must be an object.";
                return false;
            }
            if (!isset($section['fields']) || !is_array($section['fields'])) {
                $this->jsonError = "Schema Validation Error: Section '" . ($section['title'] ?? ("#" . ($i + 1))) . "' must contain a 'fields' array.";
                return false;
            }
            foreach ($section['fields'] as $j => $field) {
                if (!is_array($field)) {
                    $this->jsonError = "Schema Validation Error: Field #" . ($j + 1) . " in Section '" . ($section['title'] ?? ("#" . ($i + 1))) . "' must be an object.";
                    return false;
                }
                if (!isset($field['type'])) {
                    $this->jsonError = "Schema Validation Error: Field #" . ($j + 1) . " in Section '" . ($section['title'] ?? ("#" . ($i + 1))) . "' missing required 'type' attribute.";
                    return false;
                }
            }
        }

        $this->schema = $decoded;
        if (isset($decoded['title']) && !empty($decoded['title'])) {
            $this->title = $decoded['title'];
        }
        if (isset($decoded['description'])) {
            $this->description = $decoded['description'];
        }
        $this->normalizeSchema();
        $this->jsonError = '';
        return true;
    }

    public function addField(string $sectionId, string $type)
    {
        $targetId = $sectionId ?: ($this->selectedSectionId ?: ($this->schema['sections'][0]['id'] ?? ''));

        $newField = [
            'id' => 'fld_' . Str::random(6),
            'key' => 'field_' . Str::random(4),
            'type' => $type,
            'label' => Str::headline($type) . ' Field',
            'placeholder' => 'Enter ' . strtolower($type),
            'help_text' => '',
            'default' => '',
            'required' => false,
            'options' => in_array($type, ['dropdown', 'radio', 'checkbox']) ? ['Option 1', 'Option 2', 'Option 3'] : [],
            'multiple' => $type === 'dropdown' ? false : null,
            'col_span' => 12, // 1 to 12 column span in grid
            'row_span' => $type === 'textarea' ? 2 : 1, // 1 to 6 row height span in freeform canvas
            'align' => 'left', // left, center, right
            'valign' => 'center', // top, center, bottom, stretch
            'rows' => $type === 'textarea' ? 4 : 1, // height/rows for textareas
            'validation' => [
                'numeric' => false,
                'email' => false,
                'url' => false,
                'min' => null,
                'max' => null,
                'min_length' => null,
                'max_length' => null,
                'regex' => null,
                'file_types' => null,
                'max_file_size' => null,
            ]
        ];

        $schema = $this->schema;
        foreach ($schema['sections'] as &$sec) {
            if ($sec['id'] === $targetId) {
                $sec['fields'][] = $newField;
                break;
            }
        }
        $this->schema = $schema;

        $this->syncRawJson();
    }

    public function updateFieldOptions(string $sectionId, string $fieldId, string $optionsString)
    {
        $optionsArray = array_values(array_filter(array_map('trim', explode(',', $optionsString))));
        $schema = $this->schema;
        foreach ($schema['sections'] as &$sec) {
            if ($sec['id'] === $sectionId) {
                foreach ($sec['fields'] as &$field) {
                    if ($field['id'] === $fieldId) {
                        $field['options'] = $optionsArray;
                        break 2;
                    }
                }
            }
        }
        $this->schema = $schema;
        $this->syncRawJson();
    }

    public function removeField(string $sectionId, string $fieldId)
    {
        $schema = $this->schema;
        foreach ($schema['sections'] as &$sec) {
            if ($sec['id'] === $sectionId) {
                $sec['fields'] = array_values(array_filter($sec['fields'], fn($f) => $f['id'] !== $fieldId));
                break;
            }
        }
        $this->schema = $schema;

        $this->syncRawJson();
    }

    public function duplicateField(string $sectionId, string $fieldId)
    {
        $schema = $this->schema;
        foreach ($schema['sections'] as &$sec) {
            if ($sec['id'] === $sectionId) {
                foreach ($sec['fields'] as $index => $field) {
                    if ($field['id'] === $fieldId) {
                        $cloned = $field;
                        $cloned['id'] = 'fld_' . Str::random(6);
                        $cloned['key'] = $field['key'] . '_copy';
                        $cloned['label'] = $field['label'] . ' (Copy)';
                        array_splice($sec['fields'], $index + 1, 0, [$cloned]);
                        break 2;
                    }
                }
            }
        }
        $this->schema = $schema;

        $this->syncRawJson();
    }

    public function moveField(string $fromSectionId, int $fromIndex, int $toIndex, ?string $toSectionId = null)
    {
        $toSectionId = $toSectionId ?? $fromSectionId;
        $schema = $this->schema;

        $movedField = null;
        foreach ($schema['sections'] as &$sec) {
            if ($sec['id'] === $fromSectionId) {
                if (isset($sec['fields'][$fromIndex])) {
                    $movedField = array_splice($sec['fields'], $fromIndex, 1)[0];
                }
                break;
            }
        }

        if ($movedField !== null) {
            foreach ($schema['sections'] as &$sec) {
                if ($sec['id'] === $toSectionId) {
                    if ($toIndex < 0) {
                        $toIndex = 0;
                    }
                    if ($toIndex > count($sec['fields'])) {
                        $toIndex = count($sec['fields']);
                    }
                    array_splice($sec['fields'], $toIndex, 0, [$movedField]);
                    break;
                }
            }
        }

        $this->schema = $schema;
        $this->syncRawJson();
    }

    public function moveFieldToSection(string $fromSectionId, int $fromIndex, string $toSectionId)
    {
        if ($fromSectionId === $toSectionId) {
            return;
        }
        $this->moveField($fromSectionId, $fromIndex, 9999, $toSectionId);
    }

    public function moveSection($fromIndex, $toIndex)
    {
        $fromIndex = (int) $fromIndex;
        $toIndex = (int) $toIndex;
        $schema = $this->schema;
        if (isset($schema['sections'][$fromIndex]) && $toIndex >= 0 && $toIndex < count($schema['sections'])) {
            $sec = array_splice($schema['sections'], $fromIndex, 1)[0];
            array_splice($schema['sections'], $toIndex, 0, [$sec]);
        }
        $this->schema = $schema;
        $this->syncRawJson();
    }

    public function reorderFields(string $sectionId, array $orderedFieldIds)
    {
        $schema = $this->schema;
        foreach ($schema['sections'] as &$sec) {
            if ($sec['id'] === $sectionId) {
                $fieldMap = [];
                foreach ($sec['fields'] as $f) {
                    $fieldMap[$f['id']] = $f;
                }
                $newFields = [];
                foreach ($orderedFieldIds as $fid) {
                    if (isset($fieldMap[$fid])) {
                        $newFields[] = $fieldMap[$fid];
                    }
                }
                foreach ($sec['fields'] as $f) {
                    if (!in_array($f['id'], $orderedFieldIds)) {
                        $newFields[] = $f;
                    }
                }
                $sec['fields'] = $newFields;
                break;
            }
        }
        $this->schema = $schema;
        $this->syncRawJson();
    }

    public function addSection()
    {
        $newSecId = 'sec_' . Str::random(5);
        $schema = $this->schema;
        $schema['sections'][] = [
            'id' => $newSecId,
            'title' => 'New Section',
            'fields' => []
        ];
        $this->schema = $schema;
        $this->selectedSectionId = $newSecId;
        $this->syncRawJson();
    }

    public function removeSection(string $sectionId)
    {
        $schema = $this->schema;
        $schema['sections'] = array_values(array_filter($schema['sections'], fn($s) => $s['id'] !== $sectionId));
        $this->schema = $schema;

        if ($this->selectedSectionId === $sectionId) {
            $this->selectedSectionId = $this->schema['sections'][0]['id'] ?? '';
        }

        $this->syncRawJson();
    }

    public function applyTemplate(int $templateId)
    {
        $template = FormTemplate::findOrFail($templateId);
        $this->title = $template->title;
        $this->description = $template->description ?? '';
        $this->schema = $template->schema;
        $this->selectedSectionId = $this->schema['sections'][0]['id'] ?? '';
        $this->syncRawJson();
        $this->activeTab = 'visual';
        session()->flash('message', "Applied template: {$template->title}");
    }

    public function applyAiEdit()
    {
        if (empty($this->aiEditPrompt)) return;

        $this->isAiProcessing = true;
        $aiService = app(AiServiceInterface::class);
        $updatedSchema = $aiService->editFormSchema($this->schema, $this->aiEditPrompt, $this->form?->id);

        $this->schema = $updatedSchema;
        if (isset($updatedSchema['title']) && !empty($updatedSchema['title'])) {
            $this->title = $updatedSchema['title'];
        }
        if (isset($updatedSchema['description']) && !empty($updatedSchema['description'])) {
            $this->description = $updatedSchema['description'];
        }
        $this->selectedSectionId = $this->schema['sections'][0]['id'] ?? '';

        $this->syncRawJson();
        $this->aiEditPrompt = '';
        $this->isAiProcessing = false;
        $this->activeTab = 'visual';
        session()->flash('message', 'Form updated using AI instruction!');
    }

    public function saveForm()
    {
        if ($this->activeTab === 'json') {
            if (!$this->validateAndApplyRawJson()) {
                $this->successMessage = null;
                session()->flash('error', 'Cannot save: ' . $this->jsonError);
                return;
            }
        }

        $this->validate([
            'title' => 'required|string|max:255',
            'schema' => 'required|array',
        ]);

        if ($this->jsonError) {
            $this->successMessage = null;
            session()->flash('error', 'Cannot save: Please fix raw JSON error first.');
            return;
        }

        $this->normalizeSchema();
        $this->schema['title'] = $this->title;
        $this->schema['description'] = $this->description;
        $this->syncRawJson();

        if ($this->form && $this->form->exists) {
            $this->form->update([
                'title' => $this->title,
                'description' => $this->description,
                'schema' => $this->schema,
            ]);
            \Illuminate\Support\Facades\Cache::forget("public_form_{$this->form->slug}");
            $this->successMessage = 'Form saved successfully at ' . date('h:i:s A') . '!';
            session()->flash('message', $this->successMessage);
        } else {
            $this->form = Form::create([
                'title' => $this->title,
                'description' => $this->description,
                'schema' => $this->schema,
            ]);
            \Illuminate\Support\Facades\Cache::forget("public_form_{$this->form->slug}");
            $this->successMessage = 'Form created successfully!';
            session()->flash('message', 'Form created successfully!');
            return redirect()->route('forms.edit', ['uuid' => $this->form->uuid]);
        }
    }

    public function render()
    {
        $templates = \Illuminate\Support\Facades\Cache::remember('form_templates_all', 86400, function () {
            return FormTemplate::all();
        });

        return view('livewire.form-builder', [
            'templates' => $templates,
        ])->layout('layouts.app');
    }
}
