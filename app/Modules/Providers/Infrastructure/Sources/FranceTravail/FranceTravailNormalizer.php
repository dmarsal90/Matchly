<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\FranceTravail;

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use App\Modules\Providers\Infrastructure\Sources\Support\Text;

final class FranceTravailNormalizer implements JobNormalizer
{
    public function normalize(RawJob $raw): ?JobDraft
    {
        $job = Payload::of($raw->payload);

        $title = Text::clean($job->string('intitule'));
        $url = $job->string('contact.urlPostulation') ?? $job->string('origineOffre.urlOrigine');

        if ($title === null || $url === null) {
            return null;
        }

        $type = $job->string('typeContrat');

        return new JobDraft(
            source: $raw->source,
            externalId: $raw->externalId,
            title: $title,
            // Many offers come through agencies and hide the employer.
            company: Text::clean($job->string('entreprise.nom')) ?? 'Entreprise confidentielle',
            description: '<p>'.e($job->string('description') ?? '').'</p>',
            applyUrl: $url,
            sourceUrl: $job->string('origineOffre.urlOrigine') ?? $url,
            categoryHint: $job->string('romeLibelle'),
            locationText: $job->string('lieuTravail.libelle') ?? 'France',
            remoteScope: RemoteScope::CountryRestricted,
            allowedRegions: ['FR'],
            employmentType: match (true) {
                $type === 'CDI' => EmploymentType::FullTime,
                $type === 'CDD', $type === 'MIS' => EmploymentType::Temporary,
                default => EmploymentType::fromLabel($job->string('typeContratLibelle')),
            },
            salaryText: $job->string('salaire.libelle'),
            postedAt: $job->date('dateCreation'),
            remoteByDefault: true,
        );
    }
}
