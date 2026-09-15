<?php

declare(strict_types=1);

namespace Byte8\VelaFront\Model\Resolver\Cache;

use Magento\Cms\Model\Block;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class CmsBlockIdentity implements IdentityInterface
{
    public function getIdentities(array $resolvedData): array
    {
        $model = $resolvedData['model'] ?? null;
        if ($model instanceof Block && $model->getId()) {
            return [
                Block::CACHE_TAG . '_' . $model->getId(),
                Block::CACHE_TAG,
            ];
        }
        return [];
    }
}
