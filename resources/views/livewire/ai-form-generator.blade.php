<div class="container py-5" x-data="{
    execMode: 'sync',
    isStreaming: false,
    streamText: '',
    async startStream() {
        const promptText = document.getElementById('promptTextarea').value;
        if (!promptText || promptText.trim().length < 5) {
            alert('Please enter a prompt instruction with at least 5 characters.');
            return;
        }
        this.isStreaming = true;
        this.streamText = '⚡ [SSE STREAM CONNECTED] Real-time token streaming initiated...\n\n';

        let fullJsonContent = '';
        try {
            const response = await fetch('/api/ai/stream-generate?prompt=' + encodeURIComponent(promptText));

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let streamBuffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                const chunkText = decoder.decode(value, { stream: true });
                this.streamText += chunkText;

                streamBuffer += chunkText;
                const lines = streamBuffer.split('\n');
                // Keep incomplete trailing line fragment in streamBuffer
                streamBuffer = lines.pop();

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed.startsWith('data: ') && !trimmed.includes('[DONE]')) {
                        try {
                            const jsonStr = trimmed.substring(6).trim();
                            const parsed = JSON.parse(jsonStr);
                            const contentToken = parsed.choices?.[0]?.delta?.content ?? parsed.chunk ?? '';
                            fullJsonContent += contentToken;
                        } catch (e) {}
                    }
                }

                if (this.$refs.streamTerminal) {
                    this.$refs.streamTerminal.scrollTop = this.$refs.streamTerminal.scrollHeight;
                }
            }

            // Process any remaining tail line in streamBuffer
            if (streamBuffer.trim().startsWith('data: ') && !streamBuffer.includes('[DONE]')) {
                try {
                    const jsonStr = streamBuffer.trim().substring(6).trim();
                    const parsed = JSON.parse(jsonStr);
                    const contentToken = parsed.choices?.[0]?.delta?.content ?? parsed.chunk ?? '';
                    fullJsonContent += contentToken;
                } catch (e) {}
            }

            this.streamText += '\n\n✅ [SSE STREAM COMPLETED] Parsing schema & redirecting to Visual Builder...';

            if (fullJsonContent.trim()) {
                let schemaPayload = window.repairJsonInJs(fullJsonContent);

                let formUuid = await $wire.saveStreamedFormSchema(schemaPayload);
                if (formUuid) {
                    window.location.href = '/builder/' + formUuid;
                }
            }
        } catch (err) {
            this.streamText += '\n\n❌ [STREAM ERROR]: ' + err.message;
        } finally {
            this.isStreaming = false;
        }
    }
}">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card card-custom p-4 bg-white shadow-sm border-0">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-purple-subtle text-purple mb-3">
                        <i class="bi bi-magic fs-1" style="color: #9333ea;"></i>
                    </div>
                    <h3 class="fw-bold text-dark">AI Natural Language Form Generator</h3>
                    <p class="text-muted">Describe the form you need in plain text, and AI will construct a complete schema with field validation rules.</p>
                </div>

                <form wire:submit.prevent="generate">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Prompt Instruction:</label>
                        <textarea id="promptTextarea" class="form-control form-control-lg p-3 fs-6" rows="4" wire:model="prompt" placeholder="e.g. Create a software engineer job application form with personal details, experience level, primary technical skills checkbox, and resume PDF upload..."></textarea>
                        @error('prompt') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <!-- Execution Mode Selection: Immediate vs Queued Job vs Stream -->
                    <div class="p-3 mb-4 bg-light rounded-3 border">
                        <div class="fw-bold text-dark mb-2 small"><i class="bi bi-cpu me-1"></i> Choose LLM Execution Pipeline:</div>
                        <div class="d-flex gap-4 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="execModeRadio" id="execSync" value="sync" x-model="execMode" @change="$wire.set('useQueue', false)">
                                <label class="form-check-label small" for="execSync">
                                    <strong>Immediate Response</strong> (Synchronous call)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="execModeRadio" id="execQueue" value="queue" x-model="execMode" @change="$wire.set('useQueue', true)">
                                <label class="form-check-label small" for="execQueue">
                                    <strong>Queued Job Dispatch</strong> (Asynchronous background worker)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="execModeRadio" id="execStream" value="stream" x-model="execMode">
                                <label class="form-check-label small" for="execStream">
                                    <strong>Real-Time SSE Token Stream</strong> (Live token streaming) ⚡
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <template x-if="execMode !== 'stream'">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-indigo btn-lg py-3 fw-bold" wire:loading.attr="disabled" @if($isGenerating) disabled @endif>
                                @if($isGenerating)
                                    <span><i class="spinner-border spinner-border-sm me-2"></i> {{ $statusMessage }}</span>
                                @else
                                    <span><i class="bi bi-cpu me-2"></i> {{ $useQueue ? 'Dispatch Queued AI Job' : 'Generate Form with AI' }}</span>
                                @endif
                            </button>
                        </div>
                    </template>

                    <template x-if="execMode === 'stream'">
                        <div class="d-grid">
                            <button type="button" @click="startStream()" class="btn btn-purple btn-lg py-3 fw-bold" style="background-color: #9333ea; color: white;" :disabled="isStreaming">
                                <span x-show="!isStreaming"><i class="bi bi-broadcast me-2"></i> Start Real-Time SSE Token Stream</span>
                                <span x-show="isStreaming"><i class="spinner-border spinner-border-sm me-2"></i> Streaming LLM Tokens in Real Time...</span>
                            </button>
                        </div>
                    </template>
                </form>

                <!-- Real-Time SSE Token Terminal Output -->
                <div class="mt-4" x-show="execMode === 'stream' && streamText.length > 0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark"><i class="bi bi-terminal-fill text-purple me-1"></i> Real-Time Stream Terminal Output</span>
                        <span class="badge bg-dark text-white fw-mono">SSE Stream</span>
                    </div>
                    <pre x-ref="streamTerminal" class="p-3 bg-dark text-green rounded-3 shadow-inner" style="color: #4ade80; font-family: monospace; font-size: 0.85rem; max-height: 350px; overflow-y: auto; white-space: pre-wrap;" x-text="streamText"></pre>
                </div>

                <!-- Live Polling & Queue Log Monitor -->
                @if($isGenerating && $useQueue)
                    <div class="mt-4 p-3 bg-indigo-subtle text-indigo rounded-3 border border-indigo-subtle" wire:poll.1s="checkQueueStatus">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold"><i class="bi bi-clock-history me-1"></i> Queued AI Job Monitoring</span>
                            <span class="badge bg-indigo text-white text-uppercase">{{ $logDetails['status'] ?? 'pending' }}</span>
                        </div>
                        <div class="small mb-2">{{ $statusMessage }}</div>

                        @if($logDetails)
                            <div class="row g-2 mt-2 pt-2 border-top border-indigo-subtle extra-small text-muted">
                                <div class="col-md-3"><strong>Model:</strong> {{ $logDetails['model'] ?? 'N/A' }}</div>
                                <div class="col-md-3"><strong>Prompt Tokens:</strong> {{ $logDetails['prompt_tokens'] ?? 0 }}</div>
                                <div class="col-md-3"><strong>Completion Tokens:</strong> {{ $logDetails['completion_tokens'] ?? 0 }}</div>
                                <div class="col-md-3"><strong>Total Tokens:</strong> {{ $logDetails['total_tokens'] ?? 0 }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-4 p-3 bg-light rounded-3 small">
                    <div class="fw-bold text-dark mb-1"><i class="bi bi-lightbulb text-warning me-1"></i> Sample Prompts to Try:</div>
                    <ul class="mb-0 text-muted ps-3">
                        <li>"Internship application form with college name, major, skills checkbox and resume upload"</li>
                        <li>"Event registration with ticket type dropdown, dietary preferences, and session track radio"</li>
                        <li>"Patient intake form with medical history, emergency contact phone, and consent checkbox"</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.repairJsonInJs = function(str) {
    if (!str || typeof str !== 'string') return null;
    let s = str.replace(/^```json\s*/i, '').replace(/```$/g, '').trim();
    let first = s.indexOf('{');
    let last = s.lastIndexOf('}');
    if (first !== -1 && last !== -1) {
        s = s.substring(first, last + 1);
    }
    
    // 1. Sanitize raw control characters (newlines, tabs) inside JSON string literals
    s = s.replace(/("(?:[^"\\]|\\.)*")/g, function(m) {
        return m.replace(/\r?\n/g, "\\n").replace(/\t/g, "\\t");
    });
    
    // 2. Remove trailing commas before closing braces/brackets
    s = s.replace(/,\s*([\}\]])/g, '$1');
    
    // 3. Convert unescaped inner double quotes in property values to single quotes
    s = s.replace(/(:\s*")([\s\S]*?)("(?=\s*(?:,|\r?\n|\})))/g, function(match, p1, p2, p3) {
        let cleanVal = p2.replace(/\\"/g, '___ESC___').replace(/"/g, "'").replace(/___ESC___/g, '\\"');
        return p1 + cleanVal + p3;
    });

    // 4. Attempt direct parse
    try {
        return JSON.parse(s);
    } catch (e) {}

    // 5. Fallback defensive structure if JSON parsing fails
    return {
        title: 'Streamed AI Form',
        description: 'Auto-repaired AI Form Schema',
        sections: [
            {
                id: 'sec_1',
                title: 'General Information',
                fields: [
                    {
                        id: 'fld_1',
                        key: 'full_name',
                        type: 'text',
                        label: 'Full Name',
                        placeholder: 'John Doe',
                        required: true,
                        col_span: 6
                    }
                ]
            }
        ]
    };
};
</script>
