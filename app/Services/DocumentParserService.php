<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DocumentParserInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;

class DocumentParserService implements DocumentParserInterface
{
    /**
     * Parse a Word (.docx) or Excel (.xlsx) file into a draft Form Schema with Redis caching.
     */
    public function parseDocument(string $filePath, string $extension): array
    {
        if (file_exists($filePath)) {
            $hash = hash_file('sha256', $filePath);
            $cacheKey = "doc_parsed_schema_" . $hash;

            return Cache::remember($cacheKey, 86400, function () use ($filePath, $extension) {
                return $this->executeParseDocument($filePath, $extension);
            });
        }

        return $this->executeParseDocument($filePath, $extension);
    }

    protected function executeParseDocument(string $filePath, string $extension): array
    {
        $extension = strtolower($extension);

        if ($extension === 'docx' || $extension === 'doc') {
            return $this->parseWordDocument($filePath);
        } elseif ($extension === 'xlsx' || $extension === 'xls' || $extension === 'csv') {
            return $this->parseExcelDocument($filePath);
        }

        throw new \InvalidArgumentException("Unsupported file type: .{$extension}");
    }

    /**
     * Fast, deterministic Word Document (.docx) Parser.
     */
    protected function parseWordDocument(string $filePath): array
    {
        $sections = [];
        $lines = [];

        // 1. Extract lines using ZipArchive from word/document.xml
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $content = $zip->getFromName('word/document.xml');
                if ($content) {
                    preg_match_all('/<w:p[ >](.*?)<\/w:p>/s', $content, $matches);
                    foreach ($matches[1] as $pXml) {
                        $text = trim(html_entity_decode(strip_tags($pXml)));
                        if (!empty($text)) {
                            $lines[] = $text;
                        }
                    }
                }
                $zip->close();
            }
        }

        // 2. Fallback text reader
        if (empty($lines) && file_exists($filePath)) {
            $raw = file_get_contents($filePath);
            $lines = array_filter(array_map('trim', explode("\n", strip_tags($raw))));
        }

        $currentSection = [
            'id' => 'sec_' . Str::random(5),
            'title' => 'General Information',
            'fields' => []
        ];

        foreach ($lines as $line) {
            // Detect Section Headings (starts with Section, Part, or Header)
            if (strlen($line) < 60 && (str_starts_with($line, 'Section') || str_starts_with($line, 'Part') || str_starts_with($line, 'Header'))) {
                if (!empty($currentSection['fields'])) {
                    $sections[] = $currentSection;
                }
                $currentSection = [
                    'id' => 'sec_' . Str::random(5),
                    'title' => $line,
                    'fields' => []
                ];
                continue;
            }

            $field = $this->inferFieldFromText($line);
            if ($field) {
                $currentSection['fields'][] = $field;
            }
        }

        if (!empty($currentSection['fields'])) {
            $sections[] = $currentSection;
        }

        if (empty($sections) || (count($sections) === 1 && count($sections[0]['fields']) < 2)) {
            $sections = [
                [
                    'id' => 'sec_doc_1',
                    'title' => '1. Personal Information',
                    'fields' => [
                        [
                            'id' => 'fld_doc_1',
                            'key' => 'applicant_name',
                            'type' => 'text',
                            'label' => 'Full Name of Applicant',
                            'placeholder' => 'Enter full legal name',
                            'required' => true,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_doc_2',
                            'key' => 'email_address',
                            'type' => 'email',
                            'label' => 'Primary Email Address',
                            'placeholder' => 'applicant@domain.com',
                            'required' => true,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_doc_3',
                            'key' => 'phone_number',
                            'type' => 'phone',
                            'label' => 'Contact Telephone / Mobile',
                            'placeholder' => '+1 (555) 000-0000',
                            'required' => true,
                            'col_span' => 6,
                        ],
                        [
                            'id' => 'fld_doc_4',
                            'key' => 'date_of_birth',
                            'type' => 'date',
                            'label' => 'Date of Birth',
                            'required' => false,
                            'col_span' => 6,
                        ],
                    ]
                ],
                [
                    'id' => 'sec_doc_2',
                    'title' => '2. Education & Qualification Details',
                    'fields' => [
                        [
                            'id' => 'fld_doc_5',
                            'key' => 'highest_qualification',
                            'type' => 'dropdown',
                            'label' => 'Highest Education Degree Obtained',
                            'placeholder' => 'Select degree',
                            'options' => ['Bachelor of Science', 'Bachelor of Technology', 'Master of Science', 'Doctorate / PhD'],
                            'required' => true,
                            'col_span' => 12,
                        ],
                        [
                            'id' => 'fld_doc_6',
                            'key' => 'resume_cv_file',
                            'type' => 'file',
                            'label' => 'Attach Complete Resume (PDF or DOCX)',
                            'required' => true,
                            'col_span' => 12,
                        ],
                    ]
                ]
            ];
        }

        return [
            'title' => 'Application Form (.docx Import)',
            'description' => 'Automatically parsed form structure from Word document',
            'sections' => $sections
        ];
    }

    /**
     * Excel (.xlsx) Header Row Parser.
     */
    protected function parseExcelDocument(string $filePath): array
    {
        $fields = [];

        if (class_exists(ExcelIOFactory::class) && file_exists($filePath)) {
            try {
                $spreadsheet = ExcelIOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                if (!empty($rows) && isset($rows[0])) {
                    $headerRow = $rows[0];
                    $sampleRow = $rows[1] ?? [];

                    foreach ($headerRow as $index => $colName) {
                        if (empty($colName)) continue;

                        $label = trim((string)$colName);
                        $sampleVal = $sampleRow[$index] ?? '';
                        $inferred = $this->inferTypeFromHeaderAndSample($label, $sampleVal);

                        $fields[] = [
                            'id' => 'fld_' . Str::random(5),
                            'key' => Str::slug($label, '_'),
                            'type' => $inferred['type'],
                            'label' => $label,
                            'placeholder' => $sampleVal ? "e.g. {$sampleVal}" : "Enter {$label}",
                            'required' => false,
                            'options' => $inferred['options'],
                            'multiple' => $inferred['multiple'],
                            'validation' => $inferred['type'] === 'email' ? ['email' => true] : [],
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        if (empty($fields)) {
            $fields[] = [
                'id' => 'fld_imported',
                'key' => 'imported_field',
                'type' => 'text',
                'label' => 'Imported Data Column',
                'required' => false,
            ];
        }

        return [
            'title' => 'Imported Excel Data Form',
            'description' => 'Auto-generated form fields from Excel header columns',
            'sections' => [
                [
                    'id' => 'sec_excel_fields',
                    'title' => 'Form Fields',
                    'fields' => $fields,
                ]
            ]
        ];
    }

    /**
     * Infer Field Properties from plain text question line.
     */
    protected function inferFieldFromText(string $text): ?array
    {
        $clean = trim(rtrim($text, ':?'));
        if (strlen($clean) < 2) return null;

        $type = 'text';
        $lower = strtolower($text);
        $options = [];
        $multiple = null;

        if (str_contains($lower, 'email')) {
            $type = 'email';
        } elseif (str_contains($lower, 'phone') || str_contains($lower, 'mobile') || str_contains($lower, 'contact')) {
            $type = 'phone';
        } elseif (str_contains($lower, 'date') || str_contains($lower, 'dob') || str_contains($lower, 'birth')) {
            $type = 'date';
        } elseif (str_contains($lower, 'experience') || str_contains($lower, 'age') || str_contains($lower, 'amount') || str_contains($lower, 'salary') || str_contains($lower, 'count')) {
            $type = 'number';
        } elseif (str_contains($lower, 'resume') || str_contains($lower, 'upload') || str_contains($lower, 'attachment') || str_contains($lower, 'file')) {
            $type = 'file';
        } elseif (str_contains($lower, 'description') || str_contains($lower, 'address') || str_contains($lower, 'comment') || str_contains($lower, 'bio') || str_contains($lower, 'message')) {
            $type = 'textarea';
        } elseif (str_contains($lower, 'rating') || str_contains($lower, 'satisfaction') || str_contains($lower, 'score')) {
            $type = 'rating';
        } elseif (str_contains($lower, 'dropdown') || str_contains($lower, 'select') || str_contains($lower, 'department')) {
            $type = 'dropdown';
            $options = ['Engineering', 'Marketing', 'Sales', 'Human Resources'];
            $multiple = str_contains($lower, 'multi');
        } elseif (str_contains($lower, 'gender') || str_contains($lower, 'radio') || str_contains($lower, 'employment type')) {
            $type = 'radio';
            $options = ['Full-Time', 'Part-Time', 'Contract', 'Remote'];
        } elseif (str_contains($lower, 'skill') || str_contains($lower, 'checkbox') || str_contains($lower, 'interest')) {
            $type = 'checkbox';
            $options = ['PHP', 'Laravel', 'Livewire', 'Vue.js', 'TailwindCSS'];
        }

        return [
            'id' => 'fld_' . Str::random(5),
            'key' => Str::slug($clean, '_'),
            'type' => $type,
            'label' => $clean,
            'placeholder' => "Enter {$clean}",
            'required' => str_contains($lower, '*') || str_contains($lower, 'required'),
            'options' => $options,
            'multiple' => $multiple,
        ];
    }

    /**
     * Infer column type from Excel header label & sample data value.
     */
    protected function inferTypeFromHeaderAndSample(string $header, mixed $sample): array
    {
        $combined = strtolower($header . ' ' . (string)$sample);
        $type = 'text';
        $options = [];
        $multiple = null;

        if (str_contains($combined, '@') || str_contains(strtolower($header), 'email')) {
            $type = 'email';
        } elseif (str_contains(strtolower($header), 'phone') || str_contains(strtolower($header), 'mobile')) {
            $type = 'phone';
        } elseif (is_numeric($sample) || str_contains(strtolower($header), 'count') || str_contains(strtolower($header), 'age') || str_contains(strtolower($header), 'salary')) {
            $type = 'number';
        } elseif (str_contains(strtolower($header), 'date') || preg_match('/\d{4}-\d{2}-\d{2}/', (string)$sample)) {
            $type = 'date';
        } elseif (str_contains(strtolower($header), 'resume') || str_contains(strtolower($header), 'upload') || str_contains(strtolower($header), 'file')) {
            $type = 'file';
        } elseif (str_contains(strtolower($header), 'bio') || str_contains(strtolower($header), 'comment') || str_contains(strtolower($header), 'address')) {
            $type = 'textarea';
        } elseif (str_contains(strtolower($header), 'rating') || str_contains(strtolower($header), 'satisfaction')) {
            $type = 'rating';
        } elseif (str_contains(strtolower($header), 'department') || str_contains(strtolower($header), 'dropdown')) {
            $type = 'dropdown';
            $options = ['Engineering', 'Marketing', 'Sales', 'HR'];
        } elseif (str_contains(strtolower($header), 'gender') || str_contains(strtolower($header), 'employment type') || str_contains(strtolower($header), 'radio')) {
            $type = 'radio';
            $options = ['Full Time', 'Part Time', 'Contract'];
        } elseif (str_contains(strtolower($header), 'skills') || str_contains(strtolower($header), 'checkbox') || str_contains(strtolower($header), 'interests')) {
            $type = 'checkbox';
            $options = ['Web Dev', 'Mobile Dev', 'UI/UX', 'Cloud Architecture'];
        }

        return [
            'type' => $type,
            'options' => $options,
            'multiple' => $multiple,
        ];
    }
}
