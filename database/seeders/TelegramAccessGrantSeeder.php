<?php

namespace Database\Seeders;

use App\Enums\TelegramAccessGrantStatus;
use App\Models\CourseRequest;
use App\Models\TelegramAccessGrant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TelegramAccessGrantSeeder extends Seeder
{
    public function run(): void
    {
        $approvedRequests = CourseRequest::where('status', 'APPROVED')->get();

        if ($approvedRequests->isEmpty()) {
            return;
        }

        // Pending manual add
        $pending = $approvedRequests->first();
        TelegramAccessGrant::create([
            'course_request_id' => $pending->id,
            'course_id' => $pending->course_id,
            'student_name' => $pending->student_name,
            'student_email' => $pending->student_email,
            'student_phone' => $pending->student_phone,
            'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD,
            'admin_note' => 'Waiting for manual addition to course Telegram channel',
        ]);

        // Manually added
        $added = $approvedRequests->skip(1)->first();
        if ($added) {
            TelegramAccessGrant::create([
                'course_request_id' => $added->id,
                'course_id' => $added->course_id,
                'student_name' => $added->student_name,
                'student_email' => $added->student_email,
                'student_phone' => $added->student_phone,
                'status' => TelegramAccessGrantStatus::MANUALLY_ADDED,
                'admin_note' => 'Student added to private Telegram channel on '.now()->format('Y-m-d'),
                'granted_at' => now()->subDays(2),
                'manual_access_reference' => 'manual-add-ref-'.strtoupper(Str::random(6)),
            ]);
        }

        // Revoked access
        $revoked = $approvedRequests->skip(2)->first();
        if ($revoked) {
            TelegramAccessGrant::create([
                'course_request_id' => $revoked->id,
                'course_id' => $revoked->course_id,
                'student_name' => $revoked->student_name,
                'student_email' => $revoked->student_email,
                'student_phone' => $revoked->student_phone,
                'status' => TelegramAccessGrantStatus::REVOKED,
                'admin_note' => 'Access revoked due to policy violation',
                'revoked_at' => now()->subDay(),
                'revoked_reason' => 'Policy violation',
            ]);
        }
    }
}
