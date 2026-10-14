<?php

declare(strict_types=1);

use App\Modules\Profiles\Domain\Data\SkillClaim;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Services\SkillMerger;

function merger(): SkillMerger
{
    return new SkillMerger(['declared' => 0.5, 'cv' => 0.7, 'linkedin' => 0.75, 'github' => 0.9]);
}

it('marks declared-only skills and evidenced ones', function (): void {
    $skills = merger()->merge([
        new SkillClaim('php', 'PHP', SkillSource::Declared, level: 4),
        new SkillClaim('rust', 'Rust', SkillSource::Github, level: 3),
    ]);

    $bySlug = collect($skills)->keyBy('skill');

    expect($bySlug['php']->hasEvidence())->toBeFalse()
        ->and($bySlug['php']->isDeclared())->toBeTrue()
        ->and($bySlug['rust']->hasEvidence())->toBeTrue()
        ->and($bySlug['rust']->isDeclared())->toBeFalse();
});

it('grows confidence with each independent source', function (): void {
    [$skill] = merger()->merge([
        new SkillClaim('laravel', 'Laravel', SkillSource::Declared, level: 4),
        new SkillClaim('laravel', 'Laravel', SkillSource::Github, level: 4),
        new SkillClaim('laravel', 'Laravel', SkillSource::Linkedin),
    ]);

    // 1 - (0.5 × 0.1 × 0.25)
    expect($skill->confidence)->toBe(0.99)
        ->and($skill->sources)->toBe([SkillSource::Declared, SkillSource::Github, SkillSource::Linkedin]);
});

it('keeps the declared level but exposes the gap with the evidence', function (): void {
    [$skill] = merger()->merge([
        new SkillClaim('react', 'React', SkillSource::Github, level: 2, years: 1.5, lastUsedYear: 2024),
        new SkillClaim('react', 'React', SkillSource::Declared, level: 5, years: 6.0, lastUsedYear: 2023),
    ]);

    expect($skill->level)->toBe(5)
        ->and($skill->declaredLevel)->toBe(5)
        ->and($skill->evidenceLevel)->toBe(2)
        ->and($skill->levelGap)->toBe(3)
        ->and($skill->years)->toBe(6.0)
        ->and($skill->lastUsedYear)->toBe(2024);
});

it('falls back to the evidence level without a declared one', function (): void {
    [$skill] = merger()->merge([
        new SkillClaim('docker', 'Docker', SkillSource::Cv),
        new SkillClaim('docker', 'Docker', SkillSource::Github, level: 3),
    ]);

    expect($skill->level)->toBe(3)->and($skill->levelGap)->toBeNull();
});

it('prefers the user\'s spelling, then the most trusted source\'s', function (): void {
    [$declared] = merger()->merge([
        new SkillClaim('unreal-engine', 'Unreal', SkillSource::Github),
        new SkillClaim('unreal-engine', 'Unreal Engine 5', SkillSource::Declared, level: 3),
    ]);
    [$evidence] = merger()->merge([
        new SkillClaim('godot', 'godot', SkillSource::Cv),
        new SkillClaim('godot', 'Godot', SkillSource::Github),
    ]);

    expect($declared->name)->toBe('Unreal Engine 5')->and($evidence->name)->toBe('Godot');
});

it('orders the strongest skills first', function (): void {
    $skills = merger()->merge([
        new SkillClaim('css', 'CSS', SkillSource::Declared, level: 2),
        new SkillClaim('php', 'PHP', SkillSource::Declared, level: 5),
        new SkillClaim('php', 'PHP', SkillSource::Github, level: 5),
        new SkillClaim('go', 'Go', SkillSource::Github, level: 3),
    ]);

    expect(array_map(fn ($skill) => $skill->skill, $skills))->toBe(['php', 'go', 'css']);
});

it('returns nothing without claims', function (): void {
    expect(merger()->merge([]))->toBe([]);
});
