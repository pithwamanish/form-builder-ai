<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Service Provider & Fallback Pipeline
    |--------------------------------------------------------------------------
    |
    | Supported Primary Providers: "fastapi", "mistral", "openai", "auto"
    | When set to "auto", Laravel attempts FastAPI microservice first, then
    | falls back to Mistral / OpenAI API, and finally to local mock generator.
    |
    */

    'provider' => env('AI_PROVIDER', 'mistral'),

    'fastapi_url' => env('FASTAPI_AI_URL', 'http://127.0.0.1:8000'),

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY', ''),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1/chat/completions'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1/chat/completions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI System Prompts & Output Contract
    |--------------------------------------------------------------------------
    |
    | Moved system prompts out of source code into configuration for easy tuning.
    |
    */

    'prompts' => [
        'system_generator' => <<<EOT
You are an expert UX & Database Architect. Generate a valid Form Builder JSON schema.
The JSON output MUST follow this structure exactly:
{
  "title": "Form Title",
  "description": "Short summary",
  "sections": [
    {
      "id": "sec_1",
      "title": "Section Header",
      "fields": [
        {
          "id": "fld_1",
          "key": "unique_snake_case_key",
          "type": "text|textarea|number|email|phone|date|time|dropdown|radio|checkbox|file|heading|rating",
          "label": "Human Readable Label",
          "placeholder": "Sample placeholder",
          "required": true,
          "help_text": "Optional help description",
          "options": ["Option 1", "Option 2"],
          "col_span": 12,
          "align": "left",
          "validation": { "min": 1, "max": 100, "email": true }
        }
      ]
    }
  ]
}
Rules:
- Never return markdown code fences like ```json.
- Always provide clean, sensible field keys, labels, placeholders, and validation rules.
- Supported types: text, textarea, number, email, phone, date, time, dropdown, radio, checkbox, file, heading, rating.
- If an unsupported or hallucinated field type is requested, map it to the closest supported type (e.g., 'password' -> 'text').
EOT,

        'system_editor' => <<<EOT
You are an expert UX & Database Architect. You are editing an EXISTING form schema.
Retain existing sections and fields unless requested to remove or modify them.
CRITICAL INSTRUCTIONS:
1. If the user asks to "Add a [Section Name] section" or "Create an [Section Name] section", you MUST create and append a NEW section item in the "sections" array with "title": "[Section Name]".
2. You MUST include ALL requested fields explicitly mentioned in the user instruction (e.g. if asked for "Name, Phone, and Relation", create separate field objects for Name, Phone, and Relation).
3. Supported field types: text, textarea, number, email, phone, date, time, dropdown, radio, checkbox, file, heading, rating.
Output ONLY a valid JSON object matching the standard schema contract.
EOT,

        'output_schema_contract' => [
            'type' => 'object',
            'required' => ['title', 'sections'],
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'title', 'fields'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'fields' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['id', 'key', 'type', 'label'],
                                    'properties' => [
                                        'id' => ['type' => 'string'],
                                        'key' => ['type' => 'string'],
                                        'type' => ['type' => 'string', 'enum' => ['text', 'textarea', 'number', 'email', 'phone', 'date', 'time', 'dropdown', 'radio', 'checkbox', 'file', 'heading', 'rating']],
                                        'label' => ['type' => 'string'],
                                        'placeholder' => ['type' => 'string'],
                                        'required' => ['type' => 'boolean'],
                                        'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        'col_span' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
