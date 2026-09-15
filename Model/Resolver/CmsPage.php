<?php

declare(strict_types=1);

namespace Byte8\VelaFront\Model\Resolver;

use Byte8\VelaFront\Model\ContentParser;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

class CmsPage implements ResolverInterface
{
    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ContentParser $contentParser
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $identifier = trim((string) ($args['identifier'] ?? ''));
        if ($identifier === '') {
            throw new GraphQlInputException(__('"identifier" is required.'));
        }

        $storeId = (int) $this->storeManager->getStore()->getId();

        try {
            $page = $this->getPageByIdentifier->execute($identifier, $storeId);
        } catch (NoSuchEntityException) {
            try {
                $page = $this->pageRepository->getById($identifier);
            } catch (NoSuchEntityException) {
                throw new GraphQlNoSuchEntityException(
                    __('CMS page "%1" not found.', $identifier)
                );
            } catch (LocalizedException $e) {
                throw new GraphQlNoSuchEntityException(__($e->getMessage()));
            }
        }

        return $this->formatPage($page);
    }

    /**
     * @return array<string,mixed>
     */
    private function formatPage(PageInterface $page): array
    {
        $html = (string) $page->getContent();
        return [
            'identifier' => $page->getIdentifier(),
            'url_key' => $page->getIdentifier(),
            'title' => $page->getTitle(),
            'content_heading' => $page->getContentHeading(),
            'meta_title' => $page->getMetaTitle(),
            'meta_description' => $page->getMetaDescription(),
            'meta_keywords' => $page->getMetaKeywords(),
            'content_html' => $html,
            'content' => $this->contentParser->parse($html),
            'model' => $page,
        ];
    }
}
