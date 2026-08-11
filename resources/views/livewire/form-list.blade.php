<div class="container-fluid px-4">
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show card-custom mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Header & Search Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-folder2-open text-indigo me-2"></i>All Saved Forms</h3>
            <p class="text-muted small mb-0">Manage, edit, publish, and track responses for all your created forms.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('forms.create') }}" class="btn btn-indigo px-4">
                <i class="bi bi-plus-lg me-1"></i> Create New Form
            </a>
        </div>
    </div>

    <!-- Search input -->
    <div class="card card-custom p-3 bg-white mb-4">
        <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0" placeholder="Search forms by title or description..." wire:model.live.debounce.300ms="search">
        </div>
    </div>

    <!-- Forms Grid -->
    <div class="row g-4">
        @forelse($forms as $form)
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom bg-white h-100 shadow-sm border-top border-4 border-indigo d-flex flex-column">
                    <div class="card-body p-4 flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="fw-bold text-dark mb-0">{{ $form->title }}</h5>
                            <span class="badge bg-secondary-subtle text-secondary small">
                                <i class="bi bi-eye me-1"></i>{{ $form->views_count }} views
                            </span>
                        </div>

                        <p class="text-muted small mb-3 flex-grow-1">
                            {{ Str::limit($form->description ?: 'No description provided.', 90) }}
                        </p>

                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <span class="badge bg-indigo-subtle text-indigo">
                                <i class="bi bi-layers me-1"></i>{{ count($form->schema['sections'] ?? []) }} Sections
                            </span>
                            <span class="badge bg-info-subtle text-info">
                                <i class="bi bi-table me-1"></i>{{ $form->submissions()->count() }} Submissions
                            </span>
                        </div>

                        <div class="text-muted small border-top pt-2">
                            <i class="bi bi-clock me-1"></i>Updated {{ $form->updated_at->diffForHumans() }}
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3 border-top-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div class="btn-group">
                            <a href="{{ route('forms.edit', ['uuid' => $form->uuid]) }}" class="btn btn-sm btn-outline-primary" title="Edit in Visual Builder">
                                <i class="bi bi-pencil me-1"></i> Builder
                            </a>
                            <a href="{{ route('forms.public', ['slug' => $form->slug]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="View Public Form">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Public
                            </a>
                            <a href="{{ route('forms.submissions', ['uuid' => $form->uuid]) }}" class="btn btn-sm btn-outline-info" title="Submissions Dashboard">
                                <i class="bi bi-table me-1"></i> Data
                            </a>
                        </div>

                        <button class="btn btn-sm btn-outline-danger" onclick="confirm('Are you sure you want to delete this form?') || event.stopImmediatePropagation()" wire:click="deleteForm({{ $form->id }})" title="Delete Form">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <div class="card card-custom p-5 bg-white">
                    <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                    <h5 class="fw-bold">No saved forms found</h5>
                    <p class="text-muted small">You haven't created any forms yet, or no forms match your search query.</p>
                    <div>
                        <a href="{{ route('forms.create') }}" class="btn btn-indigo px-4">
                            <i class="bi bi-plus-lg me-1"></i> Create Your First Form
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if($forms->hasPages())
        <div class="mt-4">
            {{ $forms->links() }}
        </div>
    @endif
</div>
