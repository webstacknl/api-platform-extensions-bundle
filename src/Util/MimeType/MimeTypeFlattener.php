<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Util\MimeType;

final class MimeTypeFlattener
{
    public static function flatten(array $formats): array
    {
        $flattenedMimeTypes = [];

        foreach ($formats as $format => $mimeTypes) {
            foreach ($mimeTypes as $mimeType) {
                $flattenedMimeTypes[$mimeType] = $format;
            }
        }

        return $flattenedMimeTypes;
    }
}
