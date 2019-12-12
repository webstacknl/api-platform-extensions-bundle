<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\EventListener;

use ApiPlatform\Core\Api\FormatMatcher;
use Negotiation\Negotiator;
use Webstack\ApiPlatformExtensionsBundle\Util\MimeType\MimeTypeFlattener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * Class MeListener
 */
final class MeListener
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var Negotiator
     */
    private $negotiator;

    /**
     * @var array
     */
    private $formats;

    /**
     * MeListener constructor.
     *
     * @param Security $security
     * @param Negotiator $negotiator
     * @param array $formats
     */
    public function __construct(Security $security, Negotiator $negotiator, array $formats = [])
    {
        $this->security = $security;
        $this->negotiator = $negotiator;
        $this->formats = $formats;
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('api_platform_extensions_me' !== $request->attributes->get('_route')) {
            return;
        }

        $user = $this->security->getUser();

        if (null === $user) {
            throw new BadRequestHttpException();
        }

        $format = $this->getFormatFromRequest($request);

        $request->setRequestFormat($format);

        $request->attributes->set('_api_resource_class', get_class($user));
        $request->attributes->set('_api_item_operation_name', 'get');
        $request->attributes->set('_controller', 'api_platform.action.get_item');
        $request->attributes->set('id', $user->getId());
    }

    /**
     * @param Request $request
     * @return string|null
     */
    private function getFormatFromRequest(Request $request): ?string
    {
        $flattenedMimeTypes = MimeTypeFlattener::flatten($this->formats);
        $mimeTypes = array_keys($flattenedMimeTypes);

        $formatMatcher = new FormatMatcher($this->formats);
        $accept = $request->headers->get('Accept');

        if (null === $mediaType = $this->negotiator->getBest($accept, $mimeTypes)) {
            throw $this->getNotAcceptableHttpException($accept, $flattenedMimeTypes);
        }

        return $formatMatcher->getFormat($mediaType->getType());
    }

    /**
     * @param string $accept
     * @param array $mimeTypes
     *
     * @return NotAcceptableHttpException
     */
    private function getNotAcceptableHttpException(string $accept, array $mimeTypes): NotAcceptableHttpException
    {
        return new NotAcceptableHttpException(sprintf(
            'Requested format "%s" is not supported. Supported MIME types are "%s".',
            $accept,
            implode('", "', array_keys($mimeTypes))
        ));
    }
}
