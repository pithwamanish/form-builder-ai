<?php

namespace App\Livewire;

use App\Models\Form;
use Livewire\Component;
use Livewire\WithPagination;

use Illuminate\Support\Facades\Cache;

class FormList extends Component
{
    use WithPagination;

    public string $search = '';
    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function deleteForm(int $id)
    {
        $form = Form::findOrFail($id);
        $form->delete();
        Cache::forget('form_dashboard_stats');
        Cache::forget("public_form_{$form->slug}");
        session()->flash('message', "Form '{$form->title}' deleted successfully!");
    }

    public function render()
    {
        $query = Form::query()->orderBy('updated_at', 'desc');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'LIKE', "%{$this->search}%")
                  ->orWhere('description', 'LIKE', "%{$this->search}%");
            });
        }

        $stats = Cache::remember('form_dashboard_stats', 300, function () {
            return [
                'total_forms' => Form::count(),
                'total_submissions' => \App\Models\FormSubmission::count(),
            ];
        });

        return view('livewire.form-list', [
            'forms' => $query->paginate(12),
            'stats' => $stats,
        ])->layout('layouts.app');
    }
}
