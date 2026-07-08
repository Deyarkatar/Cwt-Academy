<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_channels', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_channels', 'telegram_url')) {
                $table->string('telegram_url', 255)->nullable()->after('private_channel_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_channels', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_channels', 'telegram_url')) {
                $table->dropColumn('telegram_url');
            }
        });
    }
};
