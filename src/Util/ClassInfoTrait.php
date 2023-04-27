<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Util;

use ApiPlatform\Metadata\Util\ClassInfoTrait as MetadataClassInfoTrait;
use ApiPlatform\Util\ClassInfoTrait as BaseClassInfoTrait;

if (trait_exists(MetadataClassInfoTrait::class)) {
    trait ClassInfoTrait
    {
        use MetadataClassInfoTrait;
    }
} elseif (trait_exists(BaseClassInfoTrait::class)) {
    trait ClassInfoTrait
    {
        use BaseClassInfoTrait;
    }
}
