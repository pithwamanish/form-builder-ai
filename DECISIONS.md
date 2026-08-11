# 🧠 Architecture Decisions & Design Rationale (DECISIONS.md)

This document outlines key technical decisions, trade-offs, and architecture choices made during the development of the **FormCraft AI Builder Studio**.

---

## 1. Architectural Principles & Source of Truth

### **JSON Schema as Canonical Single Source of Truth**
- **Decision**: The visual canvas, Inspector drawer, raw JSON editor, and public form renderer all consume and update a single unified JSON schema stored in MySQL.
- **Rationale**: Storing form definitions as dynamic JSON schemas eliminates brittle database migrations for user-created fields.
- **Two-Way Synchronization**: Edits in the visual canvas instantly update the internal schema and raw JSON code view; edits in the raw JSON editor reflect back to the canvas.

### **Responsive 12-Column Grid vs. Freeform Canvas**
- **Decision**: Implemented a responsive 12-column fluid grid system as the default layout engine, with optional **Freeform Canvas Mode** (`layout_mode: freeform`) for desktop drag-and-resize.
- **Rationale**: Fixed pixel coordinates (`top: 150px, left: 300px`) break on mobile screens. The 12-column grid ensures native mobile responsiveness out-of-the-box.
- **Mobile Touch Handling**: Freeform canvas mode utilizes the `PointerEvents` API (`pointerdown`, `pointermove`, `pointerup`) with relative percentage wrappers so drag-and-resize works on mobile touchscreens without breaking layout flow.

---

## 2. AI / LLM Multi-Tier Architecture & Fallback Pipeline

### **Multi-Tier AI Service Architecture**
To meet the task requirement of supporting a Python microservice while ensuring zero-setup local execution:
1. **Tier 1 (FastAPI REST Service)**: Attempts to dispatch request to Python FastAPI microservice (`FASTAPI_AI_URL`).
2. **Tier 2 (Native Laravel REST Client)**: If FastAPI is unreachable, Laravel calls Mistral AI (`api.mistral.ai`) or OpenAI (`api.openai.com`) directly via `Http::withToken()`.
3. **Tier 3 (Local Mock Schema Generator)**: If no API key is set, generates valid contextual schemas locally so the app runs 100% offline out-of-the-box.

### **Queued Asynchronous Execution vs. Immediate Calls**
- **Decision**: Created `GenerateFormSchemaJob` (Laravel Queue) to process long LLM calls in the background.
- **User Experience**: The UI provides a live polling monitor (`wire:poll.1s`) displaying real-time status (`pending` ➔ `processing` ➔ `completed`), logging model name, prompt/completion token count, and latency in seconds.

---

## 3. Part D Engineering Choices (Product Quality Improvements)

Per Part D requirements, three impactful architectural features were designed and implemented:

### **Improvement 1: Invisible Honeypot Bot Trapping**
- **User Problem**: Public forms are vulnerable to spam bots submitting bad data. Standard CAPTCHAs add unnecessary user friction.
- **Implementation**: Added an invisible honeypot text input field. Real users cannot see it; automated bots populate it. Submissions with filled honeypots are silently trapped without database insertion.
- **Trade-offs Accepted**: Does not catch sophisticated human spam; accepted in favor of 0% user friction.

### **Improvement 2: Document Import Preview & Schema Mapping Screen**
- **User Problem**: Parsing `.docx` or `.xlsx` files into forms can lead to incorrect field type inferences.
- **Implementation**: Built a two-stage importer (`DocumentParserService`). Stage 1 extracts headings, labels, and question types and displays a **Preview & Mapping Screen**. Users review and edit field types before committing to the database.
- **Trade-offs Accepted**: Requires one extra user click before canvas edit; accepted because preview confirmation prevents corrupted schemas.

### **Improvement 3: Dynamic Multi-Step Wizard & Single Page Display Modes**
- **User Problem**: Long forms with 15+ fields overwhelm users on single pages.
- **Implementation**: Form definitions support `display_mode: wizard` (step-by-step navigation with section progress bar) and `display_mode: single_page`.
### **Improvement 4: Enterprise Multi-Tenant Data Isolation Scoping**
- **User Problem**: Enterprise teams require strict tenant data isolation so organizations cannot view or tamper with other organizations' forms or submission records.
- **Implementation**: Added indexed `tenant_id` columns across `forms`, `form_submissions`, `ai_generation_logs`, and `document_import_logs` tables. Applied a global Eloquent scope (`TenantScope`) and trait (`BelongsToTenant`) that automatically enforces `WHERE tenant_id = current_tenant` on all database operations at the ORM layer.
- **Trade-offs Accepted**: Requires tenant context resolution middleware; accepted for enterprise security and strict data isolation guarantees.

---

## 4. Future Engineering Roadmap (With 2 More Weeks)

If granted two additional weeks of development time:
1. **Conditional Logic Engine**: Implement field visibility dependencies (e.g. *"Show input X only if dropdown Y == 'Yes'"*).
2. **Form Analytics & Conversion Heatmaps**: Add per-field drop-off rates and completion time metrics for submitted forms.
3. **Webhooks & Third-Party Integrations**: Send public form submissions directly to Slack, Zapier, or HubSpot webhooks upon validation.
