<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Services\CourseCatalog;
use Illuminate\Support\Facades\Http;

it('only offers http(s) hand-picked URLs and answers for every skill in the shipped catalog', function (): void {
    $catalog = new CourseCatalog((array) config('courses.catalog'), (array) config('courses.search'));
    $skills = array_keys((array) config('skills.catalog'));

    foreach ($catalog->catalog() as $slug => $items) {
        expect($skills)->toContain($slug);
        foreach ($items as [$title, $provider, $url, $price]) {
            expect($url)->toStartWith('https://')->and($price)->toBeIn(['free', 'paid'])->and($title)->not->toBe('')->and($provider)->not->toBe('');
        }
        $first = $catalog->for($slug, $slug)[0];
        expect($first->price->value)->toBe('free');
    }
});

it('reports the resources that no longer answer, but not the ones behind a bot wall', function (): void {
    config(['courses.catalog' => [
        'vue' => [['Vue tutorial', 'vuejs.org', 'https://vuejs.example/ok', 'free'], ['Gone', 'x', 'https://gone.example/', 'free']],
        'linux' => [['Walled', 'y', 'https://walled.example/', 'free']],
    ]]);
    Http::fake([
        'vuejs.example/*' => Http::response('ok', 200),
        'gone.example/*' => Http::response('', 404),
        'walled.example/*' => Http::response('', 403),
    ]);

    $this->artisan('courses:check')->expectsOutputToContain('1 of 3 resources do not answer')->assertFailed();
});

it('succeeds when every resource answers', function (): void {
    config(['courses.catalog' => ['vue' => [['Vue tutorial', 'vuejs.org', 'https://vuejs.example/ok', 'free']]]]);
    Http::fake(['*' => Http::response('ok', 200)]);

    $this->artisan('courses:check')->expectsOutputToContain('checked, all answer')->assertSuccessful();
});
