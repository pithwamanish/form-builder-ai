<?php

namespace Database\Seeders;

use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class FormTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'title' => 'Job Application Form',
                'category' => 'HR & Recruitment',
                'description' => 'Comprehensive candidate application form with personal details, experience, skills checklist, and resume upload.',
                'file' => 'job_application.json',
            ],
            [
                'title' => 'Customer Feedback Survey',
                'category' => 'Customer Success',
                'description' => 'Collect actionable customer feedback, star ratings, and improvement suggestions.',
                'file' => 'customer_feedback.json',
            ],
            [
                'title' => 'Event Registration Form',
                'category' => 'Events',
                'description' => 'Registrations form for conferences, webinars, or workshops with dietary and session preferences.',
                'file' => 'event_registration.json',
            ],
            [
                'title' => 'Contact & Inquiry Form',
                'category' => 'General',
                'description' => 'Standard website contact form with inquiry category dropdown and contact information.',
                'file' => 'contact_inquiry.json',
            ]
        ];

        foreach ($templates as $t) {
            $jsonPath = resource_path('json/templates/' . $t['file']);
            $schema = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : [];

            FormTemplate::updateOrCreate(
                ['title' => $t['title']],
                [
                    'title' => $t['title'],
                    'category' => $t['category'],
                    'description' => $t['description'],
                    'schema' => $schema,
                ]
            );
        }
    }
}
