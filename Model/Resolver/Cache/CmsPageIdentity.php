<?php

declare(strict_types=1);

namespace Byte8\VelaFront\Model\Resolver\Cache;

use Magento\Cms\Model\Page;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class CmsPageIdentity implements IdentityInterface
{
    public function getIdentities(array $resolvedData): array
    {
        $model = $resolvedData['model'] ?? null;
        if ($model instanceof Page && $model->getId()) {
            return [
                Page::CACHE_TAG . '_' . $model->getId(),
                Page::CACHE_TAG,
            ];
        }
        return [];
    }
}
