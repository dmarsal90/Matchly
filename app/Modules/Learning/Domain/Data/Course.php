<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Data;

use App\Modules\Learning\Domain\Enums\CoursePrice;

/** One place to learn a skill: a hand-picked resource, or a search on a learning site. */
final readonly class Course
{
    public function __construct(
        public string $title,
        public string $provider,
        public string $url,
        public CoursePrice $price,
        /** True for a hand-picked resource, false for a search link. */
        public bool $curated,
    ) {}

    /**
     * @return array{title: string, provider: string, url: string, price: string, curated: bool}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'provider' => $this->provider, 'url' => $this->url, 'price' => $this->price->value, 'curated' => $this->curated];
    }
}
