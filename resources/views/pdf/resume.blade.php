@php
    /** @var \App\Modules\Applications\Domain\Data\ResumeData $resume */
    $t = fn (string $key, array $params = []): string => (string) __("quick_apply.resume.{$key}", $params, $locale);
    $period = function (?string $from, ?string $to) use ($t): string {
        $format = function (?string $value) use ($t): string {
            if ($value === null) {
                return '';
            }
            [$year, $month] = array_pad(explode('-', $value), 2, null);

            return $month !== null ? sprintf('%s/%s', $month, $year) : $year;
        };

        return trim($format($from).' – '.($to !== null ? $format($to) : $t('present')), ' –');
    };
    $languageName = fn (string $code): string => \Locale::getDisplayLanguage($code, $locale) ?: $code;
    $look = match ($template) {
        \App\Modules\Applications\Domain\Enums\ResumeTemplate::Modern => ['margin' => '20mm 18mm', 'size' => '10pt', 'leading' => '1.5', 'name' => '22pt', 'accent' => '#1e3a8a', 'rule' => '#1e3a8a', 'gap' => '16pt'],
        \App\Modules\Applications\Domain\Enums\ResumeTemplate::Compact => ['margin' => '12mm 13mm', 'size' => '9pt', 'leading' => '1.3', 'name' => '17pt', 'accent' => '#1a1a1a', 'rule' => '#999', 'gap' => '9pt'],
        default => ['margin' => '18mm 16mm', 'size' => '10pt', 'leading' => '1.4', 'name' => '20pt', 'accent' => '#1a1a1a', 'rule' => '#999', 'gap' => '14pt'],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $resume->contact['name'] }}</title>
    <style>
        @page { margin: {{ $look['margin'] }}; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $look['size'] }}; line-height: {{ $look['leading'] }}; color: #1a1a1a; }
        h1 { font-size: {{ $look['name'] }}; margin: 0 0 2pt; font-weight: bold; color: {{ $look['accent'] }}; }
        .headline { font-size: 11pt; color: #444; margin: 0 0 6pt; }
        .contact { font-size: 9pt; color: #333; margin: 0 0 10pt; }
        h2 { font-size: 11pt; text-transform: uppercase; letter-spacing: 0.5pt; color: {{ $look['accent'] }}; border-bottom: 1px solid {{ $look['rule'] }}; padding-bottom: 2pt; margin: {{ $look['gap'] }} 0 6pt; }
        h3 { font-size: 10.5pt; margin: 8pt 0 0; font-weight: bold; }
        .meta { font-size: 9pt; color: #555; margin: 0 0 3pt; }
        ul { margin: 2pt 0 0 14pt; padding: 0; }
        li { margin: 0 0 2pt; }
        p { margin: 0 0 4pt; }
    </style>
</head>
<body>
    <h1>{{ $resume->contact['name'] }}</h1>
    @if ($resume->headline)
        <p class="headline">{{ $resume->headline }}</p>
    @endif
    <p class="contact">
        {{ collect([$resume->contact['email'], $resume->contact['phone'], $resume->contact['location'], ...array_column($resume->contact['links'], 'url')])->filter()->implode(' · ') }}
    </p>

    @if ($resume->summary)
        <h2>{{ $t('summary') }}</h2>
        <p>{{ $resume->summary }}</p>
    @endif

    @if ($resume->experience !== [])
        <h2>{{ $t('experience') }}</h2>
        @foreach ($resume->experience as $position)
            <h3>{{ $position['title'] }}@if ($position['company']) — {{ $position['company'] }}@endif</h3>
            <p class="meta">{{ collect([$period($position['started_on'], $position['finished_on']), $position['location']])->filter()->implode(' · ') }}</p>
            @if ($position['bullets'] !== [])
                <ul>
                    @foreach ($position['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    @endif

    @if ($resume->skills !== [])
        <h2>{{ $t('skills') }}</h2>
        <p>{{ implode(' · ', $resume->skills) }}</p>
    @endif

    @if ($resume->education !== [])
        <h2>{{ $t('education') }}</h2>
        @foreach ($resume->education as $item)
            <h3>{{ $item['degree'] ? $item['degree'].' — '.$item['school'] : $item['school'] }}</h3>
            <p class="meta">{{ $period($item['started_on'], $item['finished_on']) }}</p>
        @endforeach
    @endif

    @if ($resume->certifications !== [])
        <h2>{{ $t('certifications') }}</h2>
        <ul>
            @foreach ($resume->certifications as $item)
                <li>{{ $item['name'] }}@if ($item['authority']) — {{ $item['authority'] }}@endif</li>
            @endforeach
        </ul>
    @endif

    @if ($resume->projects !== [])
        <h2>{{ $t('projects') }}</h2>
        @foreach ($resume->projects as $item)
            <h3>{{ $item['title'] }}</h3>
            @if ($item['description'])<p>{{ $item['description'] }}</p>@endif
            @if ($item['url'])<p class="meta">{{ $item['url'] }}</p>@endif
        @endforeach
    @endif

    @if ($resume->languages !== [])
        <h2>{{ $t('languages') }}</h2>
        <p>{{ collect($resume->languages)->map(fn (array $language): string => $languageName($language['code']).' ('.__('profile.levels.'.$language['level'], [], $locale).')')->implode(' · ') }}</p>
    @endif
</body>
</html>
