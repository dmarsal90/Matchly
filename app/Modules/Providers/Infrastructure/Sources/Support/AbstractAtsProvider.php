<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Support;

use App\Modules\Providers\Domain\Contracts\AtsCompanyDirectory;
use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Contracts\ReportsPartialFailures;
use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Polls one public ATS board per company. A failing company is recorded and
 * skipped so the others still get ingested.
 */
abstract class AbstractAtsProvider implements JobProvider, ReportsPartialFailures
{
    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        protected readonly ProviderHttpClient $client,
        protected readonly AtsCompanyDirectory $companies,
        protected readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return iterable<array<string, mixed>> raw postings of one company
     */
    abstract protected function fetchCompany(AtsCompanyRef $company): iterable;

    /**
     * @param  array<string, mixed>  $posting
     */
    abstract protected function externalId(array $posting): ?string;

    public function fetch(): iterable
    {
        $this->failures = [];
        $key = $this->definition()->key;

        foreach ($this->companies->enabledFor($key) as $company) {
            $count = 0;

            try {
                foreach ($this->fetchCompany($company) as $posting) {
                    $id = $this->externalId($posting);
                    if ($id === null) {
                        continue;
                    }

                    $count++;
                    yield new RawJob($key, "{$company->identifier}:{$id}", [
                        ...$posting,
                        '_company' => ['identifier' => $company->identifier, 'name' => $company->name],
                    ]);
                }

                $this->companies->markSucceeded($company->id, $count);
            } catch (Throwable $exception) {
                $this->failures[] = "{$company->identifier}: {$exception->getMessage()}";
                $this->companies->markFailed($company->id, $exception->getMessage());
                $this->logger->warning('ATS company fetch failed', [
                    'provider' => $key,
                    'company' => $company->identifier,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function failures(): array
    {
        return $this->failures;
    }

    protected function option(string $name): string
    {
        return (string) $this->config->get("jobs.providers.{$this->definition()->key}.options.{$name}");
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected function url(string $option, AtsCompanyRef $company, array $replace = []): string
    {
        return strtr($this->option($option), ['{company}' => rawurlencode($company->identifier), ...$replace]);
    }
}
