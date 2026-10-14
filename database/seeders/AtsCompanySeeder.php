<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Providers\Infrastructure\Models\AtsCompany;
use Illuminate\Database\Seeder;

/**
 * Starter list of public ATS boards with remote-friendly openings (identifiers
 * verified against each API). Admins manage the list from the admin panel.
 */
final class AtsCompanySeeder extends Seeder
{
    private const array COMPANIES = [
        ['greenhouse', 'gitlab', 'GitLab', 'https://about.gitlab.com'],
        ['greenhouse', 'canonical', 'Canonical', 'https://canonical.com'],
        ['greenhouse', 'grafanalabs', 'Grafana Labs', 'https://grafana.com'],
        ['greenhouse', 'mozilla', 'Mozilla', 'https://www.mozilla.org'],
        ['greenhouse', 'elastic', 'Elastic', 'https://www.elastic.co'],
        ['greenhouse', 'wikimedia', 'Wikimedia Foundation', 'https://wikimediafoundation.org'],
        ['lever', 'binance', 'Binance', 'https://www.binance.com'],
        ['lever', 'toptal', 'Toptal', 'https://www.toptal.com'],
        ['ashby', 'linear', 'Linear', 'https://linear.app'],
        ['ashby', 'posthog', 'PostHog', 'https://posthog.com'],
        ['ashby', 'zapier', 'Zapier', 'https://zapier.com'],
        ['ashby', 'supabase', 'Supabase', 'https://supabase.com'],
        ['workable', 'huggingface', 'Hugging Face', 'https://huggingface.co'],
        ['smartrecruiters', 'Canva', 'Canva', 'https://www.canva.com'],
        ['smartrecruiters', 'Experian', 'Experian', 'https://www.experian.com'],
    ];

    public function run(): void
    {
        foreach (self::COMPANIES as [$platform, $identifier, $name, $website]) {
            AtsCompany::query()->updateOrCreate(
                ['platform' => $platform, 'identifier' => $identifier],
                ['name' => $name, 'website_url' => $website],
            );
        }
    }
}
