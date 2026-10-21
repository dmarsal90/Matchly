<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Support\Facades\Cache;

it('serves robots.txt pointing at the sitemap', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'), false);
});

it('serves a sitemap with the public pages', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('jobs.index'), false);
});

it('lists open offers in the sitemap and leaves out the rest', function () {
    $open = JobListing::factory()->create();
    $closed = JobListing::factory()->create(['status' => JobStatus::Closed]);

    Cache::forget('sitemap.xml');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(route('jobs.show', ['job' => $open->id, 'slug' => $open->slug()]), false)
        ->assertDontSee('/jobs/'.$closed->id.'/', false);
});
