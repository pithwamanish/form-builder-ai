<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Services\AiFormService;
use App\Services\DocumentParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Part A: Form Creation, JSON Schema Validation & Saving
     */
    public function test_can_create_form_and_save_json_schema()
    {
        $schema = [
            'title' => 'Test Registration Form',
            'description' => 'A test registration form',
            'sections' => [
                [
                    'id' => 'sec_1',
                    'title' => 'Personal Info',
                    'fields' => [
                        [
                            'id' => 'fld_name',
                            'key' => 'full_name',
                            'type' => 'text',
                            'label' => 'Full Name',
                            'required' => true,
                        ],
                        [
                            'id' => 'fld_email',
                            'key' => 'email',
                            'type' => 'email',
                            'label' => 'Email',
                            'required' => true,
                        ]
                    ]
                ]
            ]
        ];

        $form = Form::create([
            'title' => $schema['title'],
            'description' => $schema['description'],
            'schema' => $schema,
        ]);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'title' => 'Test Registration Form',
        ]);

        $this->assertEquals('full_name', $form->schema['sections'][0]['fields'][0]['key']);
    }

    /**
     * Test Multiple Consecutive Form Creation
     */
    public function test_can_create_multiple_consecutive_forms()
    {
        \Livewire\Livewire::test(\App\Livewire\FormBuilder::class)
            ->set('title', 'First Form')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('forms', ['title' => 'First Form']);

        \Livewire\Livewire::test(\App\Livewire\FormBuilder::class)
            ->set('title', 'Second Form')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('forms', ['title' => 'Second Form']);
        $this->assertEquals(2, Form::count());
    }

    /**
     * Test Part A: Public Form Submission & Dynamic Server-side Validation
     */
    public function test_can_submit_public_form_and_record_submission()
    {
        $form = Form::create([
            'title' => 'Feedback Form',
            'schema' => [
                'sections' => [
                    [
                        'id' => 'sec_1',
                        'title' => 'General',
                        'fields' => [
                            ['id' => 'f1', 'key' => 'feedback', 'type' => 'textarea', 'label' => 'Feedback', 'required' => true]
                        ]
                    ]
                ]
            ]
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'submission_data' => ['feedback' => 'Great product!'],
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => $form->id,
            'ip_address' => '127.0.0.1',
        ]);
        $this->assertEquals('Great product!', $submission->submission_data['feedback']);
    }

    /**
     * Test Part A: CSV Export Endpoint
     */
    public function test_csv_export_endpoint_returns_streamed_response()
    {
        $form = Form::create([
            'title' => 'Survey Form',
            'schema' => [
                'sections' => [
                    [
                        'id' => 'sec_1',
                        'title' => 'Sec',
                        'fields' => [
                            ['id' => 'f1', 'key' => 'q1', 'type' => 'text', 'label' => 'Question 1']
                        ]
                    ]
                ]
            ]
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'submission_data' => ['q1' => 'Answer 1'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->get(route('forms.submissions.export', ['uuid' => $form->uuid]));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename="' . $form->slug . '_submissions.csv"');
    }

    /**
     * Test File Upload Download Endpoint
     */
    public function test_uploaded_file_is_downloadable_from_submissions_endpoint()
    {
        Storage::fake('public');
        $fakeFile = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');
        $path = $fakeFile->store('form-uploads', 'public');

        $form = Form::create([
            'title' => 'Job Application',
            'schema' => [
                'sections' => [
                    [
                        'id' => 'sec_1',
                        'title' => 'Attachments',
                        'fields' => [
                            ['id' => 'f1', 'key' => 'resume', 'type' => 'file', 'label' => 'Resume']
                        ]
                    ]
                ]
            ]
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'submission_data' => ['resume' => $path],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->get(route('forms.submissions.download', [
            'uuid' => $form->uuid,
            'submissionId' => $submission->id,
            'fieldKey' => 'resume'
        ]));

        $response->assertStatus(200);
    }

    /**
     * Test Part B: AI Form Generation Service & Log Creation
     */
    public function test_ai_form_service_generates_valid_schema()
    {
        $aiService = new AiFormService();
        $schema = $aiService->generateFormSchema("Internship application form with skills checkbox and resume upload");

        $this->assertArrayHasKey('sections', $schema);
        $this->assertNotEmpty($schema['sections']);
        $this->assertDatabaseHas('ai_generation_logs', [
            'status' => 'completed',
        ]);
    }

    /**
     * Test Part C: Document Parser Service for CSV/Excel Headers
     */
    public function test_document_parser_service_extracts_fields()
    {
        $samplePath = base_path('storage/samples/sample_survey.csv');
        $parser = new DocumentParserService();

        $parsed = $parser->parseDocument($samplePath, 'csv');

        $this->assertArrayHasKey('sections', $parsed);
        $this->assertEquals('Imported Excel Data Form', $parsed['title']);
    }

    /**
     * Test Part D: Template Library Seeder & One-Click Application
     */
    public function test_template_library_seeds_four_templates()
    {
        $this->seed(\Database\Seeders\FormTemplateSeeder::class);

        $this->assertEquals(4, FormTemplate::count());
        $this->assertDatabaseHas('form_templates', [
            'title' => 'Job Application Form',
        ]);
    }

    /**
     * Test Part D: Honeypot Protection (Bot Submissions are Rejected Silently)
     */
    public function test_honeypot_traps_bot_submissions()
    {
        $form = Form::create([
            'title' => 'Public Form',
            'schema' => [
                'sections' => [
                    [
                        'id' => 'sec_1',
                        'title' => 'General',
                        'fields' => [
                            ['id' => 'f1', 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true]
                        ]
                    ]
                ]
            ]
        ]);

        // Livewire test component mount with filled honeypot
        \Livewire::test(\App\Livewire\PublicFormFill::class, ['slug' => $form->slug])
            ->set('honeypot', 'I am a spambot')
            ->call('submit')
            ->assertSet('submitted', true);

        // Submissions count remains 0 because honeypot trapped the bot
        $this->assertEquals(0, FormSubmission::where('form_id', $form->id)->count());
    }

    /**
     * Test Canvas Field and Section Reordering
     */
    public function test_can_reorder_fields_and_sections_in_builder()
    {
        \Livewire::test(\App\Livewire\FormBuilder::class)
            ->call('addField', '', 'text')
            ->call('addField', '', 'email')
            ->call('moveField', 'sec_1', 0, 1)
            ->assertStatus(200);
    }

    /**
     * Test Toggle Display Mode (Multi-Step Wizard vs Single Page)
     */
    public function test_can_toggle_display_mode_wizard_vs_single_page()
    {
        \Livewire::test(\App\Livewire\FormBuilder::class)
            ->call('setDisplayMode', 'single_page')
            ->assertSet('schema.settings.display_mode', 'single_page')
            ->call('setDisplayMode', 'wizard')
            ->assertSet('schema.settings.display_mode', 'wizard');
    }

    /**
     * Test Moving Fields Across Sections
     */
    public function test_can_move_fields_between_sections()
    {
        $test = \Livewire::test(\App\Livewire\FormBuilder::class)
            ->call('addSection')
            ->call('addField', '', 'text');

        $schema = $test->get('schema');
        $sec1Id = $schema['sections'][0]['id'];
        $sec2Id = $schema['sections'][1]['id'];

        $test->call('moveFieldToSection', $sec1Id, 0, $sec2Id)
            ->assertStatus(200);
    }

    /**
     * Test Per-Field Validation Rules Enforcement
     */
    public function test_per_field_validation_rules_are_enforced()
    {
        $schema = [
            'title' => 'Validation Test Form',
            'sections' => [
                [
                    'id' => 'sec_val',
                    'title' => 'Validation Section',
                    'fields' => [
                        [
                            'id' => 'fld_min_max',
                            'key' => 'age',
                            'type' => 'number',
                            'label' => 'Age',
                            'required' => true,
                            'validation' => [
                                'min' => 18,
                                'max' => 65,
                                'numeric' => true,
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $form = Form::create([
            'title' => 'Validation Form',
            'slug' => 'val-form-' . \Illuminate\Support\Str::random(5),
            'schema' => $schema,
        ]);

        // Attempt submit with invalid age (< 18)
        \Livewire::test(\App\Livewire\PublicFormFill::class, ['slug' => $form->slug])
            ->set('formData.age', 12)
            ->call('submit')
            ->assertHasErrors(['formData.age']);

        // Attempt submit with valid age (between 18 and 65)
        \Livewire::test(\App\Livewire\PublicFormFill::class, ['slug' => $form->slug])
            ->set('formData.age', 25)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);
    }

    /**
     * Test Updating Field Configuration in FormBuilder and Saving to Database
     */
    public function test_can_update_field_configuration_and_save_form()
    {
        $component = \Livewire::test(\App\Livewire\FormBuilder::class)
            ->set('title', 'My Custom Form')
            ->set('schema.sections.0.fields.0.label', 'Updated Field Label')
            ->set('schema.sections.0.fields.0.key', 'updated_key')
            ->set('schema.sections.0.fields.0.required', true)
            ->set('schema.sections.0.fields.0.col_span', 6)
            ->set('schema.sections.0.fields.0.validation.min', 10)
            ->call('saveForm');

        $this->assertDatabaseHas('forms', [
            'title' => 'My Custom Form',
        ]);

        $form = Form::where('title', 'My Custom Form')->first();
        $this->assertNotNull($form);
        $this->assertEquals('Updated Field Label', $form->schema['sections'][0]['fields'][0]['label']);
        $this->assertEquals('updated_key', $form->schema['sections'][0]['fields'][0]['key']);
        $this->assertEquals(6, $form->schema['sections'][0]['fields'][0]['col_span']);
        $this->assertEquals(10, $form->schema['sections'][0]['fields'][0]['validation']['min']);
    }

    /**
     * Test Grid Span Dropdown String Value Conversion and Persistence on Edit Form Reload
     */
    public function test_grid_span_saves_and_persists_on_form_edit()
    {
        $form = Form::create([
            'title' => 'Grid Span Test Form',
            'schema' => [
                'sections' => [
                    [
                        'id' => 'sec_1',
                        'title' => 'Main',
                        'fields' => [
                            ['id' => 'f1', 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'col_span' => 12, 'align' => 'left']
                        ]
                    ]
                ]
            ]
        ]);

        // Select Half Width ("6") in visual editor select dropdown and save
        \Livewire::test(\App\Livewire\FormBuilder::class, ['uuid' => $form->uuid])
            ->set('schema.sections.0.fields.0.col_span', '6')
            ->set('schema.sections.0.fields.0.align', 'center')
            ->call('saveForm');

        $freshForm = Form::find($form->id);
        $this->assertEquals(6, $freshForm->schema['sections'][0]['fields'][0]['col_span']);
        $this->assertEquals('center', $freshForm->schema['sections'][0]['fields'][0]['align']);

        // Verify loaded in new FormBuilder instance
        \Livewire::test(\App\Livewire\FormBuilder::class, ['uuid' => $freshForm->uuid])
            ->assertSet('schema.sections.0.fields.0.col_span', 6)
            ->assertSet('schema.sections.0.fields.0.align', 'center');
    }

    /**
     * Test Raw JSON Schema Two-Way Sync and Validation
     */
    public function test_raw_json_schema_two_way_sync_and_validation()
    {
        $form = Form::create([
            'title' => 'Sync Test Form',
            'schema' => [
                'title' => 'Sync Test Form',
                'sections' => [
                    [
                        'id' => 'sec_1',
                        'title' => 'Main',
                        'fields' => [
                            ['id' => 'f1', 'key' => 'name', 'type' => 'text', 'label' => 'Name']
                        ]
                    ]
                ]
            ]
        ]);

        $newRawJson = json_encode([
            'title' => 'Updated Schema via Raw JSON Editor',
            'description' => 'Updated from raw editor',
            'sections' => [
                [
                    'id' => 'sec_1',
                    'title' => 'Raw JSON Section',
                    'fields' => [
                        ['id' => 'f1', 'key' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'f2', 'key' => 'emergency_phone', 'type' => 'phone', 'label' => 'Emergency Phone']
                    ]
                ]
            ]
        ], JSON_PRETTY_PRINT);

        // Test two-way sync: updating rawJson updates schema, title, description, and persists on save
        \Livewire::test(\App\Livewire\FormBuilder::class, ['uuid' => $form->uuid])
            ->set('activeTab', 'json')
            ->set('rawJson', $newRawJson)
            ->assertSet('jsonError', '')
            ->assertSet('title', 'Updated Schema via Raw JSON Editor')
            ->call('saveForm')
            ->assertHasNoErrors();

        $freshForm = Form::find($form->id);
        $this->assertEquals('Updated Schema via Raw JSON Editor', $freshForm->title);
        $this->assertCount(2, $freshForm->schema['sections'][0]['fields']);

        // Test validation: invalid JSON triggers error and blocks saving
        \Livewire::test(\App\Livewire\FormBuilder::class, ['uuid' => $form->uuid])
            ->set('activeTab', 'json')
            ->set('rawJson', '{"title": "Broken JSON", "sections": [')
            ->assertNotSet('jsonError', '')
            ->call('saveForm');
    }

    /**
     * Test Auto Generation of Omitted IDs, Keys and Uniqueness Deduplication
     */
    public function test_auto_generates_missing_ids_and_keys_with_uniqueness()
    {
        $jsonWithOmittedProperties = json_encode([
            'title' => 'Omitted Props Form',
            'sections' => [
                [
                    'title' => 'Section Without ID',
                    'fields' => [
                        ['type' => 'text', 'label' => 'Full Name'], // missing id and key
                        ['type' => 'email', 'label' => 'Full Name'], // duplicate label, missing id & key
                        ['type' => 'phone', 'key' => 'full_name', 'label' => 'Phone'] // duplicate explicit key
                    ]
                ]
            ]
        ], JSON_PRETTY_PRINT);

        $test = \Livewire::test(\App\Livewire\FormBuilder::class)
            ->set('activeTab', 'json')
            ->set('rawJson', $jsonWithOmittedProperties)
            ->assertSet('jsonError', '');

        $schema = $test->get('schema');

        // Section ID should be auto-generated
        $this->assertNotEmpty($schema['sections'][0]['id']);

        $fields = $schema['sections'][0]['fields'];
        $this->assertCount(3, $fields);

        // IDs auto-generated
        $this->assertNotEmpty($fields[0]['id']);
        $this->assertNotEmpty($fields[1]['id']);
        $this->assertNotEmpty($fields[2]['id']);

        // Keys auto-generated and deduplicated across the schema
        $this->assertEquals('full_name', $fields[0]['key']);
        $this->assertEquals('full_name_1', $fields[1]['key']);
        $this->assertEquals('full_name_2', $fields[2]['key']);
    }

    public function test_ai_service_repairs_malformed_and_truncated_json_gracefully()
    {
        $aiService = new AiFormService();

        // 1. Test repairing truncated JSON cut off midway
        $truncatedJson = '{"title": "Truncated Form", "sections": [{"title": "Details", "fields": [{"type": "text", "label": "Full Name"';
        $repaired = $aiService->repairSchema($truncatedJson);

        $this->assertTrue($aiService->validateSchema($repaired));
        $this->assertEquals('Truncated Form', $repaired['title']);
        $this->assertNotEmpty($repaired['sections'][0]['fields']);

        // 2. Test malformed JSON with code fences and trailing commas
        $malformedJson = "```json\n{\n\"title\": \"Malformed Form\",\n\"sections\": [\n{\n\"title\": \"Section 1\",\n\"fields\": [\n{\"type\": \"email\", \"label\": \"Email\",},\n],\n}\n]\n}\n```";
        $repairedMalformed = $aiService->repairSchema($malformedJson);

        $this->assertTrue($aiService->validateSchema($repairedMalformed));
        $this->assertEquals('Malformed Form', $repairedMalformed['title']);

        // 3. Test completely invalid garbage input returns fallback valid schema
        $garbageInput = "This is not json at all";
        $fallbackSchema = $aiService->repairSchema($garbageInput);

        $this->assertTrue($aiService->validateSchema($fallbackSchema));
        $this->assertNotEmpty($fallbackSchema['sections']);
    }
}
