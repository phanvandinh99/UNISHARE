<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentRating;
use App\Models\FileUpload;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lecturer = User::role('lecturer')->first();
        $students = User::role('student')->take(10)->get();

        // Create lecturer documents (official)
        $lecturerDocuments = [
            [
                'title' => 'Introduction to Computer Science - Lecture Notes',
                'description' => 'Comprehensive lecture notes for CS101',
                'subject' => 'Computer Science',
                'course_code' => 'CS101',
                'is_official' => true,
                'is_approved' => true,
            ],
            [
                'title' => 'Data Structures and Algorithms - Course Syllabus',
                'description' => 'Official syllabus for CS201',
                'subject' => 'Computer Science',
                'course_code' => 'CS201',
                'is_official' => true,
                'is_approved' => true,
            ],
            [
                'title' => 'Database Systems - Assignment Guidelines',
                'description' => 'Guidelines for semester project',
                'subject' => 'Computer Science',
                'course_code' => 'CS301',
                'is_official' => true,
                'is_approved' => true,
            ],
        ];

        foreach ($lecturerDocuments as $docData) {
            $document = $this->createDocument($lecturer, $docData);

            // Add some ratings and comments
            foreach ($students->random(rand(3, 5)) as $student) {
                DocumentRating::create([
                    'document_id' => $document->id,
                    'user_id' => $student->id,
                    'rating' => rand(4, 5),
                    'review' => 'Very helpful document. Thanks professor!',
                ]);

                DocumentComment::create([
                    'document_id' => $document->id,
                    'user_id' => $student->id,
                    'content' => 'This document was very helpful for my studies. Thank you!',
                ]);
            }
        }

        // Create student documents
        $studentDocumentTitles = [
            'Study Notes for Midterm Exam',
            'Project Report',
            'Assignment Solution',
            'Research Paper Summary',
            'Lab Report',
            'Tutorial Notes',
            'Exam Preparation Guide',
        ];

        foreach ($students as $student) {
            $numDocs = rand(1, 3);

            for ($i = 0; $i < $numDocs; $i++) {
                $title = $studentDocumentTitles[array_rand($studentDocumentTitles)];
                $courseCode = 'CS' . rand(100, 400);

                $document = $this->createDocument($student, [
                    'title' => $title . ' - ' . $courseCode,
                    'description' => 'Student created document for ' . $courseCode,
                    'subject' => 'Computer Science',
                    'course_code' => $courseCode,
                    'is_official' => false,
                    'is_approved' => true,
                ]);

                // Add some ratings and comments
                foreach ($students->except($student->id)->random(rand(2, 4)) as $commenter) {
                    if (rand(0, 1)) {
                        DocumentRating::create([
                            'document_id' => $document->id,
                            'user_id' => $commenter->id,
                            'rating' => rand(3, 5),
                            'review' => 'Thanks for sharing these notes!',
                        ]);
                    }

                    if (rand(0, 1)) {
                        DocumentComment::create([
                            'document_id' => $document->id,
                            'user_id' => $commenter->id,
                            'content' => 'This was really helpful, thanks for sharing!',
                        ]);
                    }
                }
            }
        }
    }

    private function createDocument($user, $data)
    {
        // Create a fake file upload record
        $fileUpload = FileUpload::create([
            'user_id' => $user->id,
            'original_filename' => $data['title'] . '.pdf',
            'stored_filename' => Str::uuid() . '.pdf',
            'file_path' => 'uploads/' . $user->id . '/' . Str::uuid() . '.pdf',
            'file_type' => 'application/pdf',
            'file_size' => rand(100000, 5000000),
            'file_hash' => md5($data['title'] . rand(1000, 9999)),
            'status' => 'completed',
            'upload_session_id' => Str::uuid(),
            'chunks_total' => 1,
            'chunks_received' => 1,
        ]);

        // Create the document
        $document = Document::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'file_path' => $fileUpload->file_path,
            'file_name' => $fileUpload->original_filename,
            'file_type' => $fileUpload->file_type,
            'file_size' => $fileUpload->file_size,
            'file_hash' => $fileUpload->file_hash,
            'subject' => $data['subject'],
            'course_code' => $data['course_code'],
            'is_official' => $data['is_official'],
            'is_approved' => $data['is_approved'],
            'download_count' => rand(0, 100),
            'view_count' => rand(10, 200),
        ]);

        // Link the file upload to the document
        $fileUpload->update([
            'uploadable_id' => $document->id,
            'uploadable_type' => 'App\\Models\\Document',
        ]);

        return $document;
    }
}
