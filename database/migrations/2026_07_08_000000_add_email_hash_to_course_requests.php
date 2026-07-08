<?php

use App\Models\CourseRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->string('student_email_hash', 64)->nullable()->after('student_email')->index();
        });

        // Backfill hashes for existing rows. The cast decrypts the stored value
        // so we can hash the raw email address.
        DB::table('course_requests')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                $model = CourseRequest::query()->find($row->id);
                if ($model) {
                    $model->forceFill([
                        'student_email_hash' => hash('sha256', strtolower((string) $model->student_email)),
                    ])->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_requests', function (Blueprint $table) {
            $table->dropColumn('student_email_hash');
        });
    }
};
