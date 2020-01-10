<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\EventListener;

use ApiPlatform\Core\Api\FormatMatcher;
use ApiPlatform\Core\Util\ClassInfoTrait;
use App\Entity\Relation;
use App\Security\TokenCredentialsHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Negotiation\Negotiator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Webstack\ApiPlatformExtensionsBundle\Util\MimeType\MimeTypeFlattener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * Class MeListener
 */
final class MeListener
{
    use ClassInfoTrait;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Negotiator
     */
    private $negotiator;

    /**
     * @var array
     */
    private $formats;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * MeListener constructor.
     *
     * @param Security $security
     * @param EntityManagerInterface $entityManager
     * @param Negotiator $negotiator
     * @param ParameterBagInterface $parameterBag
     * @param array $formats
     */
    public function __construct(Security $security, EntityManagerInterface $entityManager, Negotiator $negotiator, ParameterBagInterface $parameterBag,  array $formats = [])
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->negotiator = $negotiator;
        $this->formats = $formats;
        $this->parameterBag = $parameterBag;
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

        $user = $this->security->getToken()->getUser();

        if ($user instanceof UserInterface) {
            $class = get_class($user);
            $id = $class->getId();
        } else {
            $subject = $this->getSubject($this->security->getToken());

            $id = $subject->getId();
            $class = $this->getObjectClass($subject);
        }

        if (null === $class) {
            throw new UnauthorizedHttpException();
        }

        $format = $this->getFormatFromRequest($request);

        $request->setRequestFormat($format);

        $request->attributes->set('_api_resource_class', $class);
        $request->attributes->set('_api_item_operation_name', 'get');
        $request->attributes->set('_controller', 'api_platform.action.get_item');
        $request->attributes->set('id', $id);
    }

    /**
     * @return
     */
    public function getSubject(OAuth2Token $token)
    {
        $class = $this->parameterBag->get('webstack.api_platform_extensions.identifier_class');

        $rsm = new ResultSetMappingBuilder($this->entityManager);
        $rsm->addRootEntityFromClassMetadata($class, 'subject');

        $reflectionClass = new \ReflectionClass($class);
        $class = strtolower($reflectionClass->getShortName());

        $query = $this->entityManager->createNativeQuery(sprintf('
            SELECT subject.*
            FROM %1$s subject
            WHERE subject.id = (
                SELECT oauth2_client.%1$s_id
                FROM oauth2_access_token
                LEFT JOIN oauth2_client ON oauth2_access_token.client = oauth2_client.identifier
                WHERE oauth2_access_token.identifier = :identifier
            )
        ', $class), $rsm);

        $query->setParameter('identifier', $token->getCredentials());

        try {
            return $query->getSingleResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            return null;
        }
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
