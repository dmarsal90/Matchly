<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Http\Controllers;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** The public pages plus every listed offer, newest first. */
final class SitemapController
{
    // shortcut: one file holds at most 50,000 URLs (the protocol limit); split into a sitemap index when the board outgrows it.
    private const int LIMIT = 50_000;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $urls = [[route('home'), null], [route('jobs.index'), null], [route('legal.privacy'), null], [route('legal.terms'), null]];

            foreach (JobListing::query()->visible()->latestFirst()->limit(self::LIMIT - count($urls))->get(['id', 'title', 'company', 'sort_at', 'updated_at']) as $job) {
                $urls[] = [route('jobs.show', ['job' => $job->id, 'slug' => $job->slug()]), $job->updated_at];
            }

            $body = '';
            foreach ($urls as [$loc, $lastmod]) {
                $body .= '<url><loc>'.e($loc).'</loc>'.($lastmod !== null ? '<lastmod>'.$lastmod->toAtomString().'</lastmod>' : '').'</url>';
            }

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
