<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Portfolio;

use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Shared\Domain\Services\RobotsTxt;
use App\Modules\Shared\Infrastructure\Http\PublicPageFetcher;

/**
 * Reads the person's own public site (the page they give plus a few common
 * ones on the same host) and lists the catalog technologies it mentions. It
 * obeys robots.txt, stays on their host and invents nothing: no levels, no
 * years, only what the text says and how often.
 */
final readonly class PortfolioReader
{
    /** Pages tried after the one given, when it is the site root. */
    private const array PATHS = ['/projects', '/portfolio', '/work', '/about'];

    private const int MAX_SKILLS = 40;

    private const int MAX_SECONDS = 40;

    public function __construct(private PublicPageFetcher $pages, private SkillCatalog $catalog) {}

    /**
     * @return array{url: string, pages: list<string>, skills: list<array<string, mixed>>}
     */
    public function read(string $url): array
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            throw ImportFailed::because(ImportFailed::NOT_FOUND);
        }

        $host = strtolower($parts['host']);
        $origin = strtolower($parts['scheme']).'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $robots = RobotsTxt::parse($this->pages->get($origin.'/robots.txt')['body'] ?? '', $this->pages->agent());

        $targets = [$url];
        if (in_array($parts['path'] ?? '/', ['', '/'], true)) {
            array_push($targets, ...array_map(fn (string $path): string => $origin.$path, self::PATHS));
        }

        $mentions = [];
        $read = [];
        // The job has a hard timeout: stop between pages instead of being killed mid-import.
        $deadline = microtime(true) + self::MAX_SECONDS;
        foreach ($targets as $target) {
            if (microtime(true) > $deadline) {
                break;
            }
            if (! $robots->allows((string) (parse_url($target, PHP_URL_PATH) ?: '/'))) {
                continue;
            }

            $page = $this->pages->get($target);
            // A redirect to another host (a social network, a hosting login) is not their site.
            if ($page === null || strtolower((string) parse_url($page['url'], PHP_URL_HOST)) !== $host || isset($read[$page['url']])) {
                continue;
            }
            $read[$page['url']] = true;

            foreach ($this->catalog->detectIn($this->text($page['body'])) as $slug => $count) {
                $mentions[$slug] = ($mentions[$slug] ?? 0) + $count;
            }
        }

        if ($read === []) {
            throw ImportFailed::because(ImportFailed::UNAVAILABLE);
        }

        arsort($mentions);
        $skills = [];
        foreach (array_slice($mentions, 0, self::MAX_SKILLS, true) as $slug => $count) {
            $skills[] = ['skill' => $slug, 'name' => $this->catalog->label($slug, $slug), 'level' => null, 'years' => null, 'last_used_year' => null, 'evidence' => ['mentions' => $count]];
        }

        if ($skills === []) {
            throw ImportFailed::because(ImportFailed::NOTHING_FOUND);
        }

        return ['url' => $url, 'pages' => array_keys($read), 'skills' => $skills];
    }

    /** The visible text of a page: no scripts, styles or markup (tags become spaces so neighbouring elements never glue into one word). */
    private function text(string $html): string
    {
        foreach (['script', 'style', 'noscript', 'svg'] as $tag) {
            $html = $this->dropBlocks($html, $tag);
        }

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** Linear scan (the page is hostile input, so no lazy regex that rescans to the end per unclosed tag). */
    private function dropBlocks(string $html, string $tag): string
    {
        $out = '';
        $pos = 0;
        while (($start = stripos($html, "<{$tag}", $pos)) !== false) {
            if (ctype_alnum($html[$start + strlen($tag) + 1] ?? ' ')) {
                $out .= substr($html, $pos, $start + 1 - $pos);
                $pos = $start + 1;

                continue;
            }
            $out .= substr($html, $pos, $start - $pos).' ';
            $end = stripos($html, "</{$tag}", $start);
            $close = $end === false ? false : strpos($html, '>', $end);
            $pos = $close === false ? strlen($html) : $close + 1;
        }

        return $out.substr($html, $pos);
    }
}
