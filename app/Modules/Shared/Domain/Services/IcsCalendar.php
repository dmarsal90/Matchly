<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Services;

use DateTimeImmutable;

/**
 * A minimal iCalendar (RFC 5545) writer for all-day events: CRLF line ends,
 * escaped text and lines folded at 75 octets, so any calendar app opens it.
 */
final class IcsCalendar
{
    /** @var list<array{uid: string, date: DateTimeImmutable, summary: string, description: string, url: string|null}> */
    private array $events = [];

    public function __construct(private readonly string $product, private readonly DateTimeImmutable $stamp) {}

    public function allDay(string $uid, DateTimeImmutable $date, string $summary, string $description = '', ?string $url = null): self
    {
        $this->events[] = ['uid' => $uid, 'date' => $date, 'summary' => $summary, 'description' => $description, 'url' => $url];

        return $this;
    }

    public function render(): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.$this->escape($this->product).'//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];

        foreach ($this->events as $event) {
            array_push(
                $lines,
                'BEGIN:VEVENT',
                'UID:'.$event['uid'],
                'DTSTAMP:'.$this->stamp->format('Ymd\THis\Z'),
                'DTSTART;VALUE=DATE:'.$event['date']->format('Ymd'),
                'DTEND;VALUE=DATE:'.$event['date']->modify('+1 day')->format('Ymd'),
                'SUMMARY:'.$this->escape($event['summary']),
            );
            if ($event['description'] !== '') {
                $lines[] = 'DESCRIPTION:'.$this->escape($event['description']);
            }
            if ($event['url'] !== null) {
                $lines[] = 'URL:'.$event['url'];
            }
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Lines longer than 75 octets continue on the next line after a space, never inside a UTF-8 character. */
    private function fold(string $line): string
    {
        $parts = [];
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $parts[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }
        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
