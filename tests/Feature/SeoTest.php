<?php

declare(strict_types=1);

it('serves robots.txt pointing at the sitemap', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'), false);
});

it('serves a sitemap with the public pages', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('jobs.index'), false);
});
