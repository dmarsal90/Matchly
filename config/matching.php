<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Compatibility score (0-100)
|--------------------------------------------------------------------------
|
| Weights of each category; they are normalised, so they need not add up to
| 100. The scorer is deterministic and explains every point it gives.
|
*/

return [

    'weights' => [
        'skills' => (int) env('MATCHING_WEIGHT_SKILLS', 40),
        'experience' => (int) env('MATCHING_WEIGHT_EXPERIENCE', 20),
        'role' => (int) env('MATCHING_WEIGHT_ROLE', 15),
        'location' => (int) env('MATCHING_WEIGHT_LOCATION', 10),
        'languages' => (int) env('MATCHING_WEIGHT_LANGUAGES', 5),
        'salary' => (int) env('MATCHING_WEIGHT_SALARY', 5),
        'education' => (int) env('MATCHING_WEIGHT_EDUCATION', 5),
    ],

    // Score bands.
    'levels' => [
        'high' => 75,
        'medium' => 50,
    ],

    // Within the skills category: share of required vs nice-to-have skills.
    'required_share' => 0.75,

    // A matched skill only declared (no GitHub/CV/LinkedIn evidence) counts this much.
    'declared_only_factor' => 0.8,

    // Years usually expected for a seniority when the posting gives none.
    'seniority_years' => [
        'intern' => 0, 'junior' => 1, 'mid' => 3, 'senior' => 5, 'lead' => 7, 'manager' => 7, 'director' => 10, 'executive' => 12,
    ],

    // Users scored per queued batch when new jobs arrive.
    'users_per_batch' => 50,

    // Seconds to wait after a profile change before rescoring (changes come in bursts).
    'recalculate_delay' => 15,

];
