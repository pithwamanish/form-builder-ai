<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card card-custom p-4 bg-white shadow-sm border-0">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-file-earmark-arrow-up fs-1"></i>
                    </div>
                    <h3 class="fw-bold">Import Form from Word or Excel</h3>
                    <p class="text-muted">Upload a <code>.docx</code> or <code>.xlsx</code> file to automatically parse headings, questions, and option lists into an editable form schema.</p>
                </div>

                @if(!$parsedSchema)
                    <form wire:submit.prevent="parse">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Document (.docx or .xlsx):</label>
                            <input type="file" class="form-control form-control-lg" wire:model="documentFile" accept=".docx,.doc,.xlsx,.xls,.csv">
                            @error('documentFile') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>

                        <!-- Sample Test Files Download Bar -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <span class="small fw-bold text-dark"><i class="bi bi-download me-1 text-primary"></i> Download Sample Files to Test Parser:</span>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="/samples/sample_application.docx" class="btn btn-sm btn-outline-primary fw-medium" download>
                                        <i class="bi bi-file-earmark-word me-1"></i> Word (.docx)
                                    </a>
                                    <a href="/samples/sample_onboarding.xlsx" class="btn btn-sm btn-outline-success fw-medium" download>
                                        <i class="bi bi-file-earmark-excel me-1"></i> Excel (.xlsx)
                                    </a>
                                    <a href="/samples/sample_survey.csv" class="btn btn-sm btn-outline-secondary fw-medium" download>
                                        <i class="bi bi-file-earmark-text me-1"></i> CSV (.csv)
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Queued Background Import Mode for Large Files -->
                        <div class="p-3 mb-4 bg-light rounded-3 border">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="queueImportMode" wire:model.live="useQueue">
                                <label class="form-check-label small fw-bold text-dark" for="queueImportMode">
                                    <i class="bi bi-clock-history me-1 text-primary"></i> Queue Large File in Background (Asynchronous Job Processing)
                                </label>
                                <div class="extra-small text-muted">Enable for large files (>5 MB) to avoid web request timeouts. You can inspect parsing status and unparseable blocks in the Audit Log below.</div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-indigo btn-lg py-3 fw-bold" wire:loading.attr="disabled" @if($isParsing) disabled @endif>
                                @if($isParsing)
                                    <span><i class="spinner-border spinner-border-sm me-2"></i> {{ $statusMessage }}</span>
                                @else
                                    <span><i class="bi bi-gear me-2"></i> {{ $useQueue ? 'Dispatch Queued Document Import' : 'Parse Uploaded File' }}</span>
                                @endif
                            </button>
                            <button type="button" class="btn btn-outline-secondary py-2 fw-bold" wire:click="loadDemoSample">
                                <i class="bi bi-file-earmark-text me-1 text-primary"></i> Try Demo Sample Document (Load Mapping & Preview Screen)
                            </button>
                        </div>
                    </form>

                    <!-- Live Polling for Active Queued Job -->
                    @if($isParsing && $useQueue)
                        <div class="mt-4 p-3 bg-indigo-subtle text-indigo rounded-3 border border-indigo-subtle" wire:poll.2s="checkQueueStatus">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold"><i class="bi bi-cpu me-1"></i> Background Queue Processing...</span>
                                <span class="badge bg-indigo text-white">IN PROGRESS</span>
                            </div>
                            <div class="small">{{ $statusMessage }}</div>
                        </div>
                    @endif

                    <!-- Dedicated Document Import Audit Logs & Queued Jobs Table -->
                    @if($importLogs && count($importLogs) > 0)
                        <div class="mt-5 pt-3 border-top">
                            <h5 class="fw-bold mb-3"><i class="bi bi-journal-text me-2 text-indigo"></i> Document Import Audit Logs & Queued Jobs</h5>
                            <div class="table-responsive bg-white rounded border">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light extra-small text-uppercase">
                                        <tr>
                                            <th>File Name</th>
                                            <th>File Size</th>
                                            <th>Status</th>
                                            <th>Uploaded At</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($importLogs as $iLog)
                                            <tr>
                                                <td class="fw-medium">
                                                    <i class="bi bi-file-earmark-code me-1 text-muted"></i> {{ $iLog->file_name }}
                                                </td>
                                                <td class="small text-muted">{{ round($iLog->file_size / 1024, 1) }} KB</td>
                                                <td>
                                                    @if($iLog->status === 'completed' || $iLog->status === 'review_required')
                                                        <span class="badge bg-success">Review & Fix Ready</span>
                                                    @elseif($iLog->status === 'processing')
                                                        <span class="badge bg-primary"><i class="spinner-border spinner-border-sm me-1"></i> Processing</span>
                                                    @elseif($iLog->status === 'pending')
                                                        <span class="badge bg-secondary">Queued</span>
                                                    @else
                                                        <span class="badge bg-danger">Failed</span>
                                                    @endif
                                                </td>
                                                <td class="small text-muted">{{ $iLog->created_at->diffForHumans() }}</td>
                                                <td class="text-end">
                                                    @if($iLog->parsed_schema)
                                                        <button class="btn btn-sm btn-outline-primary fw-bold" wire:click="loadImportLog({{ $iLog->id }})">
                                                            <i class="bi bi-eye me-1"></i> Review Mapping & Unparseable Blocks
                                                        </button>
                                                    @else
                                                        <span class="small text-muted">N/A</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                @else
                    <!-- Mapping & Preview Screen (Part C Requirement) -->
                    <div class="alert alert-info d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                        <div>
                            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                            <strong>Parsed Successfully!</strong> Review and fix any wrongly detected field types below before committing.
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary" wire:click="$set('parsedSchema', null)">
                                <i class="bi bi-arrow-left me-1"></i> Re-upload / Back to List
                            </button>
                            <button class="btn btn-success fw-bold" wire:click="confirmImport">
                                <i class="bi bi-check-lg me-1"></i> Confirm & Open in Builder
                            </button>
                        </div>
                    </div>

                    <!-- Unparseable Document Blocks Box -->
                    @if(!empty($unparseableBlocks))
                        <div class="card bg-warning-subtle border-warning p-3 mb-4">
                            <div class="fw-bold text-dark mb-2">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                Reported Unparseable / Unrecognized Document Blocks ({{ count($unparseableBlocks) }} Found):
                            </div>
                            <p class="small text-muted mb-2">The parser encountered binary data or ambiguous formatting that couldn't be automatically mapped. You can review them or click <strong>Convert to Field</strong>.</p>
                            <div class="list-group">
                                @foreach($unparseableBlocks as $bIdx => $block)
                                    <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <code class="text-dark fw-bold">{{ $block['raw_text'] }}</code>
                                            <div class="extra-small text-danger"><i class="bi bi-info-circle me-1"></i> {{ $block['reason'] }}</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" wire:click="addFieldFromUnparseable({{ $bIdx }})">
                                            <i class="bi bi-plus-circle me-1"></i> Convert to Editable Field
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="bg-light p-4 rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-indigo mb-0">{{ $parsedSchema['title'] ?? 'Parsed Form' }}</h5>
                            <span class="badge bg-indigo-subtle text-indigo fw-bold">Interactive Preview & Mapping Screen</span>
                        </div>
                        <p class="text-muted small mb-3">{{ $parsedSchema['description'] ?? '' }}</p>

                        <div class="table-responsive bg-white rounded border">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 35%;">Detected Label</th>
                                        <th style="width: 20%;">Generated Key</th>
                                        <th style="width: 25%;">Inferred Field Type</th>
                                        <th style="width: 10%;">Required</th>
                                        <th style="width: 10%; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($parsedSchema['sections'] ?? [] as $sIndex => $sec)
                                        <tr class="table-secondary">
                                            <td colspan="5" class="fw-bold text-uppercase small py-2">
                                                <i class="bi bi-folder2-open me-1"></i> {{ $sec['title'] }}
                                            </td>
                                        </tr>
                                        @foreach($sec['fields'] ?? [] as $fIndex => $f)
                                            <tr>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           value="{{ $f['label'] }}" 
                                                           wire:change="updateFieldLabel({{ $sIndex }}, {{ $fIndex }}, $event.target.value)">
                                                </td>
                                                <td><code>{{ $f['key'] }}</code></td>
                                                <td>
                                                    <select class="form-select form-select-sm" 
                                                            wire:change="updateFieldType({{ $sIndex }}, {{ $fIndex }}, $event.target.value)">
                                                        <option value="text" @selected($f['type'] === 'text')>Short Text (Input)</option>
                                                        <option value="textarea" @selected($f['type'] === 'textarea')>Long Text (Textarea)</option>
                                                        <option value="number" @selected($f['type'] === 'number')>Number</option>
                                                        <option value="email" @selected($f['type'] === 'email')>Email Address</option>
                                                        <option value="phone" @selected($f['type'] === 'phone')>Phone Number</option>
                                                        <option value="date" @selected($f['type'] === 'date')>Date Picker</option>
                                                        <option value="dropdown" @selected($f['type'] === 'dropdown')>Dropdown Select</option>
                                                        <option value="radio" @selected($f['type'] === 'radio')>Radio Options</option>
                                                        <option value="checkbox" @selected($f['type'] === 'checkbox')>Checkbox Group</option>
                                                        <option value="file" @selected($f['type'] === 'file')>File Upload</option>
                                                        <option value="heading" @selected($f['type'] === 'heading')>Section Heading</option>
                                                        <option value="rating" @selected($f['type'] === 'rating')>Star Rating</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               @checked($f['required'] ?? false) 
                                                               wire:click="toggleRequired({{ $sIndex }}, {{ $fIndex }})">
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-danger border-0" 
                                                            wire:click="removeField({{ $sIndex }}, {{ $fIndex }})" 
                                                            title="Remove field">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
