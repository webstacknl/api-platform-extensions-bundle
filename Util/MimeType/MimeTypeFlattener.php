<?php

namespace Webstack\ApiPlatformExtensionsBundle\Util\MimeType;

/**
 * Class MimeTypeFlattener
 */
final class MimeTypeFlattener
{
    /**
     * Returns the flattened list of MIME types.
     *
     * @param array $formats
     * @return array
     */
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
