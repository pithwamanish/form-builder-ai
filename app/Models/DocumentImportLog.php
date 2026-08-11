<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_size',
        'status',
        'parsed_schema',
        'unparseable_blocks',
        'error_message',
    ];

    protected $casts = [
        'parsed_schema' => 'array',
        'unparseable_blocks' => 'array',
    ];
}
