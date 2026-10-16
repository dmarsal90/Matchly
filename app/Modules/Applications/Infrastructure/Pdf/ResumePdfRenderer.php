<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Pdf;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ResumeTemplate;
use Barryvdh\DomPDF\PDF;

/**
 * ATS-friendly PDF: one column, standard fonts, real (selectable) text,
 * conventional section headings in the CV's language, no tables, images or
 * text in graphics that parsers cannot read.
 */
final readonly class ResumePdfRenderer
{
    public function __construct(private PDF $pdf) {}

    public function render(ResumeData $resume, string $language, ResumeTemplate $template = ResumeTemplate::Classic): string
    {
        return $this->pdf
            ->loadView('pdf.resume', ['resume' => $resume, 'locale' => $language, 'template' => $template])
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->output();
    }
}
