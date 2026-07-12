<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('ai_segment')->nullable()->after('payment_terms_days');
            $table->text('ai_segment_rationale')->nullable()->after('ai_segment');
            $table->timestamp('ai_segment_updated_at')->nullable()->after('ai_segment_rationale');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['ai_segment', 'ai_segment_rationale', 'ai_segment_updated_at']);
        });
    }
};
