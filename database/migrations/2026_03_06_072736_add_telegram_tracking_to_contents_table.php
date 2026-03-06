<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->integer('send_attempts')->default(0)->after('sent_at');
            $table->text('send_error')->nullable()->after('send_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'send_attempts', 'send_error']);
        });
    }
};
