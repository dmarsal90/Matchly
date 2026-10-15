<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Localization;

use Illuminate\Contracts\Config\Repository;

final readonly class SupportedLocales
{
    public function __construct(private Repository $config) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        /** @var list<string> $locales */
        $locales = $this->config->get('app.supported_locales', []);

        return $locales;
    }

    public function supports(string $locale): bool
    {
        return in_array($locale, $this->all(), true);
    }

    public function default(): string
    {
        $default = (string) $this->config->get('app.locale');

        return $this->supports($default) ? $default : ($this->all()[0] ?? 'en');
    }
}
