<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\NoteOrigin;
use App\Modules\Applications\Domain\Services\CompanyFactExtractor;
use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Applications\Domain\Services\RobotsTxt;
use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Applications\Infrastructure\Web\PublicPageFetcher;
use App\Modules\Identity\Infrastructure\Models\User;

/**
 * Reads a few public pages of the company's own site (home, about, careers)
 * and proposes what they say about themselves as notes waiting for approval.
 * It obeys robots.txt, stays on the site's host and never uses a fact the
 * person has not approved.
 */
final readonly class DiscoverCompanyFacts
{
    /** Pages tried after the home page. */
    private const array PATHS = ['/about', '/about-us', '/company', '/careers'];

    private const int MAX_SUGGESTIONS = 6;

    private const int MAX_NOTES = 20;

    public function __construct(private PublicPageFetcher $pages, private CompanyFactExtractor $extractor) {}

    /**
     * @return array{added: int, pages: int, blocked: int}
     */
    public function handle(User $user, string $company, string $siteUrl): array
    {
        $key = CompanyKey::from($company);
        $parts = parse_url($siteUrl);
        if ($key === '' || ! isset($parts['scheme'], $parts['host'])) {
            return ['added' => 0, 'pages' => 0, 'blocked' => 0];
        }

        $origin = strtolower($parts['scheme']).'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $robots = $this->robots($origin);

        $known = CompanyNote::query()->where('user_id', $user->id)->where('company_key', $key)->get()->map(fn (CompanyNote $note): string => mb_strtolower($note->fact))->all();
        $room = min(self::MAX_SUGGESTIONS, self::MAX_NOTES - count($known));

        $added = 0;
        $read = 0;
        $blocked = 0;
        $seen = [];

        foreach (['/', ...self::PATHS] as $path) {
            if ($added >= $room) {
                break;
            }
            if (! $robots->allows($path)) {
                $blocked++;

                continue;
            }

            $page = $this->pages->get($origin.$path);
            // A redirect to another host (a social network, a parent company) is not their site.
            if ($page === null || strtolower((string) parse_url($page['url'], PHP_URL_HOST)) !== strtolower($parts['host']) || isset($seen[$page['url']])) {
                continue;
            }
            $seen[$page['url']] = true;
            $read++;

            foreach ($this->extractor->extract($page['body']) as $fact) {
                if ($added >= $room || in_array(mb_strtolower($fact), $known, true)) {
                    continue;
                }
                $note = new CompanyNote(['company_key' => $key, 'company' => $company, 'fact' => $fact, 'source_url' => $page['url'], 'origin' => NoteOrigin::Web, 'approved' => false]);
                $note->user()->associate($user);
                $note->save();
                $known[] = mb_strtolower($fact);
                $added++;
            }
        }

        return ['added' => $added, 'pages' => $read, 'blocked' => $blocked];
    }

    private function robots(string $origin): RobotsTxt
    {
        $file = $this->pages->get($origin.'/robots.txt');

        // No robots.txt (or unreadable): nothing is forbidden.
        return RobotsTxt::parse($file['body'] ?? '', $this->pages->agent());
    }
}
