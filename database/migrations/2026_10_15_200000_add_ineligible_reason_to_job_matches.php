<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_matches', function (Blueprint $table): void {
            // First reason a job is ineligible (location, remote_only, ...): lets the listing say why it hides jobs without reading every result.
            $table->string('ineligible_reason', 32)->nullable()->after('eligible');
            $table->index(['user_id', 'eligible', 'ineligible_reason']);
        });

        DB::table('job_matches')->where('eligible', false)->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $result = json_decode((string) $row->result, true);
                $key = is_array($result) ? ($result['ineligible_reasons'][0]['key'] ?? null) : null;
                if (is_string($key)) {
                    DB::table('job_matches')->where('id', $row->id)->update(['ineligible_reason' => substr($key, 0, 32)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_matches', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'eligible', 'ineligible_reason']);
            $table->dropColumn('ineligible_reason');
        });
    }
};
