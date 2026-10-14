<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Classifiers;

use App\Modules\Jobs\Domain\Contracts\WorkModeClassifier;
use App\Modules\Jobs\Domain\Data\Classification;
use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Domain\Services\TaxonomyClassifier;

/**
 * Rule-based classifier. Only the location text and the title are read: job
 * descriptions mention "remote" and country names far too loosely.
 */
final readonly class RuleBasedWorkModeClassifier implements WorkModeClassifier
{
    private const string HYBRID = '/\b(hybrid|hybride|híbrido|hibrido|teilweise remote|partially remote|remote[- ]first hybrid)\b/u';

    private const string REMOTE = '/\b(remote|remotely|anywhere|worldwide|work from home|wfh|telecommut\w*|distributed|home[- ]?based|home ?office|fully[- ]remote|remoto|teletrabajo|fernarbeit|homeoffice|à distance|remota)\b/u';

    private const string ONSITE = '/\b(on[- ]?site|in[- ]office|office[- ]based|vor ort|presencial|in person)\b/u';

    public function __construct(
        private RegionDictionary $regions,
        private TaxonomyClassifier $taxonomy,
    ) {}

    public function classify(JobDraft $draft): Classification
    {
        $markers = $this->titleMarkers($draft->title);
        // Title decorations carry geography too: "ML Engineer - EMEA Remote", "Designer (Remote, LATAM)".
        $locationText = trim(($draft->locationText ?? '').($markers !== '' ? ' | '.$markers : ''), ' |');
        $workMode = $draft->workMode ?? $this->workMode(mb_strtolower($locationText), $draft->remoteByDefault);

        $explicit = array_values(array_filter(array_map($this->regions->resolve(...), $draft->allowedRegions)));
        $allowedRegions = array_values(array_unique([...$explicit, ...$this->regions->extract($locationText)]));

        $role = $this->taxonomy->role($draft->title);

        return new Classification(
            workMode: $workMode,
            remoteScope: $workMode === WorkMode::Onsite ? null : ($draft->remoteScope ?? $this->scope($locationText, $allowedRegions)),
            allowedRegions: $this->regions->isWorldwide($locationText) && $explicit === [] ? [] : $allowedRegions,
            category: $this->taxonomy->category($role, $draft->categoryHint),
            role: $role,
            seniority: $this->taxonomy->seniority($draft->title),
        );
    }

    private function workMode(string $text, bool $remoteByDefault): WorkMode
    {
        return match (true) {
            preg_match(self::HYBRID, $text) === 1 => WorkMode::Hybrid,
            preg_match(self::REMOTE, $text) === 1 => WorkMode::Remote,
            preg_match(self::ONSITE, $text) === 1 => WorkMode::Onsite,
            $remoteByDefault => WorkMode::Remote,
            default => WorkMode::Onsite,
        };
    }

    /**
     * Only the decorations of a title count ("Engineer (Remote)", "Designer - Hybrid",
     * "[Remote, EU]"): "Hybrid Cloud Engineer" is not a hybrid job.
     */
    private function titleMarkers(string $title): string
    {
        preg_match_all('/[(\[]([^)\]]*)[)\]]|\s[-–|:]\s*([^-–|:]*\b(?:remote|hybrid|on[- ]?site)\b[^-–|:]*)$/iu', $title, $matches);

        return implode(' ', array_filter([...$matches[1], ...$matches[2]]));
    }

    /**
     * @param  list<string>  $allowedRegions
     */
    private function scope(string $locationText, array $allowedRegions): ?RemoteScope
    {
        $countries = array_filter($allowedRegions, fn (string $code): bool => Region::tryFrom($code) === null);
        $hasRegions = count($countries) < count($allowedRegions);

        return match (true) {
            $allowedRegions === [] && $this->regions->isWorldwide($locationText) => RemoteScope::Worldwide,
            $hasRegions => RemoteScope::Region,
            $countries !== [] => RemoteScope::CountryRestricted,
            // "Remote" with no geography: the source did not say, which is not the same as worldwide.
            default => null,
        };
    }
}
