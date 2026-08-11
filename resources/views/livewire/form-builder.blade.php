<div class="container-fluid px-4">
    @if ($successMessage || session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show card-custom mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ $successMessage ?? session('message') }}
            <button type="button" class="btn-close" wire:click="$set('successMessage', null)" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show card-custom mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Header Actions Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <input type="text" class="form-control form-control-lg fw-bold border-0 bg-transparent px-0 fs-3" wire:model.live.debounce.300ms="title" placeholder="Form Title...">
            <input type="text" class="form-control border-0 bg-transparent px-0 text-muted" wire:model.live.debounce.300ms="description" placeholder="Add form description or instructions...">
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('forms.create') }}" class="btn btn-outline-success" title="Create a brand new blank form">
                <i class="bi bi-plus-lg me-1"></i> New Form
            </a>
            <button class="btn btn-outline-secondary" wire:click="$set('activeTab', 'templates')" data-bs-toggle="modal" data-bs-target="#templatesModal">
                <i class="bi bi-collection me-1"></i> Templates
            </button>

            @if($form)
                <a href="{{ route('forms.public', ['slug' => $form->slug]) }}" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> View Public Form
                </a>
                <a href="{{ route('forms.submissions', ['uuid' => $form->uuid]) }}" class="btn btn-outline-info">
                    <i class="bi bi-table me-1"></i> Submissions ({{ $form->submissions()->count() }})
                </a>
                <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#shareModal">
                    <i class="bi bi-qr-code me-1"></i> Share / Embed
                </button>
            @endif

            <button class="btn btn-indigo px-4 shadow-sm" wire:click="saveForm" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveForm"><i class="bi bi-floppy me-1"></i> Save Form</span>
                <span wire:loading wire:target="saveForm"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...</span>
            </button>
        </div>
    </div>

    <!-- Mode Switcher Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 card-custom border gap-2">
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'visual' ? 'active bg-indigo' : 'text-dark' }}" wire:click="$set('activeTab', 'visual')">
                <i class="bi bi-eye me-1"></i> Visual Canvas
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'json' ? 'active bg-indigo' : 'text-dark' }}" wire:click="$set('activeTab', 'json')">
                <i class="bi bi-code-slash me-1"></i> Raw JSON Schema (2-Way Sync)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'ai' ? 'active bg-purple text-white' : 'text-dark' }}" style="background-color: {{ $activeTab === 'ai' ? '#9333ea' : 'transparent' }}" wire:click="$set('activeTab', 'ai')">
                <i class="bi bi-magic me-1"></i> AI Form Modifier
            </button>
        </li>
    </ul>

    <div class="row g-4">
        @if($activeTab === 'visual')
            <!-- Sidebar: Field Types Palette -->
            <div class="col-lg-3">
                <div class="card card-custom p-3 bg-white mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase mb-1">Target Section</label>
                    <select class="form-select mb-3" wire:model.live="selectedSectionId">
                        @foreach($schema['sections'] ?? [] as $sec)
                            <option value="{{ $sec['id'] }}">{{ $sec['title'] ?: 'Untitled Section' }}</option>
                        @endforeach
                    </select>

                    <h6 class="fw-bold mb-3 text-uppercase text-muted small"><i class="bi bi-plus-circle me-1"></i> Click to Add Field</h6>
                    <div class="d-grid gap-2">
                        @php
                            $fieldTypes = [
                                ['type' => 'text', 'label' => 'Text Input', 'icon' => 'bi-input-cursor-text'],
                                ['type' => 'textarea', 'label' => 'Long Textarea', 'icon' => 'bi-textarea-t'],
                                ['type' => 'number', 'label' => 'Number Input', 'icon' => 'bi-123'],
                                ['type' => 'email', 'label' => 'Email Address', 'icon' => 'bi-envelope'],
                                ['type' => 'phone', 'label' => 'Phone Number', 'icon' => 'bi-telephone'],
                                ['type' => 'date', 'label' => 'Date Picker', 'icon' => 'bi-calendar-date'],
                                ['type' => 'dropdown', 'label' => 'Dropdown Select', 'icon' => 'bi-caret-down-square'],
                                ['type' => 'radio', 'label' => 'Radio List', 'icon' => 'bi-ui-radios'],
                                ['type' => 'checkbox', 'label' => 'Checkbox Group', 'icon' => 'bi-ui-checks'],
                                ['type' => 'file', 'label' => 'File Upload', 'icon' => 'bi-upload'],
                                ['type' => 'rating', 'label' => 'Star Rating', 'icon' => 'bi-star-half'],
                            ];
                        @endphp

                        @foreach($fieldTypes as $ft)
                            <button class="btn btn-light text-start border-0 py-2 d-flex align-items-center gap-2 hover-bg shadow-sm" 
                                    draggable="true" 
                                    ondragstart="event.dataTransfer.setData('fieldType', '{{ $ft['type'] }}')" 
                                    wire:click="addField('{{ $selectedSectionId }}', '{{ $ft['type'] }}')"
                                    title="Click or Drag onto canvas to add">
                                <i class="bi bi-grip-vertical text-muted"></i>
                                <i class="bi {{ $ft['icon'] }} text-indigo fs-5"></i>
                                <span>{{ $ft['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Canvas Column -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 bg-white p-3 rounded-3 shadow-sm border">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <h5 class="fw-bold mb-0">Visual Canvas</h5>
                        
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn {{ ($schema['settings']['display_mode'] ?? 'wizard') === 'wizard' ? 'btn-indigo fw-bold' : 'btn-outline-secondary' }}" wire:click="setDisplayMode('wizard')" title="Multi-Step Wizard mode (sections step-by-step with progress bar)">
                                <i class="bi bi-diagram-3 me-1"></i> Multi-Step Wizard
                            </button>
                            <button type="button" class="btn {{ ($schema['settings']['display_mode'] ?? 'wizard') === 'single_page' ? 'btn-indigo fw-bold' : 'btn-outline-secondary' }}" wire:click="setDisplayMode('single_page')" title="Single Page mode (all sections on one page)">
                                <i class="bi bi-file-earmark-text me-1"></i> Single Page
                            </button>
                        </div>

                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn {{ ($schema['settings']['layout_mode'] ?? 'grid') === 'grid' ? 'btn-purple fw-bold text-white' : 'btn-outline-purple' }}" wire:click="setLayoutMode('grid')" title="12-Column Responsive Grid Layout">
                                <i class="bi bi-grid-3x3-gap me-1"></i> 12-Col Grid
                            </button>
                            <button type="button" class="btn {{ ($schema['settings']['layout_mode'] ?? 'grid') === 'freeform' ? 'btn-purple fw-bold text-white' : 'btn-outline-purple' }}" wire:click="setLayoutMode('freeform')" title="Freeform Absolute Canvas with Mobile Touch & Corner Resizing">
                                <i class="bi bi-aspect-ratio me-1"></i> Freeform Canvas
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" wire:click="addSection">
                        <i class="bi bi-plus-lg me-1"></i> Add New Section
                    </button>
                </div>

                @foreach($schema['sections'] ?? [] as $sIndex => $sec)
                    <div class="card card-custom p-4 bg-white mb-4 shadow-sm border-top border-4 border-indigo"
                         wire:key="sec-card-{{ $sec['id'] ?? $sIndex }}"
                         style="{{ ($schema['settings']['layout_mode'] ?? 'grid') === 'freeform' ? 'background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px); background-size: 20px 20px; background-color: #f8fafc;' : '' }}"
                         ondragover="event.preventDefault(); this.style.borderColor='#4f46e5';"
                         ondragleave="this.style.borderColor='';"
                         ondrop="event.preventDefault(); this.style.borderColor=''; const dragType = event.dataTransfer.getData('dragType'); const sIdx = event.dataTransfer.getData('secIndex'); const t = event.dataTransfer.getData('fieldType'); if(dragType === 'section' && sIdx !== '') { @this.call('moveSection', parseInt(sIdx), {{ $sIndex }}); } else if(t) { @this.call('addField', '{{ $sec['id'] }}', t); }">
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 flex-wrap gap-2">
                            <div class="flex-grow-1 d-flex align-items-center gap-2">
                                <i class="bi bi-grip-vertical text-muted fs-4" 
                                   style="cursor: grab;" 
                                   title="Drag section to reorder"
                                   draggable="true"
                                   ondragstart="event.stopPropagation(); event.dataTransfer.setData('dragType', 'section'); event.dataTransfer.setData('secIndex', '{{ $sIndex }}');"></i>
                                <input type="text" class="form-control form-control-lg fw-bold border-0 bg-transparent px-0 text-indigo fs-4" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.title" placeholder="Section Header Title...">
                            </div>

                            <div class="d-flex align-items-center gap-2" wire:key="sec-actions-{{ $sec['id'] ?? $sIndex }}">
                                @if(($schema['settings']['layout_mode'] ?? 'grid') === 'freeform')
                                    <span class="badge bg-purple-subtle text-purple border border-purple-subtle"><i class="bi bi-aspect-ratio me-1"></i> Freeform Canvas Blueprint</span>
                                @endif
                                @if($sIndex > 0)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveSection({{ $sIndex }}, {{ $sIndex - 1 }})" title="Move Section Up">
                                        <i class="bi bi-arrow-up"></i>
                                    </button>
                                @endif
                                @if($sIndex < count($schema['sections']) - 1)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveSection({{ $sIndex }}, {{ $sIndex + 1 }})" title="Move Section Down">
                                        <i class="bi bi-arrow-down"></i>
                                    </button>
                                @endif

                                <div class="dropdown">
                                    <button class="btn btn-sm btn-indigo dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-plus-circle me-1"></i> Add Field to Section
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        @foreach($fieldTypes as $ft)
                                            <li>
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2" wire:click="addField('{{ $sec['id'] }}', '{{ $ft['type'] }}')">
                                                    <i class="bi {{ $ft['icon'] }} text-indigo"></i>
                                                    <span>{{ $ft['label'] }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                @if(count($schema['sections']) > 1)
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeSection('{{ $sec['id'] }}')" title="Delete Section">
                                        <i class="bi bi-trash"></i> Delete Section
                                    </button>
                                @endif
                            </div>
                        </div>

                        @php $isFreeform = ($schema['settings']['layout_mode'] ?? 'grid') === 'freeform'; @endphp
                        @if($isFreeform)
                            <div class="d-grid gap-3" style="grid-template-columns: repeat(12, 1fr); grid-auto-rows: minmax(100px, auto); grid-auto-flow: dense;">
                        @else
                            <div class="row g-3">
                        @endif
                            @forelse($sec['fields'] ?? [] as $fIndex => $f)
                                @php
                                    $colSpan = min(12, max(1, $f['col_span'] ?? 12));
                                    $rowSpan = min(6, max(1, $f['row_span'] ?? 1));
                                @endphp
                                <div class="{{ $isFreeform ? '' : 'col-12 col-md-' . $colSpan }}"
                                     style="{{ $isFreeform ? 'grid-column: span ' . $colSpan . '; grid-row: span ' . $rowSpan . ';' : '' }}"
                                     wire:key="fld-card-{{ $sec['id'] }}-{{ $f['id'] }}"
                                     draggable="true"
                                     ondragstart="event.stopPropagation(); event.dataTransfer.setData('fieldSrcIndex', '{{ $fIndex }}'); event.dataTransfer.setData('secId', '{{ $sec['id'] }}');"
                                     ondragover="event.preventDefault(); event.stopPropagation();"
                                     ondrop="event.stopPropagation(); event.preventDefault(); const srcIdx = event.dataTransfer.getData('fieldSrcIndex'); const srcSecId = event.dataTransfer.getData('secId'); if(srcIdx !== '') { @this.call('moveField', srcSecId, parseInt(srcIdx), {{ $fIndex }}, '{{ $sec['id'] }}'); }">
                                    <div class="card p-3 border border-light-subtle rounded-3 bg-white hover-shadow h-100 shadow-sm position-relative d-flex flex-column justify-content-between"
                                         style="{{ $isFreeform ? 'border: 1.5px dashed #9333ea !important; background-color: #faf5ff !important;' : '' }}">
                                        <!-- Header Bar with Quick Summary & Action Buttons -->
                                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 pb-2 border-bottom">
                                            <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
                                                <i class="bi bi-grip-vertical text-muted fs-5" style="cursor: grab;" title="Drag field within or between sections"></i>
                                                <span class="badge bg-indigo-subtle text-indigo border border-indigo-subtle text-uppercase fw-bold">{{ $f['type'] }}</span>
                                                <span class="fw-bold fs-6 text-dark">{{ ($f['label'] ?? null) ?: 'Untitled Field' }}</span>

                                                @if($f['required'] ?? false)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size: 0.7rem;">Required</span>
                                                @endif
                                                
                                                <!-- Spatial Row Position & Inline Width / Height Resizer -->
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-light border py-0 px-2 text-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size: 0.75rem;">
                                                        <i class="bi bi-arrows-angle-expand me-1"></i> {{ $isFreeform ? ('Freeform 2D: ' . $colSpan . '/12 × ' . $rowSpan . ' Row') : ('Width: ' . $colSpan . '/12') }}
                                                    </button>
                                                    <ul class="dropdown-menu shadow-sm" style="font-size: 0.8rem;">
                                                        <li><h6 class="dropdown-header">Horizontal Grid Width (Col Span)</h6></li>
                                                        <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'col_span', 3)">25% Width (Span 3)</button></li>
                                                        <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'col_span', 4)">33% Width (Span 4)</button></li>
                                                        <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'col_span', 6)">50% Width (Span 6)</button></li>
                                                        <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'col_span', 8)">66% Width (Span 8)</button></li>
                                                        <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'col_span', 12)">100% Width (Span 12)</button></li>
                                                        
                                                        @if($isFreeform)
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><h6 class="dropdown-header text-purple"><i class="bi bi-aspect-ratio me-1"></i>Freeform Vertical Row Span (Height)</h6></li>
                                                            <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'row_span', 1)">1 Row Standard Height</button></li>
                                                            <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'row_span', 2)">2 Rows Medium Height</button></li>
                                                            <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'row_span', 3)">3 Rows Tall Height</button></li>
                                                            <li><button class="dropdown-item" type="button" wire:click="updateFieldProp('{{ $f['id'] }}', 'row_span', 4)">4 Rows Hero Block</button></li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-1">
                                                <!-- Progressive Config Drawer Toggle Button -->
                                                <button type="button" 
                                                        class="btn btn-sm {{ $activeFieldConfigId === $f['id'] ? 'btn-indigo' : 'btn-outline-indigo' }}" 
                                                        wire:click="toggleFieldConfig('{{ $f['id'] }}')"
                                                        title="Toggle field configuration panel">
                                                    <i class="bi me-1 {{ $activeFieldConfigId === $f['id'] ? 'bi-x-lg' : 'bi-gear-fill' }}"></i>
                                                    <span>{{ $activeFieldConfigId === $f['id'] ? 'Close Config' : 'Configure' }}</span>
                                                </button>

                                                @if(count($schema['sections']) > 1)
                                                    @php $currentSecId = $sec['id'] ?? ('sec_' . $sIndex); @endphp
                                                    <select class="form-select form-select-sm text-indigo border-indigo-subtle py-0 px-1" 
                                                            style="font-size: 0.75rem; height: 30px; width: auto;" 
                                                            onchange="if(this.value) { @this.call('moveFieldToSection', '{{ $currentSecId }}', {{ $fIndex }}, this.value); this.value=''; }">
                                                        <option value="">Move...</option>
                                                        @foreach($schema['sections'] as $tIndex => $targetSec)
                                                            @php $targetSecId = $targetSec['id'] ?? ('sec_' . $tIndex); @endphp
                                                            @if((string)$targetSecId !== (string)$currentSecId)
                                                                <option value="{{ $targetSecId }}">→ {{ $targetSec['title'] ?: ('Section '.($tIndex+1)) }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                @endif

                                                @if($fIndex > 0)
                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" wire:click="moveField('{{ $sec['id'] }}', {{ $fIndex }}, {{ $fIndex - 1 }})" title="Move Up">
                                                        <i class="bi bi-arrow-up"></i>
                                                    </button>
                                                @endif
                                                @if($fIndex < count($sec['fields']) - 1)
                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" wire:click="moveField('{{ $sec['id'] }}', {{ $fIndex }}, {{ $fIndex + 1 }})" title="Move Down">
                                                        <i class="bi bi-arrow-down"></i>
                                                    </button>
                                                @endif

                                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" wire:click="duplicateField('{{ $sec['id'] }}', '{{ $f['id'] }}')" title="Duplicate Field">
                                                    <i class="bi bi-copy"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" wire:click="removeField('{{ $sec['id'] }}', '{{ $f['id'] }}')" title="Delete Field">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>

                                        @php
                                            $vAlign = $f['valign'] ?? 'center';
                                            $vClass = match($vAlign) {
                                                'top' => 'justify-content-start',
                                                'bottom' => 'justify-content-end',
                                                'stretch' => 'justify-content-between',
                                                default => 'justify-content-center',
                                            };
                                        @endphp
                                        <!-- Clean Canvas Live Preview (Always visible) -->
                                        <div class="my-2 text-{{ $f['align'] ?? 'left' }} flex-grow-1 d-flex flex-column {{ $vClass }}">
                                            @if($f['type'] === 'textarea')
                                                <textarea class="form-control form-control-sm flex-grow-1" rows="{{ max(3, $rowSpan * 3) }}" disabled style="resize: vertical;" placeholder="{{ $f['placeholder'] ?? 'Expandable Textarea control...' }}"></textarea>
                                            @elseif($f['type'] === 'dropdown')
                                                <select class="form-select form-select-sm" disabled {{ ($f['multiple'] ?? false) ? 'multiple' : '' }}>
                                                    @foreach($f['options'] ?? ['Option 1', 'Option 2'] as $opt)
                                                        <option>{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($f['type'] === 'radio')
                                                <div class="d-flex gap-3 flex-wrap justify-content-{{ ($f['align'] ?? 'left') === 'center' ? 'center' : (($f['align'] ?? 'left') === 'right' ? 'end' : 'start') }}">
                                                    @foreach($f['options'] ?? ['Option 1', 'Option 2'] as $opt)
                                                        <div class="form-check form-check-inline mb-0">
                                                            <input class="form-check-input" type="radio" disabled name="prev_r_{{ $f['id'] }}">
                                                            <label class="form-check-label small text-muted">{{ $opt }}</label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif($f['type'] === 'checkbox')
                                                @if(isset($f['options']) && is_array($f['options']) && count($f['options']) > 0)
                                                    <div class="d-flex gap-3 flex-wrap justify-content-{{ ($f['align'] ?? 'left') === 'center' ? 'center' : (($f['align'] ?? 'left') === 'right' ? 'end' : 'start') }}">
                                                        @foreach($f['options'] as $opt)
                                                            <div class="form-check form-check-inline mb-0">
                                                                <input class="form-check-input" type="checkbox" disabled>
                                                                <label class="form-check-label small text-muted">{{ $opt }}</label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" disabled checked>
                                                        <label class="form-check-label small text-muted fw-bold">{{ $f['label'] ?? 'Untitled Field' }}</label>
                                                    </div>
                                                @endif
                                            @elseif($f['type'] === 'heading')
                                                <h5 class="fw-bold text-dark border-bottom pb-1 my-1 text-{{ $f['align'] ?? 'left' }}">{{ $f['label'] ?? 'Untitled Heading' }}</h5>
                                            @elseif($f['type'] === 'rating')
                                                <div class="d-flex gap-2 justify-content-{{ ($f['align'] ?? 'left') === 'center' ? 'center' : (($f['align'] ?? 'left') === 'right' ? 'end' : 'start') }}">
                                                    @for($st = 1; $st <= 5; $st++)
                                                        <div class="form-check form-check-inline mb-0">
                                                            <input class="form-check-input" type="radio" disabled name="prev_star_{{ $f['id'] }}">
                                                            <label class="form-check-label small text-muted">{{ $st }} ★</label>
                                                        </div>
                                                    @endfor
                                                </div>
                                            @elseif($f['type'] === 'file')
                                                <input type="file" disabled class="form-control form-control-sm">
                                            @elseif($f['type'] === 'phone')
                                                <input type="tel" disabled class="form-control form-control-sm" placeholder="{{ $f['placeholder'] ?? 'Numeric phone digits only (+1 555-0199)' }}">
                                            @else
                                                <input type="text" disabled class="form-control form-control-sm text-{{ $f['align'] ?? 'left' }}" placeholder="{{ $f['placeholder'] ?? 'Input preview...' }}">
                                            @endif

                                            @if($f['help_text'] ?? false)
                                                <div class="form-text small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>{{ $f['help_text'] }}</div>
                                            @endif
                                        </div>

                                        @if($isFreeform)
                                            <!-- Interactive Diagonal Corner Resizer & 2D Spatial Control Handle -->
                                            <div class="position-absolute bottom-0 end-0 p-1 bg-white border rounded-top-start shadow-sm d-flex align-items-center gap-1"
                                                 style="border-color: #9333ea !important; z-index: 10;"
                                                 title="Freeform 2D Spatial Resizer">
                                                <span class="badge bg-purple text-white px-2 py-1" style="font-size: 0.65rem;" title="2D Spatial Grid: {{ $colSpan }} Columns Wide × {{ $rowSpan }} Rows Tall">
                                                    📐 {{ $colSpan }}×{{ $rowSpan }}
                                                </span>
                                                <!-- Diagonal 2D Resize Toggle (Expands both Width & Height simultaneously) -->
                                                <button type="button" 
                                                        class="btn btn-xs btn-purple text-white px-1 py-0 border-0" 
                                                        wire:click="resizeField2D('{{ $f['id'] }}', {{ $colSpan >= 12 ? 4 : $colSpan + 2 }}, {{ ($rowSpan % 4) + 1 }})" 
                                                        title="Diagonal 2D Scale (Expand Width + Height)">
                                                    <i class="bi bi-arrows-angle-expand fw-bold"></i>
                                                </button>
                                                <!-- Horizontal Width Cycle -->
                                                <button type="button" 
                                                        class="btn btn-xs btn-outline-purple px-1 py-0 border-0" 
                                                        wire:click="updateFieldProp('{{ $f['id'] }}', 'col_span', {{ $colSpan >= 12 ? 3 : ($colSpan === 3 ? 4 : ($colSpan === 4 ? 6 : ($colSpan === 6 ? 8 : 12))) }})" 
                                                        title="Cycle Grid Width (Col Span)">
                                                    <i class="bi bi-arrows-expand text-purple"></i>
                                                </button>
                                                <!-- Vertical Height Cycle -->
                                                <button type="button" 
                                                        class="btn btn-xs btn-outline-purple px-1 py-0 border-0" 
                                                        wire:click="updateFieldProp('{{ $f['id'] }}', 'row_span', {{ ($rowSpan % 4) + 1 }})" 
                                                        title="Cycle Row Height (Row Span)">
                                                    <i class="bi bi-arrows-vertical text-purple"></i>
                                                </button>
                                            </div>
                                        @endif

                                        <!-- Progressive Field Configuration Inspector Drawer (On-Demand) -->
                                        @if($activeFieldConfigId === $f['id'])
                                            <div class="mt-3 p-3 bg-light rounded border border-indigo-subtle shadow-sm" wire:key="fld-cfg-{{ $f['id'] }}">
                                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                                    <span class="fw-bold text-indigo small"><i class="bi bi-sliders me-1"></i> Field Configuration Inspector</span>
                                                    <button type="button" class="btn-close btn-sm" wire:click="$set('activeFieldConfigId', null)" aria-label="Close"></button>
                                                </div>

                                                <div class="row g-2">
                                                <!-- Field Label & Unique Key -->
                                                <div class="col-md-7">
                                                    <label class="form-label small fw-bold text-muted mb-0">Field Label:</label>
                                                    <input type="text" class="form-control form-control-sm fw-bold border-indigo-subtle" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.label" placeholder="Field Label...">
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label small fw-bold text-muted mb-0">Field Key (JSON Key):</label>
                                                    <input type="text" class="form-control form-control-sm border-secondary-subtle" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.key" placeholder="field_key">
                                                </div>

                                                <!-- Placeholder, Help Text & Default Value (Filtered by Field Type) -->
                                                @if(in_array($f['type'], ['text', 'textarea', 'email', 'url', 'number', 'phone', 'date', 'time']))
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold text-muted mb-0">Placeholder:</label>
                                                        <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.placeholder" placeholder="Placeholder text...">
                                                    </div>
                                                @endif

                                                <div class="col-md-4">
                                                    <label class="form-label small fw-bold text-muted mb-0">Help Text:</label>
                                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.help_text" placeholder="Subtext hint below field...">
                                                </div>

                                                @if(!in_array($f['type'], ['file']))
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold text-muted mb-0">Default Value:</label>
                                                        <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.default" placeholder="Initial value...">
                                                    </div>
                                                @endif

                                                <!-- Layout Controls Row: Width & Alignment & Height -->
                                                <div class="col-12 mt-2 p-2 bg-white rounded border border-light">
                                                    <div class="row g-2 align-items-center">
                                                        <div class="col-md-5">
                                                            <label class="form-label small fw-bold text-muted mb-0"><i class="bi bi-arrows-expand me-1"></i>Width (Grid Span):</label>
                                                            <select class="form-select form-select-sm mt-1" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.col_span">
                                                                <option value="12" {{ (int)($f['col_span'] ?? 12) === 12 ? 'selected' : '' }}>Full Width (12/12)</option>
                                                                <option value="6" {{ (int)($f['col_span'] ?? 12) === 6 ? 'selected' : '' }}>Half Width (6/12)</option>
                                                                <option value="4" {{ (int)($f['col_span'] ?? 12) === 4 ? 'selected' : '' }}>One Third (4/12)</option>
                                                                <option value="8" {{ (int)($f['col_span'] ?? 12) === 8 ? 'selected' : '' }}>Two Thirds (8/12)</option>
                                                                <option value="3" {{ (int)($f['col_span'] ?? 12) === 3 ? 'selected' : '' }}>One Quarter (3/12)</option>
                                                                <option value="9" {{ (int)($f['col_span'] ?? 12) === 9 ? 'selected' : '' }}>Three Quarters (9/12)</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-3">
                                                            <label class="form-label small fw-bold text-muted mb-0"><i class="bi bi-text-center me-1"></i>H-Alignment:</label>
                                                            <select class="form-select form-select-sm mt-1" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.align">
                                                                <option value="left" {{ ($f['align'] ?? 'left') === 'left' ? 'selected' : '' }}>Left</option>
                                                                <option value="center" {{ ($f['align'] ?? 'left') === 'center' ? 'selected' : '' }}>Center</option>
                                                                <option value="right" {{ ($f['align'] ?? 'left') === 'right' ? 'selected' : '' }}>Right</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-3">
                                                            <label class="form-label small fw-bold text-muted mb-0"><i class="bi bi-distribute-vertical me-1"></i>V-Alignment:</label>
                                                            <select class="form-select form-select-sm mt-1" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.valign">
                                                                <option value="top" {{ ($f['valign'] ?? 'center') === 'top' ? 'selected' : '' }}>Top</option>
                                                                <option value="center" {{ ($f['valign'] ?? 'center') === 'center' ? 'selected' : '' }}>Center</option>
                                                                <option value="bottom" {{ ($f['valign'] ?? 'center') === 'bottom' ? 'selected' : '' }}>Bottom</option>
                                                                <option value="stretch" {{ ($f['valign'] ?? 'center') === 'stretch' ? 'selected' : '' }}>Stretch</option>
                                                            </select>
                                                        </div>

                                                        @if($f['type'] === 'textarea')
                                                            <div class="col-md-3">
                                                                <label class="form-label small fw-bold text-muted mb-0"><i class="bi bi-distribute-vertical me-1"></i>Rows:</label>
                                                                <input type="number" min="2" max="15" class="form-control form-control-sm mt-1" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.rows" title="Textarea height in rows">
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Option Editor for Dropdown, Radio, Checkbox -->
                                                @if(in_array($f['type'], ['dropdown', 'radio', 'checkbox']))
                                                    <div class="col-12 mt-2">
                                                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-list-ul me-1"></i>Options (separated by commas):</label>
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               value="{{ is_array($f['options'] ?? null) ? implode(', ', $f['options']) : ($f['options'] ?? '') }}" 
                                                               wire:change="updateFieldOptions('{{ $sec['id'] }}', '{{ $f['id'] }}', $event.target.value)" 
                                                               wire:blur="updateFieldOptions('{{ $sec['id'] }}', '{{ $f['id'] }}', $event.target.value)" 
                                                               placeholder="e.g. Option A, Option B, Option C">
                                                    </div>
                                                @endif

                                                <!-- Advanced Validation & Rules Section (Filtered by Field Type) -->
                                                <div class="col-12 mt-2">
                                                    <div class="card p-2 border-0 bg-white shadow-xs">
                                                        <div class="fw-bold text-indigo small mb-2">
                                                            <i class="bi bi-shield-check me-1"></i> Validation Rules & Constraints
                                                        </div>
                                                        
                                                        <div class="row g-2">
                                                            <!-- Toggles -->
                                                            <div class="col-12 d-flex gap-3 flex-wrap align-items-center">
                                                                <div class="form-check form-switch mb-0">
                                                                    <input class="form-check-input" type="checkbox" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.required" id="req_{{ $f['id'] }}">
                                                                    <label class="form-check-label small fw-bold text-danger" for="req_{{ $f['id'] }}">Required Field</label>
                                                                </div>

                                                                @if($f['type'] === 'dropdown')
                                                                    <div class="form-check form-switch mb-0">
                                                                        <input class="form-check-input" type="checkbox" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.multiple" id="multi_{{ $f['id'] }}">
                                                                        <label class="form-check-label small" for="multi_{{ $f['id'] }}">Multi-Select</label>
                                                                    </div>
                                                                @endif

                                                                @if(in_array($f['type'], ['text', 'textarea', 'number']))
                                                                    <div class="form-check form-switch mb-0">
                                                                        <input class="form-check-input" type="checkbox" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.numeric" id="num_{{ $f['id'] }}">
                                                                        <label class="form-check-label small" for="num_{{ $f['id'] }}">Numeric Only</label>
                                                                    </div>
                                                                @endif

                                                                @if(in_array($f['type'], ['text', 'email']))
                                                                    <div class="form-check form-switch mb-0">
                                                                        <input class="form-check-input" type="checkbox" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.email" id="eml_{{ $f['id'] }}">
                                                                        <label class="form-check-label small" for="eml_{{ $f['id'] }}">Email Format</label>
                                                                    </div>
                                                                @endif

                                                                @if(in_array($f['type'], ['text', 'url']))
                                                                    <div class="form-check form-switch mb-0">
                                                                        <input class="form-check-input" type="checkbox" wire:model.live="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.url" id="url_{{ $f['id'] }}">
                                                                        <label class="form-check-label small" for="url_{{ $f['id'] }}">URL Format</label>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <!-- Min / Max Numeric Values -->
                                                            @if(in_array($f['type'], ['number', 'rating', 'date', 'time']))
                                                                <div class="col-md-3">
                                                                    <label class="form-label small text-muted mb-0">Min Value:</label>
                                                                    <input type="number" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.min" placeholder="e.g. 18">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label small text-muted mb-0">Max Value:</label>
                                                                    <input type="number" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.max" placeholder="e.g. 100">
                                                                </div>
                                                            @endif

                                                            <!-- Min / Max Character Length -->
                                                            @if(in_array($f['type'], ['text', 'textarea', 'phone', 'email', 'url']))
                                                                <div class="col-md-3">
                                                                    <label class="form-label small text-muted mb-0">Min Length:</label>
                                                                    <input type="number" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.min_length" placeholder="e.g. 5">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label small text-muted mb-0">Max Length:</label>
                                                                    <input type="number" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.max_length" placeholder="e.g. 255">
                                                                </div>
                                                            @endif

                                                            <!-- Regex Pattern -->
                                                            @if(in_array($f['type'], ['text', 'textarea', 'phone', 'email', 'url']))
                                                                <div class="col-md-6">
                                                                    <label class="form-label small text-muted mb-0">Regex Pattern:</label>
                                                                    <input type="text" class="form-control form-control-sm font-monospace" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.regex" placeholder="e.g. /^[A-Z0-9]+$/i">
                                                                </div>
                                                            @endif

                                                            <!-- File Specific Rules -->
                                                            @if($f['type'] === 'file')
                                                                <div class="col-md-4">
                                                                    <label class="form-label small text-muted mb-0">Allowed Extensions:</label>
                                                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.file_types" placeholder="e.g. pdf, png, jpg, docx">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label small text-muted mb-0">Max File Size (MB):</label>
                                                                    <input type="number" class="form-control form-control-sm" wire:model.live.debounce.300ms="schema.sections.{{ $sIndex }}.fields.{{ $fIndex }}.validation.max_file_size" placeholder="e.g. 10">
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @empty
                                <div class="col-12 text-center py-4 text-muted border border-dashed rounded-3">
                                    <p class="mb-1"><i class="bi bi-inbox fs-3 text-secondary"></i></p>
                                    <p class="small mb-0">Drag field type here or click <strong>"+ Add Field to Section"</strong> above to add fields.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($activeTab === 'json')
            <div class="col-12">
                <div class="card card-custom p-4 bg-white shadow-sm border-0">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3 pb-3 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-code-slash text-indigo me-2"></i>Raw JSON Schema Editor (Single Source of Truth)</h5>
                                @if($jsonError)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Invalid Schema
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> 2-Way Synced & Validated
                                    </span>
                                @endif
                            </div>
                            <p class="text-muted small mb-0 mt-1">Directly edit the underlying JSON schema object. Any changes sync instantly with the visual builder canvas and database upon saving.</p>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm" wire:click="syncRawJson" title="Prettify and format JSON schema">
                                <i class="bi bi-braces me-1"></i> Format JSON
                            </button>
                            <button class="btn btn-outline-primary btn-sm" wire:click="validateAndApplyRawJson" title="Validate & sync changes to canvas">
                                <i class="bi bi-arrow-repeat me-1"></i> Apply to Canvas
                            </button>
                            <button class="btn btn-indigo btn-sm shadow-sm" wire:click="saveForm">
                                <i class="bi bi-floppy me-1"></i> Save Schema
                            </button>
                        </div>
                    </div>

                    @if($jsonError)
                        <div class="alert alert-danger p-3 mb-3 rounded-3 shadow-xs border-0 border-start border-4 border-danger">
                            <div class="fw-bold text-danger mb-1"><i class="bi bi-x-circle-fill me-2"></i>Schema Validation Failed</div>
                            <div class="small font-monospace text-dark">{{ $jsonError }}</div>
                        </div>
                    @else
                        <div class="alert alert-success-subtle border-0 text-success p-2 px-3 mb-3 rounded-3 small d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-info-circle-fill me-1"></i> Schema structure validated: {{ count($schema['sections'] ?? []) }} sections, {{ array_sum(array_map(fn($s) => count($s['fields'] ?? []), $schema['sections'] ?? [])) }} total fields.</span>
                            <span class="badge bg-success text-white">Live 2-Way Sync</span>
                        </div>
                    @endif

                    <div class="position-relative">
                        <textarea class="form-control font-monospace p-4 bg-dark text-green rounded-3 fs-6 shadow-inner" style="min-height: 520px; font-family: 'Fira Code', 'Courier New', monospace; line-height: 1.5; color: #4ade80; background-color: #0f172a !important;" wire:model.live.debounce.400ms="rawJson" spellcheck="false" placeholder="// JSON Schema object..."></textarea>
                    </div>

                    <!-- Interactive Schema Documentation & Cheat Sheet -->
                    <div class="mt-4 p-4 bg-light rounded-3 border">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="bi bi-book-half text-indigo me-2"></i>Complete JSON Schema Property & Configuration Specification
                                </h6>
                                <p class="text-muted small mb-0">Full dictionary of all supported keys, data types, allowed values, layout options, and validation rules.</p>
                            </div>
                            <span class="badge bg-indigo-subtle text-indigo px-3 py-2">
                                <i class="bi bi-magic me-1"></i> Auto-Generates Omitted IDs & Unique Keys
                            </span>
                        </div>

                        <ul class="nav nav-tabs mb-3 border-bottom flex-wrap" id="schemaDocTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-bold small py-2 text-dark" id="tab-form-link" data-bs-toggle="tab" data-bs-target="#tab-form-doc" type="button">📌 Form & Settings</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 text-dark" id="tab-section-link" data-bs-toggle="tab" data-bs-target="#tab-section-doc" type="button">📁 Section Level</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 text-dark" id="tab-field-props-link" data-bs-toggle="tab" data-bs-target="#tab-field-props-doc" type="button">🏷️ Field Attributes</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 text-dark" id="tab-fields-link" data-bs-toggle="tab" data-bs-target="#tab-fields-doc" type="button">📝 13 Field Types</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 text-dark" id="tab-layout-link" data-bs-toggle="tab" data-bs-target="#tab-layout-doc" type="button">⚙️ Layout & Grid</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 text-dark" id="tab-validation-link" data-bs-toggle="tab" data-bs-target="#tab-validation-doc" type="button">🛡️ Validations</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 text-indigo" id="tab-sample-link" data-bs-toggle="tab" data-bs-target="#tab-sample-doc" type="button">📋 Master Schema Spec</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="schemaDocContent">
                            <!-- 1. Form Level -->
                            <div class="tab-pane fade show active" id="tab-form-doc">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Property Key</th>
                                                <th>Data Type</th>
                                                <th>Required</th>
                                                <th>Description & Allowed Values</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <tr>
                                                <td><code class="fw-bold">title</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-danger">Required</span></td>
                                                <td>Form title header (e.g., <code>"Internship Application Form"</code>).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">description</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Subtitle / guidance instructions paragraph at form top.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">settings.display_mode</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Navigation mode: <code>"single_page"</code> (scroll all) or <code>"wizard"</code> (step-by-step pages). Default: <code>"single_page"</code>.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">settings.layout_mode</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Canvas mode: <code>"grid"</code> (12-col responsive grid) or <code>"freeform"</code> (2D grid height span). Default: <code>"freeform"</code>.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">sections</code></td>
                                                <td><span class="badge bg-secondary">array</span></td>
                                                <td><span class="badge bg-danger">Required</span></td>
                                                <td>List of section objects containing fields. Must contain at least 1 section.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 2. Section Level -->
                            <div class="tab-pane fade" id="tab-section-doc">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Property Key</th>
                                                <th>Data Type</th>
                                                <th>Behavior / Requirement</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <tr>
                                                <td><code class="fw-bold">id</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-success">Auto-Generated</span></td>
                                                <td>Unique section identifier (e.g. <code>"sec_9x2ab"</code>). Omit to auto-assign a unique ID.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">title</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-danger">Required</span></td>
                                                <td>Section header title displayed above fields (e.g., <code>"Emergency Contact Info"</code>).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">description</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Subtitle note describing section purpose.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">fields</code></td>
                                                <td><span class="badge bg-secondary">array</span></td>
                                                <td><span class="badge bg-danger">Required</span></td>
                                                <td>Array of field objects inside this section.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 3. Field Properties & Attributes -->
                            <div class="tab-pane fade" id="tab-field-props-doc">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Field Attribute</th>
                                                <th>Type</th>
                                                <th>Requirement</th>
                                                <th>Description & Allowed Values</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <tr>
                                                <td><code class="fw-bold">id</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-success">Auto-Generated</span></td>
                                                <td>Unique field component ID (e.g. <code>"fld_a1b2c3"</code>). Omit to auto-assign.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">key</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-success">Auto-Generated</span></td>
                                                <td>Unique database storage key (e.g., <code>"full_name"</code>). Auto-generated from <code>label</code> and deduplicated if omitted or duplicated across schema.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">type</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-danger">Required</span></td>
                                                <td>Input element control type (see <strong>13 Field Types</strong> tab).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">label</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Field heading title (e.g., <code>"Phone Number"</code>). Defaults to headline of <code>type</code> if omitted.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">placeholder</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Input watermark placeholder hint text (e.g., <code>"e.g. +1 555-0199"</code>).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">help_text</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Helper caption string rendered below the input.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">default</code></td>
                                                <td><span class="badge bg-secondary">any</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Initial prefilled value (e.g., <code>"John Doe"</code>, <code>25</code>, or <code>["Option 1"]</code>).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">required</code></td>
                                                <td><span class="badge bg-secondary">boolean</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td><code>true</code> to make field mandatory, <code>false</code> for optional. Default: <code>false</code>.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">options</code></td>
                                                <td><span class="badge bg-secondary">array</span></td>
                                                <td><span class="badge bg-info text-dark">Required for lists</span></td>
                                                <td>Array of string choice options (e.g., <code>["Male", "Female", "Other"]</code>) for <code>dropdown</code>, <code>radio</code>, or <code>checkbox</code> fields.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">multiple</code></td>
                                                <td><span class="badge bg-secondary">boolean</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td><code>true</code> to allow selecting multiple options in <code>dropdown</code> select fields.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">rows</code></td>
                                                <td><span class="badge bg-secondary">integer</span></td>
                                                <td><span class="badge bg-light text-dark">Optional</span></td>
                                                <td>Textarea row height (e.g., <code>4</code>). Default: <code>4</code> for textarea, <code>1</code> for input.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 4. Supported Field Types -->
                            <div class="tab-pane fade" id="tab-fields-doc">
                                <p class="small text-muted mb-2">Supported <code>type</code> values in field objects (omitted <code>id</code> & <code>key</code> are auto-generated and deduplicated):</p>
                                <div class="row g-2">
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"text"</code> - Single line text input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"textarea"</code> - Multi-line paragraph input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"number"</code> - Numeric quantity input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"email"</code> - Email address input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"phone"</code> - International phone input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"date"</code> - Date picker input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"time"</code> - Time picker input</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"dropdown"</code> - Select dropdown menu</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"radio"</code> - Single choice radio group</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"checkbox"</code> - Multi-choice checkbox list</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"file"</code> - File attachment upload</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"heading"</code> - Visual sub-heading divider</div></div>
                                    <div class="col-md-3"><div class="p-2 border rounded bg-white small"><code>"rating"</code> - Interactive 1-5 star rating</div></div>
                                </div>
                            </div>

                            <!-- 5. Layout & Grid Span -->
                            <div class="tab-pane fade" id="tab-layout-doc">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Layout Key</th>
                                                <th>Type</th>
                                                <th>Allowed Values</th>
                                                <th>Effect</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <tr>
                                                <td><code class="fw-bold">col_span</code></td>
                                                <td><span class="badge bg-secondary">integer</span></td>
                                                <td><code>1</code> to <code>12</code> (Default: <code>12</code>)</td>
                                                <td>12-column responsive width span (e.g. <code>12</code> = Full width, <code>6</code> = Half width, <code>4</code> = One Third).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">row_span</code></td>
                                                <td><span class="badge bg-secondary">integer</span></td>
                                                <td><code>1</code> to <code>6</code> (Default: <code>1</code>)</td>
                                                <td>Height row span for textareas/cards on freeform canvas.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">align</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><code>"left"</code>, <code>"center"</code>, <code>"right"</code></td>
                                                <td>Horizontal text alignment inside field container.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">valign</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><code>"top"</code>, <code>"center"</code>, <code>"bottom"</code>, <code>"stretch"</code></td>
                                                <td>Vertical alignment for 2D flexbox layout containers.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 6. Validation Rules -->
                            <div class="tab-pane fade" id="tab-validation-doc">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Validation Key</th>
                                                <th>Type</th>
                                                <th>Sample JSON Value</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <tr>
                                                <td><code class="fw-bold">required</code></td>
                                                <td><span class="badge bg-secondary">boolean</span></td>
                                                <td><code>"required": true</code></td>
                                                <td>Makes field mandatory during user submission.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.numeric</code></td>
                                                <td><span class="badge bg-secondary">boolean</span></td>
                                                <td><code>"validation": { "numeric": true }</code></td>
                                                <td>Restricts input to numeric digits only.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.email</code></td>
                                                <td><span class="badge bg-secondary">boolean</span></td>
                                                <td><code>"validation": { "email": true }</code></td>
                                                <td>Validates valid email format (e.g. <code>user@example.com</code>).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.url</code></td>
                                                <td><span class="badge bg-secondary">boolean</span></td>
                                                <td><code>"validation": { "url": true }</code></td>
                                                <td>Validates valid URL format (e.g. <code>https://domain.com</code>).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.min / max</code></td>
                                                <td><span class="badge bg-secondary">number</span></td>
                                                <td><code>"validation": { "min": 18, "max": 65 }</code></td>
                                                <td>Sets minimum & maximum allowed numeric value.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.min_length / max_length</code></td>
                                                <td><span class="badge bg-secondary">integer</span></td>
                                                <td><code>"validation": { "min_length": 5, "max_length": 100 }</code></td>
                                                <td>Sets minimum & maximum character string lengths.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.regex</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><code>"validation": { "regex": "^[A-Z0-9]+$" }</code></td>
                                                <td>Validates value against custom Regex pattern.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.file_types</code></td>
                                                <td><span class="badge bg-secondary">string</span></td>
                                                <td><code>"validation": { "file_types": "pdf,doc,docx,png" }</code></td>
                                                <td>Comma-separated allowed file extensions for file uploads.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="fw-bold">validation.max_file_size</code></td>
                                                <td><span class="badge bg-secondary">integer</span></td>
                                                <td><code>"validation": { "max_file_size": 10 }</code></td>
                                                <td>Maximum allowed file attachment size in Megabytes (MB).</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 7. Master Schema Sample -->
                            <div class="tab-pane fade" id="tab-sample-doc">
                                <p class="small text-muted mb-2">Complete master schema featuring every single possible attribute, option, layout, and validation rule:</p>
                                <pre class="bg-dark text-light p-3 rounded-3 font-monospace small mb-0" style="max-height: 400px; overflow-y: auto;">
{
  "title": "Master Application Form",
  "description": "Comprehensive form demonstrating all supported properties",
  "settings": {
    "display_mode": "wizard",
    "layout_mode": "freeform"
  },
  "sections": [
    {
      "id": "sec_personal",
      "title": "Personal Information",
      "fields": [
        {
          "id": "fld_full_name",
          "key": "full_name",
          "type": "text",
          "label": "Full Name",
          "placeholder": "Enter your legal full name",
          "help_text": "Must match government ID",
          "default": "John Doe",
          "required": true,
          "col_span": 6,
          "align": "left",
          "valign": "center",
          "validation": {
            "min_length": 3,
            "max_length": 100
          }
        },
        {
          "id": "fld_email",
          "key": "email_address",
          "type": "email",
          "label": "Email Address",
          "placeholder": "john@example.com",
          "required": true,
          "col_span": 6,
          "validation": {
            "email": true
          }
        },
        {
          "id": "fld_resume",
          "key": "resume_file",
          "type": "file",
          "label": "Upload Resume",
          "help_text": "PDF or Word documents only, max 5MB",
          "required": true,
          "col_span": 12,
          "validation": {
            "file_types": "pdf,doc,docx",
            "max_file_size": 5
          }
        },
        {
          "id": "fld_dept",
          "key": "department",
          "type": "dropdown",
          "label": "Target Department",
          "options": ["Engineering", "Product", "Design", "Marketing"],
          "multiple": false,
          "required": true,
          "col_span": 12
        }
      ]
    }
  ]
}
                                </pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($activeTab === 'ai')
            <div class="col-12">
                <div class="card card-custom p-4 bg-white">
                    <h5 class="fw-bold mb-3"><i class="bi bi-magic text-purple me-2"></i>AI Form Modifier</h5>
                    <p class="text-muted small">Instruct the AI to modify or enhance your current form schema in plain English.</p>

                    <div class="mb-3">
                        <textarea class="form-control" rows="4" wire:model="aiEditPrompt" placeholder="e.g. Add an Emergency Contact section with Name, Phone, and Relation fields..."></textarea>
                    </div>

                    <button class="btn btn-primary" wire:click="applyAiEdit" wire:loading.attr="disabled">
                        <span wire:loading.remove><i class="bi bi-send me-1"></i> Apply AI Edit</span>
                        <span wire:loading><i class="spinner-border spinner-border-sm me-1"></i> Processing AI request...</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Part D Modal: Templates -->
    <div class="modal fade" id="templatesModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-collection text-indigo me-2"></i>Form Template Library</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @foreach($templates as $tmpl)
                            <div class="col-md-6">
                                <div class="card h-100 card-custom p-3 border hover-shadow">
                                    <span class="badge bg-indigo-subtle text-indigo w-fit mb-2">{{ $tmpl->category }}</span>
                                    <h6 class="fw-bold mb-1">{{ $tmpl->title }}</h6>
                                    <p class="text-muted small flex-grow-1">{{ $tmpl->description }}</p>
                                    <button class="btn btn-sm btn-indigo mt-2 w-100" wire:click="applyTemplate({{ $tmpl->id }})" data-bs-dismiss="modal">
                                        Use Template
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Part D Modal: Share & QR Embed Widget -->
    @if($form)
        <div class="modal fade" id="shareModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold"><i class="bi bi-qr-code text-indigo me-2"></i>Share & Embed Form</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode(route('forms.public', ['slug' => $form->slug])) }}" class="img-fluid border p-2 rounded bg-white shadow-sm" alt="Form QR Code">
                        </div>
                        <p class="small text-muted mb-3">Scan QR code to open form directly on mobile devices.</p>

                        <div class="text-start">
                            <label class="form-label fw-bold small">Embed HTML Snippet (Iframe Widget):</label>
                            <div class="input-group mb-3">
                                <input type="text" readonly class="form-control font-monospace small" value="<iframe src=&quot;{{ route('forms.public', ['slug' => $form->slug]) }}&quot; width=&quot;100%&quot; height=&quot;650&quot; frameborder=&quot;0&quot;></iframe>">
                                <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Embed code copied!');">
                                    Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
