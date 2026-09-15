<?php

declare(strict_types=1);

namespace Byte8\VelaFront\Model\Resolver;

use Byte8\VelaFront\Model\ContentParser;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

class CmsBlock implements ResolverInterface
{
    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
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
            $block = $this->getBlockByIdentifier->execute($identifier, $storeId);
        } catch (NoSuchEntityException) {
            throw new GraphQlNoSuchEntityException(
                __('CMS block "%1" not found.', $identifier)
            );
        }

        $html = (string) $block->getContent();
        return [
            'identifier' => $block->getIdentifier(),
            'title' => $block->getTitle(),
            'content_html' => $html,
            'content' => $this->contentParser->parse($html),
            'model' => $block,
        ];
    }
}
