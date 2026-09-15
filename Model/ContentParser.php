<?php

declare(strict_types=1);

namespace Byte8\VelaFront\Model;

/**
 * Parses Page Builder HTML into a structured tree mirroring the
 * ParsedNode shape that @velafront/cms consumes.
 */
class ContentParser
{
    public const PARSER_VERSION = '1.0';

    private const INLINE_TYPES = ['text', 'html', 'heading'];

    public function __construct(
        private readonly DirectiveResolver $directiveResolver
    ) {
    }

    /**
     * @return array{version:string, nodes:array<int,array<string,mixed>>}
     */
    public function parse(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return ['version' => self::PARSER_VERSION, 'nodes' => []];
        }

        $resolved = $this->directiveResolver->resolveAll($content);

        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"?><root>' . $resolved . '</root>';
        $document->loadHTML(
            $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $root = $document->getElementsByTagName('root')->item(0);
        if (!$root instanceof \DOMElement) {
            return ['version' => self::PARSER_VERSION, 'nodes' => []];
        }

        return [
            'version' => self::PARSER_VERSION,
            'nodes' => $this->processNodes($root->childNodes),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function processNodes(\DOMNodeList $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $contentType = $node->getAttribute('data-content-type');
            if ($contentType === '') {
                // Not a Page Builder node — descend in case children are
                foreach ($this->processNodes($node->childNodes) as $child) {
                    $out[] = $child;
                }
                continue;
            }

            $out[] = [
                'content_type' => $contentType,
                'appearance' => $node->getAttribute('data-appearance') ?: 'default',
                'data' => $this->extractNodeData($node, $contentType),
                'children' => $this->processNodes($node->childNodes),
            ];
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function extractNodeData(\DOMElement $node, string $contentType): array
    {
        $data = [];

        foreach ($node->attributes as $attr) {
            $name = $attr->name;
            if ($name === 'data-content-type' || $name === 'data-appearance' || $name === 'style') {
                continue;
            }
            if (!str_starts_with($name, 'data-')) {
                continue;
            }
            $key = substr($name, 5);
            $data[$key] = $attr->value;
        }

        // Inline HTML for text/heading/html
        if (in_array($contentType, self::INLINE_TYPES, true)) {
            $data['html'] = $this->directiveResolver->resolveAll($this->innerHtml($node));
        }

        if ($contentType === 'heading') {
            foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $level) {
                $match = $node->getElementsByTagName($level)->item(0);
                if ($match instanceof \DOMElement) {
                    $data['heading_type'] = $level;
                    $data['html'] = $this->directiveResolver->resolveAll($this->innerHtml($match));
                    break;
                }
            }
        }

        // Image / banner / slide media
        $img = $node->getElementsByTagName('img')->item(0);
        if ($img instanceof \DOMElement) {
            $src = $img->getAttribute('src');
            if ($src !== '') {
                $data['image_url'] = $this->directiveResolver->resolveMediaUrl($src);
            }
            if ($img->hasAttribute('alt')) {
                $data['image_alt'] = $img->getAttribute('alt');
            }
            if ($img->hasAttribute('width')) {
                $data['image_width'] = (int) $img->getAttribute('width');
            }
            if ($img->hasAttribute('height')) {
                $data['image_height'] = (int) $img->getAttribute('height');
            }
        }

        // Link data
        $link = $node->getElementsByTagName('a')->item(0);
        if ($link instanceof \DOMElement) {
            $href = $link->getAttribute('href');
            if ($href !== '') {
                $data['link_url'] = $this->directiveResolver->resolveUrl($href);
            }
            $target = $link->getAttribute('target');
            if ($target !== '') {
                $data['link_target'] = $target;
            }
            if ($contentType === 'button-item') {
                $data['button_text'] = trim($this->innerHtml($link));
            }
        }

        // Background images (Page Builder stores a JSON blob in data-background-images)
        if (!empty($data['background-images'])) {
            $raw = html_entity_decode($data['background-images'], ENT_QUOTES | ENT_HTML5);
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                if (!empty($parsed['desktop_image'])) {
                    $data['background_image'] = $this->directiveResolver->resolveMediaUrl($parsed['desktop_image']);
                }
                if (!empty($parsed['mobile_image'])) {
                    $data['mobile_image_url'] = $this->directiveResolver->resolveMediaUrl($parsed['mobile_image']);
                }
            }
            unset($data['background-images']);
        }

        // Inline styles — surface a few useful ones as structured fields
        $style = $node->getAttribute('style');
        if ($style !== '') {
            $styles = $this->parseInlineStyles($style);
            if (isset($styles['background-color'])) {
                $data['background_color'] = $styles['background-color'];
            }
            if (isset($styles['background-position'])) {
                $data['background_position'] = $styles['background-position'];
            }
            if (isset($styles['background-size'])) {
                $data['background_size'] = $styles['background-size'];
            }
            if (isset($styles['background-repeat'])) {
                $data['background_repeat'] = $styles['background-repeat'];
            }
            if (isset($styles['text-align'])) {
                $data['text_align'] = $styles['text-align'];
            }
        }

        // Products widget
        if ($contentType === 'products') {
            if (!empty($data['skus'])) {
                $data['product_skus'] = array_values(array_filter(array_map('trim', explode(',', (string) $data['skus']))));
            }
            if (isset($data['products-count'])) {
                $data['product_count'] = (int) $data['products-count'];
            }
            if (isset($data['conditions-encoded'])) {
                $data['product_conditions'] = (string) $data['conditions-encoded'];
            }
        }

        // Widget placeholder (already generated by the Magento template filter via resolveAll)
        if ($contentType === 'widget') {
            if (isset($data['widget-type'])) {
                $data['widget_type'] = (string) $data['widget-type'];
            }
            if (isset($data['widget-attrs'])) {
                $data['widget_attrs'] = (string) $data['widget-attrs'];
            }
        }

        // Block reference
        if ($contentType === 'block' && isset($data['identifier'])) {
            $data['block_identifier'] = (string) $data['identifier'];
        }

        if (isset($data['css-classes'])) {
            $data['css_classes'] = (string) $data['css-classes'];
            unset($data['css-classes']);
        }

        return $data;
    }

    /**
     * @return array<string,string>
     */
    private function parseInlineStyles(string $raw): array
    {
        $out = [];
        foreach (explode(';', $raw) as $decl) {
            $pos = strpos($decl, ':');
            if ($pos === false) {
                continue;
            }
            $prop = strtolower(trim(substr($decl, 0, $pos)));
            $value = trim(substr($decl, $pos + 1));
            if ($prop === '' || $value === '') {
                continue;
            }
            $out[$prop] = $value;
        }
        return $out;
    }

    private function innerHtml(\DOMElement $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }
        return $html;
    }
}
