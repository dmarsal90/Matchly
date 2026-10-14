<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->string('locale', 5)->nullable()->after('is_admin');
            $table->string('timezone', 64)->nullable()->after('locale');
            $table->timestampTz('last_active_at')->nullable()->after('timezone');
            // OAuth-only accounts have no password until they set one.
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_admin', 'locale', 'timezone', 'last_active_at']);
        });
    }
};
