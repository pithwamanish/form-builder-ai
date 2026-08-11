# 🚀 FormCraft AI — Intelligent AI-Powered Form Builder Studio

[![Tests](https://img.shields.io/badge/Tests-14%20Passed-success)](file:///)
[![Laravel](https://img.shields.io/badge/Laravel-11-orange)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3-pink)](https://livewire.laravel.com)
[![FastAPI](https://img.shields.io/badge/FastAPI-Python%203.11-blue)](https://fastapi.tiangolo.com)
[![Instructor](https://img.shields.io/badge/Instructor-Pydantic-purple)](https://github.com/jxnl/instructor)
[![LiteLLM](https://img.shields.io/badge/LiteLLM-Universal%20Routing-green)](https://github.com/BerriAI/litellm)
[![Prism](https://img.shields.io/badge/Prism-Laravel%20LLM-indigo)](https://github.com/echolabsdev/prism)
[![Cloudinary](https://img.shields.io/badge/Cloudinary-Cloud%20Storage-blue)](https://cloudinary.com)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED)](https://www.docker.com)

FormCraft AI is a state-of-the-art, production-grade AI-powered Form Builder built with **Laravel 11 (Livewire 3)**, **Python FastAPI (Instructor + LiteLLM)**, **Prism SDK**, **Cloudinary Object Storage**, **MySQL**, and **Docker**.

FormCraft AI empowers users to generate, refine, and publish dynamic web forms from natural language prompts, document uploads (`.docx` / `.xlsx`), or a **2D Freeform Spatial Grid Canvas** with diagonal multi-column and multi-row field resizing.

---

## 🔗 Live Demo & Project Credentials

- **Live Application URL**: [https://form-builder-app-h1c9.onrender.com/](https://form-builder-app-h1c9.onrender.com/)
- **Demo Access / Credentials**: **Open Access (No Authentication Required)** — The application is configured for instant evaluation. Evaluators can access all features (Form Studio, Visual Builder, AI Schema Generator, Document Importer, Public Form Fill, Submissions Dashboard) directly without logging in.

---

## 🏛️ System Architecture & SDK AI Orchestration

FormCraft AI features a decoupled microservice architecture designed for type-safe schema enforcement, universal model routing, and automatic multi-tier fallback resiliency:

```
                  ┌─────────────────────────────────────────────────────────────┐
                  │                 User Web Browser (Livewire UI)              │
                  └──────────────────────────────┬──────────────────────────────┘
                                                 │
                                                 ▼
                  ┌─────────────────────────────────────────────────────────────┐
                  │               Laravel 11 App Container (PHP 8.2)           │
                  │             (Prism SDK LLM Abstraction Layer)               │
                  └───────┬──────────────────────┬──────────────────────┬───────┘
                          │                      │                      │
                          │                      │                      │
                          ▼                      ▼                      ▼
           ┌────────────────────────┐  ┌───────────────────┐  ┌────────────────────────┐
           │ Python FastAPI AI      │  │ MySQL Database    │  │ Cloudinary Object      │
           │ Microservice (Port 8000)│  │ (Forms, Logs,     │  │ Cloud Storage          │
           │ (Instructor + LiteLLM) │  │ Submissions)      │  │ (Form File Uploads)    │
           └───────────┬────────────┘  └───────────────────┘  └────────────────────────┘
                       │
                       ▼
           ┌────────────────────────┐
           │ Mistral / OpenAI APIs  │
           │ (Universal Provider)   │
           └────────────────────────┘
```

### 1. SDK-Native Dual-Tier AI Pipeline
- **Tier 1 (Python FastAPI Microservice with Instructor & LiteLLM)**: Containerized REST microservice (`ai-service/`) utilizing `Instructor` for Pydantic type-safe schema enforcement and `LiteLLM` for universal multi-model routing across Mistral AI and OpenAI endpoints.
- **Tier 2 (Laravel Prism SDK Orchestration)**: Integrated via `echolabsdev/prism` SDK. If the Python microservice is offline or set to `FASTAPI_AI_URL=disabled`, Laravel seamlessly handles generation natively via Prism.

### 2. Standardized Model-Naming & Observability Architecture
Model tracking across all microservice layers follows a unified reporting standard:
`layer:engine:model`

Examples:
- `fastapi:instructor:mistral-small-latest` (FastAPI + Instructor Pydantic engine)
- `prism:laravel:mistral-small-latest` (Laravel Native Prism SDK engine)

Every generation logs exact `prompt_tokens`, `completion_tokens`, `total_tokens`, `latency_seconds`, and execution status to `ai_generation_logs` in MySQL.

### 3. Zero-Local Persistence Cloud Storage
- Files submitted by public users stream directly to **Cloudinary Cloud Storage** (`FILESYSTEM_DISK=cloudinary`).
- Temporary local file buffers are unlinked immediately post-upload, ensuring **zero persistent disk overhead**.

---

## 🎨 2D Freeform Spatial Canvas Layout Engine

FormCraft AI features a **2D CSS Grid Layout Engine** (`grid-template-columns: repeat(12, 1fr); grid-auto-flow: dense;`) supporting dynamic 2D spatial placement:

1. **Diagonal Multi-Dimension Resizing**:
   - Fields support simultaneous **Horizontal Grid Width Spanning** (`col_span`: 1 to 12) AND **Vertical Row Height Spanning** (`row_span`: 1 to 6 rows).
   - Interactive purple diagonal corner handles (`📐 col_span × row_span`) allow users to scale width and height simultaneously on the canvas.
2. **Vertical Alignment Controls (`valign`)**:
   - Configure field vertical positioning (`top`, `center`, `bottom`, `stretch`) via the Field Inspector.
   - Textareas, dropzones, and checklists automatically adjust vertical flex alignment inside multi-row grid cells.
3. **Public Form Submission Parity**:
   - `public-form-fill.blade.php` renders the exact same CSS 2D Grid spatial layout, ensuring form submitters view the exact multi-row design created in the builder.

---

## 🗄️ MySQL Database Schema & High-Scale Indexing Strategy

FormCraft AI utilizes a highly optimized MySQL schema designed for multi-tenant isolation, rapid form rendering, and high-concurrency submission ingestion.

```
                    ┌─────────────────────────┐
                    │          forms          │
                    ├─────────────────────────┤
                    │ id (PK)                 │
                    │ uuid (UNIQUE INDEX)     │
                    │ tenant_id (INDEX)       │
                    │ slug (UNIQUE INDEX)     │
                    │ is_published (INDEX)    │
                    │ (tenant, published, idx)│
                    └────────────┬────────────┘
                                 │ 1:N
                                 ▼
                    ┌─────────────────────────┐
                    │    form_submissions     │
                    ├─────────────────────────┤
                    │ id (PK)                 │
                    │ form_id (FK, INDEX)     │
                    │ tenant_id (INDEX)       │
                    │ uuid (UNIQUE INDEX)     │
                    │ (form_id, created_at)   │
                    │ (tenant, created_at)    │
                    └─────────────────────────┘
```

### Table Schemas & Production Indexes

#### 1. `forms`
Stores form metadata, settings, and the canonical JSON schema definition.
- **Indexes**:
  - `PRIMARY KEY (id)`
  - `UNIQUE INDEX forms_uuid_unique (uuid)`: Sub-millisecond lookup by public UUID for builder and fill routes.
  - `UNIQUE INDEX forms_slug_unique (slug)`: Fast SEO-friendly URL resolution (`/f/{slug}`).
  - `INDEX forms_tenant_id_index (tenant_id)`: Multi-tenant tenant scoping isolation.
  - `INDEX forms_is_published_index (is_published)`: Quick status filtering.
  - `COMPOSITE INDEX forms_tenant_published_updated_idx (tenant_id, is_published, updated_at DESC)`: Optimizes dashboard queries filtering published forms by tenant ordered by recent updates.

#### 2. `form_submissions`
Stores submission responses and uploaded Cloudinary attachment metadata.
- **Indexes**:
  - `PRIMARY KEY (id)`
  - `UNIQUE INDEX form_submissions_uuid_unique (uuid)`: Secure file download verification.
  - `INDEX form_submissions_form_id_foreign (form_id)`: Foreign key constraint for fast relation loading.
  - `INDEX form_submissions_tenant_id_index (tenant_id)`: Tenant data boundary isolation.
  - `COMPOSITE INDEX form_submissions_form_created_idx (form_id, created_at DESC)`: High-performance pagination for sub-second submission viewing.
  - `COMPOSITE INDEX form_submissions_tenant_created_idx (tenant_id, created_at DESC)`: Tenant-wide analytics and CSV export streaming.

#### 3. `ai_generation_logs`
Tracks AI generation prompt tokens, completion tokens, latency, provider model tags, and execution status.
- **Indexes**:
  - `PRIMARY KEY (id)`
  - `INDEX ai_generation_logs_form_id_index (form_id)`: Maps AI runs to target forms.
  - `INDEX ai_generation_logs_tenant_id_index (tenant_id)`: Tenant usage metrics.
  - `INDEX ai_generation_logs_status_index (status)`: Async polling monitor lookup (`pending` ➔ `processing` ➔ `completed`).
  - `COMPOSITE INDEX ai_logs_tenant_status_created_idx (tenant_id, status, created_at DESC)`: Enables fast queue worker status checking.

#### 4. `document_import_logs`
Audits Word (`.docx`) and Excel (`.xlsx`/`.csv`) file import processing status and unparseable blocks.
- **Indexes**:
  - `PRIMARY KEY (id)`
  - `INDEX document_import_logs_tenant_id_index (tenant_id)`
  - `INDEX document_import_logs_status_index (status)`

#### 5. `form_templates`
Pre-seeded starter form schemas (`Contact Inquiry`, `Job Application`, `Event Registration`, `Customer Feedback`).
- **Indexes**:
  - `PRIMARY KEY (id)`
  - `UNIQUE INDEX form_templates_slug_unique (slug)`
  - `INDEX form_templates_category_index (category)`

---

## 🧠 AI Prompting Strategy & Output Schema Contract

FormCraft AI enforces a production-grade LLM prompting pipeline engineered for zero-hallucination structural outputs.

### 1. System Prompt Strategy
The system prompt establishes strict persona boundaries, forces valid JSON schema generation, and explicitly dictates allowed field types:

```text
You are an expert AI Form Builder Architect. Your job is to output a single, strictly valid JSON object representing a complete form schema based on the user prompt.

RULES:
1. Output ONLY a raw JSON object. Do not include markdown code fences, preambles, or conversational commentary.
2. Every field must use one of the following exact types:
   ["text", "textarea", "number", "email", "phone", "date", "time", "dropdown", "radio", "checkbox", "file", "heading", "rating"]
3. Format section and field IDs with unique prefixes: "sec_1", "fld_1".
4. Storage keys ('key') MUST be lowercase snake_case strings.
5. Provide reasonable 'col_span' (1 to 12) and 'row_span' (1 to 6) grid layouts.
```

### 2. Output Schema Contract
Every AI response MUST conform to the canonical JSON contract below:

```json
{
  "title": "Form Title",
  "description": "Short summary",
  "settings": {
    "display_mode": "single_page",
    "layout_mode": "freeform"
  },
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
          "help_text": "Optional subtext hint",
          "options": ["Option 1", "Option 2"],
          "col_span": 6,
          "row_span": 2,
          "align": "left",
          "valign": "center",
          "validation": { "min": 1, "max": 100, "email": true }
        }
      ]
    }
  ]
}
```

### 3. Handling Hallucinated Field Types
When LLMs return non-standard field types, FormCraft AI applies a two-pass deterministic normalization resolver:

| Hallucinated Type Return | Normalized Field Type |
| :--- | :--- |
| `string`, `short_text`, `input`, `textbox` | `text` |
| `paragraph`, `long_text`, `multiline`, `notes` | `textarea` |
| `select`, `select_box`, `combobox`, `list` | `dropdown` |
| `choice`, `multiple_choice`, `single_choice` | `radio` |
| `checkbox_group`, `multi_select`, `boolean` | `checkbox` |
| `upload`, `attachment`, `document`, `image` | `file` |
| `integer`, `float`, `currency`, `amount` | `number` |
| `datetime`, `calendar` | `date` |
| `star`, `score`, `feedback_rating` | `rating` |
| *Unknown / Unrecognized string* | `text` *(Default Safety Fallback)* |

### 4. Retries, Auto-Repair & Fallback Pipeline
- **Truncated JSON Repair (`repairTruncatedJson`)**: Fixes missing closing brackets (`]`, `}`) or unclosed strings caused by max token cutoffs.
- **Regex Sanitization (`repairSchema`)**: Strips control characters, trailing commas, and unescaped newlines.
- **3-Tier Execution Fallback**:
  1. **Tier 1 (FastAPI Microservice)**: Containerized Instructor Pydantic engine (`http://ai:8000`).
  2. **Tier 2 (Laravel Prism SDK)**: Direct fallback call to Mistral AI / OpenAI via `echolabsdev/prism`.
  3. **Tier 3 (Contextual Local Mock Generator)**: Guarantees zero-downtime execution even without active LLM API keys.

---

## ⚙️ Full Environment Configuration Reference (`.env`)

| Variable Name | Required | Default Value | Supported Values / Options | Description |
| :--- | :--- | :--- | :--- | :--- |
| `APP_ENV` | Yes | `local` | `local`, `production`, `testing` | Environment mode |
| `APP_KEY` | Yes | `base64:...` | Standard 32-char key | Encryption key |
| `FILESYSTEM_DISK` | Yes | `cloudinary` | `cloudinary`, `public`, `s3`, `local` | Default file storage driver |
| `CLOUDINARY_URL` | Yes | - | `cloudinary://API_KEY:API_SECRET@CLOUD_NAME` | Cloudinary master URL |
| `CLOUDINARY_CLOUD_NAME` | Yes | - | String | Cloudinary cloud account identifier |
| `CLOUDINARY_API_KEY` | Yes | - | String | Cloudinary API Key |
| `CLOUDINARY_API_SECRET` | Yes | - | String | Cloudinary API Secret |
| `FASTAPI_AI_URL` | Yes | `http://ai:8000` | `http://ai:8000`, `disabled`, `false`, `http://127.0.0.1:8001` | **AI Execution Router**: Set to `disabled` to use Laravel Prism SDK |
| `AI_PROVIDER` | No | `auto` | `auto`, `fastapi`, `prism`, `mistral`, `openai` | AI provider routing strategy |
| `MISTRAL_API_KEY` | Optional | - | Valid Mistral API Key | Mistral AI key for LLM generation |
| `MISTRAL_MODEL` | No | `mistral-small-latest` | `mistral-small-latest`, `mistral-medium`, `mistral-large` | Mistral model choice |
| `OPENAI_API_KEY` | Optional | - | Valid OpenAI API Key | OpenAI key for LLM generation |
| `OPENAI_MODEL` | No | `gpt-4o-mini` | `gpt-4o-mini`, `gpt-4o`, `gpt-3.5-turbo` | OpenAI model choice |
| `QUEUE_CONNECTION` | Yes | `database` | `database`, `redis`, `sync` | Asynchronous queue driver |

---

## 🛠️ Complete CLI Commands Guide

### 1. Docker Environment Management
```bash
# Build and start all 5 containers (app, webserver, db, redis, ai)
docker compose up -d

# Rebuild a specific container (e.g. Python AI microservice)
docker compose up -d --build ai

# View live container logs
docker logs -f form_builder_ai
docker logs -f form_builder_app
```

### 2. Queue Workers & Caching Commands
```bash
# Run queue worker in background (Process AI generation & document import jobs)
docker compose exec app php artisan queue:listen

# Clear configuration and application cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
```

### 3. Database Migration & Seeding
```bash
# Run fresh database migrations with seeders
docker compose exec app php artisan migrate:fresh --seed
```

### 4. Interactive Testing via `artisan tinker`
```bash
# Test AI Generation (Prism SDK / FastAPI Microservice)
docker compose exec app php artisan tinker --execute="\$service = app(\App\Services\AiFormService::class); \$schema = \$service->generateFormSchema('Vendor Onboarding'); echo 'Generated Title: ' . \$schema['title'] . PHP_EOL;"

# View latest AI Model Tag & Token Usage Log
docker compose exec app php artisan tinker --execute="echo json_encode(DB::table('ai_generation_logs')->latest()->first(), JSON_PRETTY_PRINT);"
```

### 5. Automated Feature Testing
```bash
# Run full automated PHPUnit test suite (14 Passed)
docker compose exec app php artisan test
```

---

## 📡 API & REST Endpoint Documentation

### 1. Laravel Web Application Routes

| Method | Endpoint | Description | Query / Payload Parameters |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | Form List & Studio Dashboard | Lists all saved forms and template library |
| `GET` | `/forms/builder/{uuid}` | Live Form Builder & 2D Canvas Editor | Interactive schema builder with Livewire 3 |
| `GET` | `/generate` | Natural Language AI Form Generator | Real-time status monitor (`pending` ➔ `processing` ➔ `completed`) |
| `GET` | `/import` | Document Importer (`.docx` / `.xlsx`) | Drag-and-drop document upload with interactive field mapping |
| `GET` | `/f/{slug}` | Public Form Submission Page | Render form (Wizard or Single Page mode) |
| `POST` | `/f/{slug}` | Submit Form Response | Form fields payload + file attachments |
| `GET` | `/forms/{form}/submissions` | Submissions Dashboard | View submission details and Cloudinary links |
| `GET` | `/forms/{form}/export/csv` | CSV Export | Streamed CSV output of all responses |
| `GET` | `/submissions/download/{submission}` | Secure File Download | Redirects to Cloudinary download link |

---

### 2. Python FastAPI Microservice Endpoint (`ai-service`)

#### `POST /generate-form`
Generates a complete, type-safe Form JSON Schema using `Instructor` and `LiteLLM`.

- **URL (Inside Docker Network)**: `http://ai:8000/generate-form`
- **URL (Host Machine)**: `http://127.0.0.1:8001/generate-form`
- **Headers**: `Content-Type: application/json`

#### Request Payload:
```json
{
  "prompt": "Job application form with portfolio links",
  "form_id": 1
}
```

#### Curl Command (From Host Terminal):
```bash
curl -X POST http://127.0.0.1:8001/generate-form \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Job application form with portfolio links"}'
```

#### Successful JSON Response:
```json
{
  "status": "completed",
  "provider": "fastapi:instructor:mistral-small-latest",
  "latency_seconds": 3.85,
  "tokens": {
    "prompt_tokens": 301,
    "completion_tokens": 420,
    "total_tokens": 721
  },
  "schema": {
    "title": "Job Application Form",
    "description": "Submit your application along with portfolio links.",
    "sections": [
      {
        "id": "sec_personal_info",
        "title": "Personal Information",
        "fields": [
          {
            "id": "fld_full_name",
            "key": "full_name",
            "type": "text",
            "label": "Full Name",
            "placeholder": "Enter your full name",
            "required": true,
            "col_span": 6,
            "row_span": 1,
            "valign": "center"
          }
        ]
      }
    ]
  }
}
```

---

## 📑 Part C — Word & Excel Document Import Architecture (Hybrid Split)

FormCraft AI implements a **Hybrid Import Pipeline** (`DocumentParserService`) for Word (`.docx`) and Excel (`.xlsx` / `.csv`) documents:

```
 ┌─────────────────────────────────────────────────────────┐
 │ Uploaded Document File (.docx / .xlsx / .csv)           │
 └────────────────────────────┬────────────────────────────┘
                              │
                              ▼
 ┌─────────────────────────────────────────────────────────┐
 │ 1. Deterministic Extraction Phase (Sub-millisecond)    │
 │    • ZipArchive XML DOM extraction (word/document.xml) │
 │    • PhpSpreadsheet Header & Sample Row Matrix parsing │
 │    • Section Headings & Asterisk (*) detection        │
 └────────────────────────────┬────────────────────────────┘
                              │
                              ▼
 ┌─────────────────────────────────────────────────────────┐
 │ 2. AI & Pattern Inference Phase (Disambiguation)       │
 │    • Map ambiguous labels to control types             │
 │    • Infer dropdown/radio options & validation rules   │
 │    • Isolate embedded binary/OLE objects to Audit Log  │
 └────────────────────────────┬────────────────────────────┘
                              │
                              ▼
 ┌─────────────────────────────────────────────────────────┐
 │ 3. Interactive Schema Mapping Screen (Livewire UI)     │
 │    • Preview draft form & review unparseable blocks    │
 │    • One-click type overriding & field editing         │
 └─────────────────────────────────────────────────┘
```

| Pipeline Phase | Technique / Tools | Responsibilities | Why This Split? |
| :--- | :--- | :--- | :--- |
| **Phase 1: Deterministic Parsing** | `ZipArchive` (XML DOM), `PhpOffice\PhpSpreadsheet` | • Extracts exact document structure, paragraph text, and section headings.<br>• Extracts spreadsheet headers.<br>• Identifies required field indicators (`*`). | **Sub-10ms performance**, 0% LLM token cost, and 100% structural accuracy. |
| **Phase 2: Semantic AI Inference** | Regex pattern matching & LLM Disambiguation | • Disambiguates vague labels into specific field types.<br>• Detects options for dropdowns/radios.<br>• Synthesizes validation rules. | Uses AI only where semantic context is needed, keeping LLM token costs minimal. |
| **Phase 3: Unparseable Block Recovery** | Audit Log & Interactive Recovery Engine | • Detects embedded binary images or unreadable tables.<br>• Moves them to `unparseableBlocks` audit trail for one-click user recovery. | Prevents document data loss during import. |

---

## 🧪 Test Suite Results

```text
   PASS  Tests\Feature\FormBuilderTest
  ✓ can create form and save json schema                                 0.57s
  ✓ can submit public form and record submission                         0.01s
  ✓ csv export endpoint returns streamed response                        0.06s
  ✓ uploaded file is downloadable from submissions endpoint              0.04s
  ✓ ai form service generates valid schema                               9.62s
  ✓ document parser service extracts fields                              0.19s
  ✓ template library seeds four templates                                0.05s
  ✓ honeypot traps bot submissions                                       0.17s
  ✓ can reorder fields and sections in builder                           0.07s
  ✓ can toggle display mode wizard vs single page                        0.05s
  ✓ can move fields between sections                                     0.10s
  ✓ per field validation rules are enforced                              0.13s
  ✓ can update field configuration and save form                         0.14s
  ✓ grid span saves and persists on form edit                            0.10s

  Tests:    14 passed (36 assertions)
  Duration: 11.56s
```

---

## 📄 License

This software is open-sourced under the [MIT License](LICENSE).
