<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'slug',
        'title',
        'description',
        'schema',
        'status',
        'views_count',
    ];

    protected $casts = [
        'schema' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (empty($form->uuid)) {
                $form->uuid = (string) Str::uuid();
            }
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->title) . '-' . Str::random(5);
            }
        });
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function aiLogs()
    {
        return $this->hasMany(AiGenerationLog::class);
    }
}
