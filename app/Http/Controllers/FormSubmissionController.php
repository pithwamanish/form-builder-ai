<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionController extends Controller
{
    public function index(Request $request, string $uuid)
    {
        $form = Form::where('uuid', $uuid)->firstOrFail();
        
        $query = FormSubmission::where('form_id', $form->id)->orderBy('created_at', 'desc');
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('submission_data', 'LIKE', "%{$search}%");
        }

        $submissions = $query->paginate(15);

        return view('submissions.index', compact('form', 'submissions'));
    }

    public function downloadFile(string $uuid, int $submissionId, string $fieldKey)
    {
        $form = Form::where('uuid', $uuid)->firstOrFail();
        $submission = FormSubmission::where('form_id', $form->id)->where('id', $submissionId)->firstOrFail();

        $path = $submission->submission_data[$fieldKey] ?? null;

        if (!$path) {
            abort(404, 'Uploaded file path not found.');
        }

        $fileName = basename(parse_url($path, PHP_URL_PATH));
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

        // 1. High-Availability Local Stream & Flysystem Disk Check
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path);
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path);
        }

        $localPaths = [
            storage_path('app/form-uploads/' . $fileName),
            storage_path('app/public/form-uploads/' . $fileName),
            storage_path('app/' . $path),
            storage_path('app/public/' . $path),
        ];

        foreach ($localPaths as $lPath) {
            if ($lPath && file_exists($lPath) && is_file($lPath)) {
                return response()->download($lPath, $fileName, [
                    'Content-Type' => $contentType,
                ]);
            }
        }

        // 2. Cloudinary Remote Stream / Redirect
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
            $isImage = in_array($ext, $imageExtensions);
            $fetchUrls = [$path];

            if (!$isImage && str_contains($path, '/image/upload/')) {
                array_unshift($fetchUrls, str_replace('/image/upload/', '/raw/upload/', $path));
            }

            foreach ($fetchUrls as $fetchUrl) {
                try {
                    $response = Http::get($fetchUrl);
                    if ($response->successful() && strlen($response->body()) > 0 && !str_contains($response->body(), 'show_original_unsupported_file_format')) {
                        return response($response->body(), 200, [
                            'Content-Type' => $contentType,
                            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                        ]);
                    }
                } catch (\Exception $e) {
                    // Try next URL
                }
            }

            return redirect()->away($path);
        }

        abort(404, 'Uploaded file not found on storage.');
    }

    public function exportCsv(string $uuid)
    {
        $form = Form::where('uuid', $uuid)->firstOrFail();
        $submissions = FormSubmission::where('form_id', $form->id)->orderBy('created_at', 'asc')->get();

        $filename = "{$form->slug}_submissions.csv";

        $handle = fopen('php://temp', 'r+');
        // Add UTF-8 BOM for Excel compatibility
        fputs($handle, "\xEF\xBB\xBF");

        // Header row from field labels
        $fieldKeys = [];
        $csvHeaders = ['Submission ID', 'Submitted At', 'IP Address'];

        foreach ($form->schema['sections'] ?? [] as $sec) {
            foreach ($sec['fields'] ?? [] as $f) {
                $fieldKeys[] = [
                    'key' => $f['key'],
                    'type' => $f['type'],
                ];
                $csvHeaders[] = $f['label'] ?? $f['key'];
            }
        }

        fputcsv($handle, $csvHeaders);

        foreach ($submissions as $sub) {
            $row = [
                $sub->id,
                $sub->created_at ? $sub->created_at->toDateTimeString() : '',
                $sub->ip_address,
            ];

            foreach ($fieldKeys as $fk) {
                $key = $fk['key'];
                $type = $fk['type'];
                $val = $sub->submission_data[$key] ?? '';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                } elseif ($type === 'file' && !empty($val)) {
                    $val = route('forms.submissions.download', ['uuid' => $form->uuid, 'submissionId' => $sub->id, 'fieldKey' => $key]);
                }
                $row[] = $val;
            }

            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
