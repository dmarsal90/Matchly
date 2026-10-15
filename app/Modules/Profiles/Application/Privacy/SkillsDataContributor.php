<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Privacy;

use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;

final readonly class SkillsDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        return new PersonalData('skills', [
            'skills' => UserSkill::query()->where('user_id', $userId)->orderBy('source')->orderBy('skill')->get()
                ->map(fn (UserSkill $skill): array => [
                    'source' => $skill->source->value,
                    'skill' => $skill->skill,
                    'name' => $skill->name,
                    'level' => $skill->level,
                    'years' => $skill->years,
                    'last_used_year' => $skill->last_used_year,
                    'evidence' => $skill->evidence,
                ])->values()->all(),
            'imports' => ProfileImport::query()->where('user_id', $userId)->orderBy('id')->get()
                ->map(fn (ProfileImport $import): array => [
                    'source' => $import->source->value,
                    'status' => $import->status->value,
                    'reference' => $import->reference,
                    'imported_at' => $import->created_at->toIso8601String(),
                    'confirmed_at' => $import->confirmed_at?->toIso8601String(),
                    'data' => $import->payload,
                ])->values()->all(),
        ]);
    }

    public function erase(int $userId): void
    {
        // Rows cascade with the account; nothing lives outside the database.
    }
}
