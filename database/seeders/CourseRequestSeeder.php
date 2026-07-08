<?php

namespace Database\Seeders;

use App\Enums\CourseRequestStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseRequestSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::all();

        if ($courses->isEmpty()) {
            return;
        }

        // Pending payment request
        CourseRequest::create([
            'course_id' => $courses->first()->id,
            'public_tracking_code' => strtoupper(Str::random(16)),
            'student_name' => 'Ahmed Khalil',
            'student_email' => 'ahmed@example.local',
            'student_phone' => '+9647501111111',
            'student_city' => 'Erbil',
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'student_note' => 'Interested in IT Security',
        ]);

        // Pending review request with payment proof
        $reviewRequest = CourseRequest::create([
            'course_id' => $courses->first()->id,
            'public_tracking_code' => strtoupper(Str::random(16)),
            'student_name' => 'Sara Jamal',
            'student_email' => 'sara@example.local',
            'student_phone' => '+9647502222222',
            'student_city' => 'Sulaymaniyah',
            'status' => CourseRequestStatus::PENDING_REVIEW,
            'student_note' => 'Bank transfer completed',
        ]);

        // Approved request, waiting for manual Telegram add
        $approvedRequest = CourseRequest::create([
            'course_id' => $courses->skip(1)->first()->id ?? $courses->first()->id,
            'public_tracking_code' => strtoupper(Str::random(16)),
            'student_name' => 'Mohammed Hassan',
            'student_email' => 'mohammed@example.local',
            'student_phone' => '+9647503333333',
            'student_city' => 'Duhok',
            'status' => CourseRequestStatus::APPROVED,
            'student_note' => 'Payment confirmed',
        ]);

        // Approved + manually added to Telegram
        $manuallyAddedRequest = CourseRequest::create([
            'course_id' => $courses->first()->id,
            'public_tracking_code' => strtoupper(Str::random(16)),
            'student_name' => 'Layla Othman',
            'student_email' => 'layla@example.local',
            'student_phone' => '+9647504444444',
            'student_city' => 'Halabja',
            'status' => CourseRequestStatus::APPROVED,
            'student_note' => 'Already in Telegram group',
        ]);

        // Rejected request
        CourseRequest::create([
            'course_id' => $courses->skip(1)->first()->id ?? $courses->first()->id,
            'public_tracking_code' => strtoupper(Str::random(16)),
            'student_name' => 'Omar Faris',
            'student_email' => 'omar@example.local',
            'student_phone' => '+9647505555555',
            'student_city' => 'Kirkuk',
            'status' => CourseRequestStatus::REJECTED,
            'student_note' => 'Incomplete payment',
            'rejection_reason' => 'Payment amount does not match course price',
        ]);
    }
}
