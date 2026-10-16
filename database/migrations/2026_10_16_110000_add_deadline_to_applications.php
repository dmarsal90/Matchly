<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* A date the person sets (apply by, answer an offer by) and when they were warned about it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->date('deadline_on')->nullable()->after('applied_at');
            $table->timestampTz('deadline_reminded_at')->nullable()->after('deadline_on');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['deadline_on', 'deadline_reminded_at']);
        });
    }
};
