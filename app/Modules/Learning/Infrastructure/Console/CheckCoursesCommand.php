<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Console;

use App\Modules\Learning\Domain\Services\CourseCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('courses:check')]
#[Description('Fetch every hand-picked learning resource and report the ones that no longer answer')]
final class CheckCoursesCommand extends Command
{
    public function handle(CourseCatalog $catalog): int
    {
        $dead = [];
        $checked = [];

        foreach ($catalog->catalog() as $slug => $items) {
            foreach ($items as [, , $url]) {
                if (isset($checked[$url])) {
                    continue;
                }
                $checked[$url] = true;

                try {
                    $status = Http::withUserAgent('Mozilla/5.0 (compatible; link-check)')->timeout(15)->connectTimeout(5)->get($url)->status();
                } catch (Throwable) {
                    $status = 0;
                }

                // 401/403/429 mean "a bot wall", not "gone": the page exists but refuses us.
                if ($status === 404 || $status === 410 || $status === 0 || $status >= 500) {
                    $dead[] = [$slug, $url, $status];
                }
            }
        }

        if ($dead === []) {
            $this->components->info(count($checked).' resources checked, all answer.');

            return self::SUCCESS;
        }

        $this->table(['Skill', 'URL', 'Status'], $dead);
        $this->components->error(count($dead).' of '.count($checked).' resources do not answer.');

        return self::FAILURE;
    }
}
