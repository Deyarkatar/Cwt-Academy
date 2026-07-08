<?php

namespace App\Notifications;

use App\Models\CourseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CourseRequestApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CourseRequest $courseRequest,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $courseTitle = $this->courseRequest->course->title ?? 'a course';

        return [
            'title' => 'Course Request Approved',
            'body' => "Your request for {$courseTitle} has been approved.",
            'data' => [
                'course_request_id' => $this->courseRequest->id,
                'tracking_code' => $this->courseRequest->public_tracking_code,
                'course_title' => $this->courseRequest->course->title ?? '',
            ],
        ];
    }
}
