<?php

namespace Webstack\ApiPlatformExtensionsBundle\Swagger;

use ArrayObject;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webstack\ApiPlatformExtensionsBundle\Util\MimeType\MimeTypeFlattener;

/**
 * Class SwaggerDecorator
 */
final class SwaggerDecorator implements NormalizerInterface
{
    /**
     * @var NormalizerInterface
     */
    private $decorated;

    /**
     * @var array
     */
    private $formats;

    /**
     * SwaggerDecorator constructor.
     *
     * @param NormalizerInterface $decorated
     * @param array $formats
     */
    public function __construct(NormalizerInterface $decorated, array $formats = [])
    {
        $this->decorated = $decorated;
        $this->formats = $formats;
    }

    /**
     * @param mixed $object
     * @param null $format
     * @param array $context
     * @return array
     * @throws ExceptionInterface
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        /** @var array[] $docs */
        $docs = $this->decorated->normalize($object, $format, $context);

        $this->addMePaths($docs['paths']);

        return $docs;
    }

    /**
     * @param ArrayObject|array $paths
     */
    private function addMePaths(ArrayObject $paths): void
    {
        $formats = array_flip(MimeTypeFlattener::flatten($this->formats));

        $authorization = [
            'get' => [
                'tags' => [ 'Authorization' ],
                'consumes' => $formats,
                'produces' => $formats,
                'summary' => 'Retrieves the current user resource.',
                'parameters' => [],
                'responses' => [
                    '200' => [
                        'description' => 'User resource response'
                    ],
                    '404' => [
                        'description' => 'Resource not found'
                    ],
                ]
            ]
        ];

        $paths['/me'] = $authorization;

        $paths->uksort(static function($k1, $k2) {
            if ($k1 === '/me') {
                return -1;
            }

            if ($k2 === '/me') {
                return 1;
            }

            return $k1 <=> $k2;
        });
    }

    /**
     * @param mixed $data
     * @param null $format
     * @return bool
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
