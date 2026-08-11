<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-custom p-4 bg-white">
                @if($submitted)
                    <div class="text-center py-5">
                        <div class="mb-3 text-success">
                            <i class="bi bi-check-circle-fill display-1"></i>
                        </div>
                        <h2 class="fw-bold text-dark">Thank You!</h2>
                        <p class="text-muted fs-5">Your response has been recorded successfully.</p>
                        <a href="{{ route('forms.public', ['slug' => $form->slug]) }}" class="btn btn-indigo px-4 mt-3" onclick="location.reload(); return false;">
                            Submit Another Response
                        </a>
                    </div>
                @else
                    <div class="border-bottom pb-3 mb-4">
                        <h2 class="fw-bold mb-1">{{ $form->title }}</h2>
                        @if($form->description)
                            <p class="text-muted mb-0">{{ $form->description }}</p>
                        @endif
                    </div>

                    <form wire:submit.prevent="submit">
                        <!-- Part D Honeypot Field (Hidden from real users, traps bots) -->
                        <div style="display:none !important;" aria-hidden="true">
                            <input type="text" wire:model="honeypot" tabindex="-1" autocomplete="off">
                        </div>

                    @php 
                        $sections = $form->schema['sections'] ?? []; 
                        $totalSteps = count($sections); 
                        $displayMode = $form->schema['settings']['display_mode'] ?? 'wizard';
                        $isWizardMode = ($displayMode === 'wizard' && $totalSteps > 1);
                    @endphp

                    @if($isWizardMode)
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-indigo-subtle text-indigo fw-bold fs-6">Step {{ $currentStep + 1 }} of {{ $totalSteps }}</span>
                                <span class="small text-muted fw-bold">{{ round((($currentStep + 1) / $totalSteps) * 100) }}% Completed</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-indigo" style="width: {{ (($currentStep + 1) / $totalSteps) * 100 }}%;"></div>
                            </div>
                            <div class="d-flex gap-2 mt-3 overflow-auto pb-1">
                                @foreach($sections as $sIdx => $sData)
                                    <button type="button" class="btn btn-sm {{ $currentStep === $sIdx ? 'btn-indigo' : 'btn-outline-secondary' }} text-nowrap" wire:click="goToStep({{ $sIdx }})">
                                        {{ $sIdx + 1 }}. {{ Str::limit($sData['title'] ?: 'Step '.($sIdx+1), 20) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form wire:submit.prevent="submit">
                        <!-- Part D Honeypot Field (Hidden from real users, traps bots) -->
                        <div style="display:none !important;" aria-hidden="true">
                            <input type="text" wire:model="honeypot" tabindex="-1" autocomplete="off">
                        </div>

                        @foreach($sections as $sIndex => $sec)
                            @if(!$isWizardMode || $currentStep === $sIndex)
                                <div class="mb-4">
                                    <h5 class="fw-bold text-indigo border-bottom pb-2 mb-3">{{ $sec['title'] }}</h5>

                                    @php $isFreeform = ($form->schema['settings']['layout_mode'] ?? 'grid') === 'freeform'; @endphp
                                    @if($isFreeform)
                                        <div class="d-grid gap-3" style="grid-template-columns: repeat(12, 1fr); grid-auto-rows: minmax(90px, auto); grid-auto-flow: dense;">
                                    @else
                                        <div class="row g-3">
                                    @endif
                                        @foreach($sec['fields'] ?? [] as $f)
                                            @php
                                                $colSpan = min(12, max(1, $f['col_span'] ?? 12));
                                                $rowSpan = min(6, max(1, $f['row_span'] ?? 1));
                                                $vAlign = $f['valign'] ?? 'center';
                                                $vClass = match($vAlign) {
                                                    'top' => 'd-flex flex-column justify-content-start h-100',
                                                    'bottom' => 'd-flex flex-column justify-content-end h-100',
                                                    'stretch' => 'd-flex flex-column justify-content-between h-100',
                                                    default => $isFreeform ? 'd-flex flex-column justify-content-center h-100' : '',
                                                };
                                            @endphp
                                            <div class="{{ $isFreeform ? '' : 'col-12 col-md-' . $colSpan }} text-{{ $f['align'] ?? 'left' }} {{ $vClass }}"
                                                 style="{{ $isFreeform ? 'grid-column: span ' . $colSpan . '; grid-row: span ' . $rowSpan . ';' : '' }}">
                                                @php 
                                                    $fieldKey = 'formData.' . $f['key']; 
                                                    $isSingleCheckbox = ($f['type'] === 'checkbox' && (!isset($f['options']) || !is_array($f['options']) || count($f['options']) === 0));
                                                    $isHeading = ($f['type'] === 'heading');
                                                @endphp

                                                @if(!$isSingleCheckbox && !$isHeading)
                                                    <label class="form-label fw-bold">
                                                        {{ $f['label'] ?? 'Field' }}
                                                        @if($f['required'] ?? false)
                                                            <span class="text-danger">*</span>
                                                        @endif
                                                    </label>
                                                @endif

                                                @if($f['type'] === 'textarea')
                                                    <textarea class="form-control" rows="{{ $f['rows'] ?? 4 }}" style="resize: vertical;" wire:model="{{ $fieldKey }}" placeholder="{{ $f['placeholder'] ?? '' }}"></textarea>

                                                @elseif($f['type'] === 'dropdown')
                                                    @if($f['multiple'] ?? false)
                                                        <select class="form-select" multiple wire:model="{{ $fieldKey }}">
                                                            @foreach($f['options'] ?? [] as $opt)
                                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                                            @endforeach
                                                        </select>
                                                        <div class="form-text small text-muted">Hold Ctrl / Cmd to select multiple options.</div>
                                                    @else
                                                        <select class="form-select" wire:model="{{ $fieldKey }}">
                                                            <option value="">-- Select Option --</option>
                                                            @foreach($f['options'] ?? [] as $opt)
                                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif

                                                @elseif($f['type'] === 'radio')
                                                    <div class="d-flex gap-3 flex-wrap justify-content-{{ ($f['align'] ?? 'left') === 'center' ? 'center' : (($f['align'] ?? 'left') === 'right' ? 'end' : 'start') }}">
                                                        @foreach($f['options'] ?? [] as $opt)
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio" name="radio_{{ $f['id'] }}" value="{{ $opt }}" wire:model="{{ $fieldKey }}" id="r_{{ $f['id'] }}_{{ $loop->index }}">
                                                                <label class="form-check-label" for="r_{{ $f['id'] }}_{{ $loop->index }}">{{ $opt }}</label>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                @elseif($f['type'] === 'checkbox')
                                                    @if(isset($f['options']) && is_array($f['options']) && count($f['options']) > 0)
                                                        <div class="d-flex gap-3 flex-wrap justify-content-{{ ($f['align'] ?? 'left') === 'center' ? 'center' : (($f['align'] ?? 'left') === 'right' ? 'end' : 'start') }}">
                                                            @foreach($f['options'] as $opt)
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="checkbox" value="{{ $opt }}" wire:model="{{ $fieldKey }}" id="cb_{{ $f['id'] }}_{{ $loop->index }}">
                                                                    <label class="form-check-label" for="cb_{{ $f['id'] }}_{{ $loop->index }}">{{ $opt }}</label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="form-check mt-1">
                                                            <input class="form-check-input" type="checkbox" value="1" wire:model="{{ $fieldKey }}" id="cb_{{ $f['id'] }}">
                                                            <label class="form-check-label fw-bold text-dark" for="cb_{{ $f['id'] }}">
                                                                {{ $f['label'] }}
                                                                @if($f['required'] ?? false)
                                                                    <span class="text-danger">*</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    @endif

                                                @elseif($f['type'] === 'heading')
                                                    <h5 class="fw-bold text-dark border-bottom pb-1 mt-2 mb-2 text-{{ $f['align'] ?? 'left' }}">{{ $f['label'] }}</h5>

                                                @elseif($f['type'] === 'rating')
                                                    <div class="d-flex gap-2 flex-wrap justify-content-{{ ($f['align'] ?? 'left') === 'center' ? 'center' : (($f['align'] ?? 'left') === 'right' ? 'end' : 'start') }}">
                                                        @for($star = 1; $star <= 5; $star++)
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio" name="rating_{{ $f['id'] }}" value="{{ $star }}" wire:model="{{ $fieldKey }}" id="star_{{ $f['id'] }}_{{ $star }}">
                                                                <label class="form-check-label fw-bold text-warning" for="star_{{ $f['id'] }}_{{ $star }}">{{ $star }} ★</label>
                                                            </div>
                                                        @endfor
                                                    </div>

                                                @elseif($f['type'] === 'file')
                                                    <input type="file" class="form-control" wire:model="uploads.{{ $f['key'] }}">

                                                @elseif($f['type'] === 'phone')
                                                    <input type="tel" 
                                                           class="form-control" 
                                                           pattern="[0-9+\s-()]*" 
                                                           oninput="this.value = this.value.replace(/[^0-9+\s-()]/g, '')" 
                                                           wire:model="{{ $fieldKey }}" 
                                                           placeholder="{{ $f['placeholder'] ?? 'e.g. +1 (555) 000-0000' }}">

                                                @else
                                                    <input type="{{ $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : ($f['type'] === 'email' ? 'email' : 'text')) }}" 
                                                           class="form-control text-{{ $f['align'] ?? 'left' }}" 
                                                           wire:model="{{ $fieldKey }}" 
                                                           placeholder="{{ $f['placeholder'] ?? '' }}">
                                                @endif

                                                @if($f['help_text'] ?? false)
                                                    <div class="form-text small">{{ $f['help_text'] }}</div>
                                                @endif

                                                @error($fieldKey)
                                                    <span class="text-danger small d-block mt-1">{{ $message }}</span>
                                                @enderror
                                                @if($f['type'] === 'file')
                                                    @error('uploads.' . $f['key'])
                                                        <span class="text-danger small d-block mt-1">{{ $message }}</span>
                                                    @enderror
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            @if($isWizardMode && $currentStep > 0)
                                <button type="button" class="btn btn-outline-secondary px-4 py-2" wire:click="prevStep">
                                    <i class="bi bi-arrow-left me-1"></i> Previous Step
                                </button>
                            @else
                                <div></div>
                            @endif

                            @if($isWizardMode && $currentStep < $totalSteps - 1)
                                <button type="button" class="btn btn-indigo px-4 py-2 fw-bold" wire:click="nextStep">
                                    Next Step <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            @else
                                <button type="submit" class="btn btn-indigo btn-lg px-5 py-3 fw-bold">
                                    Submit Form
                                </button>
                            @endif
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
