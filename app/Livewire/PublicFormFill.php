<?php

namespace App\Livewire;

use App\Contracts\StorageServiceInterface;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Request;
use Livewire\Component;
use Livewire\WithFileUploads;

use Illuminate\Support\Facades\Cache;

class PublicFormFill extends Component
{
    use WithFileUploads;

    public Form $form;
    public array $formData = [];
    public array $uploads = []; // Handles Livewire file uploads
    public string $honeypot = ''; // Spam protection honeypot
    public int $currentStep = 0;
    public bool $submitted = false;

    public function mount(string $slug)
    {
        // High-Performance Redis Cache lookup for public forms
        $this->form = Cache::remember("public_form_{$slug}", 3600, function () use ($slug) {
            return Form::where('slug', $slug)->firstOrFail();
        });
        $this->form->increment('views_count');

        // Pre-fill defaults from schema
        foreach ($this->form->schema['sections'] ?? [] as $sec) {
            foreach ($sec['fields'] ?? [] as $f) {
                if (($f['type'] === 'checkbox' && isset($f['options']) && is_array($f['options']) && count($f['options']) > 0) || ($f['type'] === 'dropdown' && ($f['multiple'] ?? false))) {
                    $this->formData[$f['key']] = [];
                } elseif ($f['type'] === 'checkbox') {
                    $this->formData[$f['key']] = false;
                } else {
                    $this->formData[$f['key']] = $f['default'] ?? '';
                }
            }
        }
    }

    protected function buildFieldRules(array $f, array &$rules, array &$messages)
    {
        $fieldKey = 'formData.' . $f['key'];
        $isRequired = $f['required'] ?? false;
        $v = $f['validation'] ?? [];

        if ($f['type'] === 'checkbox' && (!isset($f['options']) || !is_array($f['options']) || count($f['options']) === 0)) {
            $rules[$fieldKey] = [$isRequired ? 'accepted' : 'nullable'];
            $messages[$fieldKey . '.accepted'] = "You must check and accept: {$f['label']}.";
            $messages[$fieldKey . '.required'] = "You must check and accept: {$f['label']}.";
            return;
        }

        if ($f['type'] === 'file') {
            $fileRules = [$isRequired ? 'required' : 'nullable', 'file'];
            $maxMb = isset($v['max_file_size']) && (int)$v['max_file_size'] > 0 ? (int)$v['max_file_size'] : 10;
            $maxKb = $maxMb * 1024;
            $fileRules[] = 'max:' . $maxKb;

            if (!empty($v['file_types'])) {
                $types = array_map('trim', explode(',', str_replace('.', '', $v['file_types'])));
                $cleanTypes = array_filter($types);
                if (!empty($cleanTypes)) {
                    $fileRules[] = 'mimes:' . implode(',', $cleanTypes);
                }
            }

            $rules['uploads.' . $f['key']] = $fileRules;
            $messages['uploads.' . $f['key'] . '.required'] = "The {$f['label']} file is required.";
            $messages['uploads.' . $f['key'] . '.mimes'] = "The {$f['label']} must be a file of type: " . ($v['file_types'] ?? 'allowed extensions') . ".";
            $messages['uploads.' . $f['key'] . '.max'] = "The {$f['label']} file size may not exceed {$maxMb}MB.";
            return;
        }

        $fieldRules = [$isRequired ? 'required' : 'nullable'];

        if ($f['type'] === 'email' || !empty($v['email'])) {
            $fieldRules[] = 'email';
        }
        if ($f['type'] === 'url' || !empty($v['url'])) {
            $fieldRules[] = 'url';
        }
        if ($f['type'] === 'number' || !empty($v['numeric'])) {
            $fieldRules[] = 'numeric';
        }
        if ($f['type'] === 'phone') {
            $fieldRules[] = 'regex:/^[0-9+\s\-()]+$/';
            $messages[$fieldKey . '.regex'] = "The {$f['label']} must contain numbers and valid phone symbols only.";
        }

        if (isset($v['min']) && $v['min'] !== '') {
            $fieldRules[] = 'min:' . $v['min'];
        }
        if (isset($v['max']) && $v['max'] !== '') {
            $fieldRules[] = 'max:' . $v['max'];
        }
        if (isset($v['min_length']) && $v['min_length'] !== '') {
            $fieldRules[] = 'min:' . $v['min_length'];
        }
        if (isset($v['max_length']) && $v['max_length'] !== '') {
            $fieldRules[] = 'max:' . $v['max_length'];
        }
        if (!empty($v['regex'])) {
            $fieldRules[] = 'regex:' . $v['regex'];
            $messages[$fieldKey . '.regex'] = "The {$f['label']} format is invalid.";
        }

        $rules[$fieldKey] = $fieldRules;
        $messages[$fieldKey . '.required'] = "The {$f['label']} field is required.";
    }

    public function nextStep()
    {
        $sections = $this->form->schema['sections'] ?? [];
        if (!isset($sections[$this->currentStep])) return;

        // Validate only fields in current section
        $currentSec = $sections[$this->currentStep];
        $rules = [];
        $messages = [];

        foreach ($currentSec['fields'] ?? [] as $f) {
            $this->buildFieldRules($f, $rules, $messages);
        }

        if (!empty($rules)) {
            $this->validate($rules, $messages);
        }

        if ($this->currentStep < count($sections) - 1) {
            $this->currentStep++;
        }
    }

    public function prevStep()
    {
        if ($this->currentStep > 0) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $stepIndex)
    {
        $sections = $this->form->schema['sections'] ?? [];
        if ($stepIndex >= 0 && $stepIndex < count($sections)) {
            $this->currentStep = $stepIndex;
        }
    }

    public function submit()
    {
        // 1. Spam Honeypot Protection check (Part D Differentiator)
        if (!empty($this->honeypot)) {
            // Bot detected! Return fake success silently.
            $this->submitted = true;
            return;
        }

        // 2. Build Server-side Validation Rules dynamically from JSON Schema
        $rules = [];
        $messages = [];

        foreach ($this->form->schema['sections'] ?? [] as $sec) {
            foreach ($sec['fields'] ?? [] as $f) {
                $this->buildFieldRules($f, $rules, $messages);
            }
        }

        $this->validate($rules, $messages);

        // Process File Uploads into submission data via Cloudinary Cloud Storage Service
        $storage = app(StorageServiceInterface::class);
        foreach ($this->form->schema['sections'] ?? [] as $sec) {
            foreach ($sec['fields'] ?? [] as $f) {
                if ($f['type'] === 'file' && isset($this->uploads[$f['key']])) {
                    $path = $storage->upload($this->uploads[$f['key']], 'form-uploads');
                    $this->formData[$f['key']] = $path;
                }
            }
        }

        // 3. Persist Submission
        FormSubmission::create([
            'form_id' => $this->form->id,
            'submission_data' => $this->formData,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.public-form-fill')->layout('layouts.app');
    }
}
