<?php

namespace Database\Seeders;

use App\Enums\InstructorStatus;
use App\Models\Instructor;
use Illuminate\Database\Seeder;

class InstructorSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = [
            [
                'name' => 'Dilan Aziz',
                'email' => 'dilan@cwtacademy.local',
                'phone' => '+9647501234567',
                'bio' => 'Senior software engineer with 10+ years of experience.',
                'status' => InstructorStatus::APPROVED,
            ],
            [
                'name' => 'Lanya Mohammed',
                'email' => 'lanya@cwtacademy.local',
                'phone' => '+9647507654321',
                'bio' => 'Professional designer and art director.',
                'status' => InstructorStatus::APPROVED,
            ],
        ];

        foreach ($instructors as $instructor) {
            Instructor::create($instructor);
        }
    }
}
