<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Html;

use App\Modules\Shared\Domain\Contracts\HtmlSanitizer;
use HTMLPurifier;
use HTMLPurifier_Config;

final class PurifierHtmlSanitizer implements HtmlSanitizer
{
    private const string ALLOWED = 'p,br,strong,b,em,i,u,ul,ol,li,a[href],h3,h4,h5,blockquote,code,pre,hr,table,thead,tbody,tr,th,td';

    private ?HTMLPurifier $purifier = null;

    public function __construct(private readonly string $cachePath) {}

    public function sanitize(string $html): string
    {
        // Headings are demoted so a posting never competes with the page's own h1/h2.
        $html = (string) preg_replace(['/<(\/?)h[12](\s[^>]*)?>/i', '/<(\/?)h6(\s[^>]*)?>/i'], ['<$1h3>', '<$1h5>'], $html);

        $clean = $this->purifier()->purify($html);

        // Collapse the empty paragraphs many ATS exports are full of.
        $clean = (string) preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $clean);

        return trim($clean);
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier !== null) {
            return $this->purifier;
        }

        if (! is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $this->cachePath);
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('HTML.Nofollow', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.Linkify', true);
        $config->set('Core.Encoding', 'UTF-8');

        return $this->purifier = new HTMLPurifier($config);
    }
}
