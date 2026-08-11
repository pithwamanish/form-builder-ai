<?php

declare(strict_types=1);

namespace App\Contracts;

interface DocumentParserInterface
{
    /**
     * Parse a Word (.docx) or Excel (.xlsx/.csv) file into a form schema.
     */
    public function parseDocument(string $filePath, string $extension): array;
}
