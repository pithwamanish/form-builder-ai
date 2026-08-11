<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'AI Form Builder Studio' }}</title>
    
    <!-- Fonts & Bootstrap 5 CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    @livewireStyles

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        .navbar-brand-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .btn-indigo {
            background-color: #4f46e5;
            color: #ffffff;
            border: none;
        }
        .btn-indigo:hover {
            background-color: #4338ca;
            color: #ffffff;
        }
        .badge-ai {
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-2">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('forms.index') }}">
                <i class="bi bi-cpu-fill text-primary fs-4"></i>
                <span class="navbar-brand-gradient fs-4">FormCraft AI</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                @if(!request()->routeIs('forms.public'))
                    <ul class="navbar-nav me-auto ms-4 mb-2 mb-lg-0 gap-2">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('forms.index') ? 'active fw-bold text-primary' : '' }}" href="{{ route('forms.index') }}">
                                <i class="bi bi-folder2-open me-1"></i> My Saved Forms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('forms.create') || request()->routeIs('forms.edit') ? 'active fw-bold text-primary' : '' }}" href="{{ route('forms.create') }}">
                                <i class="bi bi-kanban me-1"></i> Visual Builder
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('forms.ai') ? 'active fw-bold text-primary' : '' }}" href="{{ route('forms.ai') }}">
                                <i class="bi bi-magic me-1"></i> AI Form Generator <span class="badge badge-ai ms-1">AI</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('forms.import') ? 'active fw-bold text-primary' : '' }}" href="{{ route('forms.import') }}">
                                <i class="bi bi-file-earmark-arrow-up me-1"></i> Import Word/Excel
                            </a>
                        </li>
                    </ul>
                @else
                    <div class="me-auto ms-4 text-muted small d-none d-md-block">
                        <span class="badge bg-light text-dark border me-1"><i class="bi bi-shield-check text-success me-1"></i> Secure Form Submission</span>
                    </div>
                @endif

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <!-- Multi-Tenant Scope Switcher (Part D Differentiator) -->
                    <div class="dropdown me-1">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1 py-1 px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Switch Multi-Tenant Data Scope">
                            <i class="bi bi-building text-primary"></i>
                            <span class="small">Tenant: <strong class="text-dark">{{ session('tenant_id', 'default') }}</strong></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header"><i class="bi bi-shield-lock me-1"></i> Multi-Tenant Scope Isolation</h6></li>
                            <li><a class="dropdown-item {{ session('tenant_id', 'default') === 'default' ? 'active fw-bold' : '' }}" href="{{ route('tenant.switch', 'default') }}"><i class="bi bi-building-check me-2 text-primary"></i> Default Tenant (`default`)</a></li>
                            <li><a class="dropdown-item {{ session('tenant_id') === 'acme_corp' ? 'active fw-bold' : '' }}" href="{{ route('tenant.switch', 'acme_corp') }}"><i class="bi bi-building-fill me-2 text-success"></i> Acme Corp (`acme_corp`)</a></li>
                            <li><a class="dropdown-item {{ session('tenant_id') === 'globex_inc' ? 'active fw-bold' : '' }}" href="{{ route('tenant.switch', 'globex_inc') }}"><i class="bi bi-building-fill-gear me-2 text-purple"></i> Globex Inc (`globex_inc`)</a></li>
                        </ul>
                    </div>

                    <?php
                        $activeDb = 'unknown';
                        try {
                            $activeDb = \Illuminate\Support\Facades\DB::connection()->getDriverName();
                        } catch (\Throwable $e) {
                            $activeDb = 'error';
                        }
                    ?>
                    <span class="badge {{ $activeDb === 'pgsql' ? 'bg-primary-subtle text-primary border-primary-subtle' : 'bg-warning-subtle text-dark border-warning-subtle' }} border px-3 py-2 rounded-pill" title="Active Database Driver">
                        <i class="bi {{ $activeDb === 'pgsql' ? 'bi-database-fill-check' : 'bi-database-fill-exclamation' }} me-1"></i> DB: {{ strtoupper($activeDb) }}
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <main class="py-4">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    @livewireScripts
</body>
</html>
