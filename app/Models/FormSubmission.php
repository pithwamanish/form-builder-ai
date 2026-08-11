<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'form_id',
        'submission_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'submission_data' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
