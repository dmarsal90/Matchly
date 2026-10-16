<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Services\CompanyFactExtractor;
use App\Modules\Shared\Domain\Services\RobotsTxt;

it('follows robots.txt: the group for our agent, else the wildcard, longest rule wins', function (): void {
    $robots = RobotsTxt::parse("User-agent: *\nDisallow: /private\nAllow: /private/open\nDisallow: /tmp*.html$\n\nUser-agent: OtherBot\nDisallow: /\n", 'AcmeBot');

    expect($robots->allows('/'))->toBeTrue()
        ->and($robots->allows('/about'))->toBeTrue()
        ->and($robots->allows('/private/data'))->toBeFalse()
        ->and($robots->allows('/private/open/page'))->toBeTrue()
        ->and($robots->allows('/tmp-1.html'))->toBeFalse()
        ->and($robots->allows('/tmp-1.htmlx'))->toBeTrue();

    $named = RobotsTxt::parse("User-agent: *\nAllow: /\n\nUser-agent: AcmeBot\nUser-agent: Other\nDisallow: /about\n", 'AcmeBot/1.0');
    expect($named->allows('/about'))->toBeFalse()->and($named->allows('/careers'))->toBeTrue();

    expect(RobotsTxt::parse('', 'AcmeBot')->allows('/anything'))->toBeTrue()
        ->and(RobotsTxt::parse("User-agent: *\nDisallow:\n", 'AcmeBot')->allows('/x'))->toBeTrue()
        ->and(RobotsTxt::parse("User-agent: *\nDisallow: /\n", 'AcmeBot')->allows('/x'))->toBeFalse();
});

it('extracts the company’s own sentences and skips page furniture and contact data', function (): void {
    $html = <<<'HTML'
    <html><head>
      <meta name="description" content="We build open-source observability tools for small engineering teams.">
      <script>var x = "This is some script text that is long enough to pass the filter.";</script>
    </head><body>
      <nav><p>Navigation paragraph that should never be taken as a fact okay.</p></nav>
      <main>
        <p>Founded in 2019, Globex is a remote-first company with a team spread across twelve countries.</p>
        <p>Write to us at hello@globex.example or call +34 600 123 456 for any question you might have.</p>
        <p>We use cookies to improve your experience on this website, see our privacy policy.</p>
        <p>Short.</p>
        <p>Our public roadmap lists what we are building next and everyone can vote on it, which keeps us honest about priorities and also helps customers plan around what is coming in the next releases of the product.</p>
      </main>
      <footer><p>© 2026 Globex. All rights reserved and some more words here.</p></footer>
    </body></html>
    HTML;

    $facts = (new CompanyFactExtractor)->extract($html);

    expect($facts[0])->toBe('We build open-source observability tools for small engineering teams.')
        ->and($facts)->toContain('Founded in 2019, Globex is a remote-first company with a team spread across twelve countries.')
        ->and(implode(' ', $facts))->not->toContain('hello@')->not->toContain('cookies')->not->toContain('Navigation')->not->toContain('script')->not->toContain('©')
        // Long paragraphs are cut at a sentence or word boundary, never mid-word.
        ->and(collect($facts)->every(fn (string $fact): bool => mb_strlen($fact) <= 281))->toBeTrue();
});

it('returns nothing for empty or broken pages', function (): void {
    expect((new CompanyFactExtractor)->extract(''))->toBe([])
        ->and((new CompanyFactExtractor)->extract('<p>unclosed <b>tags'))->toBe([]);
});
