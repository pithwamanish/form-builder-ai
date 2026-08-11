@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Submissions: {{ $form->title }}</h3>
            <p class="text-muted small mb-0">Total Submissions: {{ $submissions->total() }} | Views: {{ $form->views_count }}</p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('forms.edit', ['uuid' => $form->uuid]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Builder
            </a>
            <a href="{{ route('forms.submissions.export', ['uuid' => $form->uuid]) }}" class="btn btn-success" download="{{ $form->slug }}_submissions.csv">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card card-custom p-3 bg-white mb-4">
        <form method="GET" action="{{ route('forms.submissions', ['uuid' => $form->uuid]) }}">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search submission responses..." value="{{ request('search') }}">
                <button class="btn btn-indigo" type="submit">Search</button>
            </div>
        </form>
    </div>

    <!-- Submissions Table -->
    <div class="card card-custom bg-white shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th># ID</th>
                        <th>Submitted At</th>
                        <th>IP Address</th>
                        @foreach($form->schema['sections'] ?? [] as $sec)
                            @foreach($sec['fields'] ?? [] as $f)
                                <th>{{ $f['label'] }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $sub)
                        <tr>
                            <td class="fw-bold">#{{ $sub->id }}</td>
                            <td class="small text-muted">{{ $sub->created_at->diffForHumans() }}</td>
                            <td class="small"><code>{{ $sub->ip_address }}</code></td>
                            @foreach($form->schema['sections'] ?? [] as $sec)
                                @foreach($sec['fields'] ?? [] as $f)
                                    @php
                                        $val = $sub->submission_data[$f['key']] ?? '-';
                                        $isFile = ($f['type'] === 'file') && !empty($val) && $val !== '-';
                                        if (is_array($val)) {
                                            $val = implode(', ', $val);
                                        }
                                    @endphp
                                    <td>
                                        @if($isFile)
                                            <a href="{{ route('forms.submissions.download', ['uuid' => $form->uuid, 'submissionId' => $sub->id, 'fieldKey' => $f['key']]) }}" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-file-earmark-arrow-down"></i>
                                                <span>Download File</span>
                                            </a>
                                        @else
                                            {{ $val }}
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No submissions recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
