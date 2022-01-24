<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Swagger;

use ArrayObject;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webstack\ApiPlatformExtensionsBundle\Util\MimeType\MimeTypeFlattener;

final class SwaggerDecorator implements NormalizerInterface
{
    private NormalizerInterface $decorated;
    private array $formats;

    public function __construct(NormalizerInterface $decorated, array $formats = [])
    {
        $this->decorated = $decorated;
        $this->formats = $formats;
    }

    /**
     * @throws ExceptionInterface
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        $docs = $this->decorated->normalize($object, $format, $context);

        $this->addMePaths($docs['paths']);

        return $docs;
    }

    private function addMePaths(ArrayObject $paths): void
    {
        $formats = array_flip(MimeTypeFlattener::flatten($this->formats));

        $authorization = [
            'get' => [
                'tags' => ['Authorization'],
                'consumes' => $formats,
                'produces' => $formats,
                'summary' => 'Retrieves the current user resource.',
                'parameters' => [],
                'responses' => [
                    '200' => [
                        'description' => 'User resource response',
                    ],
                    '404' => [
                        'description' => 'Resource not found',
                    ],
                ],
            ],
        ];

        $paths['/me'] = $authorization;

        $paths->uksort(static function ($k1, $k2) {
            if ('/me' === $k1) {
                return -1;
            }

            if ('/me' === $k2) {
                return 1;
            }

            return $k1 <=> $k2;
        });
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
