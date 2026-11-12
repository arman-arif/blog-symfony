<?php

declare(strict_types=1);

namespace App\Service\Article;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** Sanitizes stored HTML, adds stable heading anchors and derives reading metadata. */
final class ContentProcessor
{
    private const int WORDS_PER_MINUTE = 200;

    private readonly HtmlSanitizer $sanitizer;
    private readonly AsciiSlugger $slugger;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowMediaHosts(['www.youtube.com', 'youtube.com', 'youtu.be', 'player.vimeo.com', 'vimeo.com'])
            ->allowElement('iframe', ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading'])
            ->allowElement('figure', ['class'])
            ->allowElement('figcaption')
            ->forceHttpsUrls()
            ->withMaxInputLength(500_000);

        $this->sanitizer = new HtmlSanitizer($config);
        $this->slugger = new AsciiSlugger();
    }

    public function sanitize(string $html, bool $affiliateLinks = false): string
    {
        $clean = $this->sanitizer->sanitize($html);

        return $this->processDocument($clean, $affiliateLinks);
    }

    public function readingTime(string $html): int
    {
        $words = preg_split('/\s+/', trim(strip_tags($html))) ?: [];

        return max(1, (int) ceil(count(array_filter($words)) / self::WORDS_PER_MINUTE));
    }

    public function excerpt(string $html, int $length = 160): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim(false === $space ? $cut : mb_substr($cut, 0, $space), '.,;:!?').'…';
    }

    private function processDocument(string $html, bool $affiliateLinks = false): string
    {
        if ('' === trim($html)) {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="content-root">'.$html.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('content-root');
        if (null === $root) {
            return $html;
        }

        $used = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            if ('a' === $element->tagName && $affiliateLinks) {
                $element->setAttribute('rel', 'sponsored nofollow noopener');
            }
            if (!in_array($element->tagName, ['h2', 'h3', 'h4'], true)) {
                continue;
            }

            $base = strtolower((string) $this->slugger->slug($element->textContent));
            $base = '' === $base ? 'section' : $base;
            $id = $base;
            $suffix = 2;
            while (isset($used[$id])) {
                $id = $base.'-'.$suffix++;
            }
            $used[$id] = true;
            $element->setAttribute('id', $id);
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }
}
