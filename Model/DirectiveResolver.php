<?php

declare(strict_types=1);

namespace Byte8\VelaFront\Model;

use Magento\Framework\Filter\Template as TemplateFilter;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves Magento template directives (media/store/view/config/widget) in CMS content
 * and exposes helpers used by the content parser to build structured JSON.
 */
class DirectiveResolver
{
    public function __construct(
        private readonly TemplateFilter $templateFilter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Resolve all Magento directives inside a string using Magento's own template filter.
     */
    public function resolveAll(string $html): string
    {
        if ($html === '') {
            return '';
        }

        try {
            return $this->templateFilter->filter($html);
        } catch (\Throwable $e) {
            return $html;
        }
    }

    /**
     * Resolve a media path into a full URL using the current store's media base URL.
     */
    public function resolveMediaUrl(string $path): string
    {
        if ($path === '' || preg_match('~^https?://~i', $path)) {
            return $path;
        }

        $store = $this->storeManager->getStore();
        $mediaBase = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
        return $mediaBase . '/' . ltrim($path, '/');
    }

    /**
     * Resolve a storefront-relative URL.
     */
    public function resolveUrl(string $url): string
    {
        if ($url === '' || preg_match('~^https?://~i', $url)) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }
}
