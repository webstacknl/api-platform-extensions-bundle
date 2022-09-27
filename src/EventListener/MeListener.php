<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\EventListener;

use ApiPlatform\Core\Api\FormatMatcher;
use ApiPlatform\Core\Util\ClassInfoTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Negotiation\Exception\Exception as NeogationException;
use Negotiation\Negotiator;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Webstack\ApiPlatformExtensionsBundle\Util\MimeType\MimeTypeFlattener;

final class MeListener
{
    use ClassInfoTrait;

    private Security $security;
    private EntityManagerInterface $entityManager;
    private Negotiator $negotiator;
    private ParameterBagInterface $parameterBag;
    private array $formats;

    public function __construct(Security $security, EntityManagerInterface $entityManager, Negotiator $negotiator, ParameterBagInterface $parameterBag, array $formats = [])
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->negotiator = $negotiator;
        $this->formats = $formats;
        $this->parameterBag = $parameterBag;
    }

    /**
     * @throws ReflectionException
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws NeogationException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('api_platform_extensions_me' !== $request->attributes->get('_route')) {
            return;
        }

        if (null === $this->security->getToken()) {
            return;
        }

        /** @var UserInterface $user */
        $user = $this->security->getToken()->getUser();

        if ($user instanceof UserInterface) {
            $class = get_class($user);
            $id = $user->getId();
        } else {
            $subject = $this->getSubject($this->security->getToken());

            $id = $subject->getId();
            $class = $this->getObjectClass($subject);
        }

        $format = $this->getFormatFromRequest($request);

        $request->setRequestFormat($format);

        $request->attributes->set('_api_resource_class', $class);
        $request->attributes->set('_api_item_operation_name', 'get');
        $request->attributes->set('_controller', 'api_platform.action.get_item');
        $request->attributes->set('id', $id);
    }

    /**
     * @throws ReflectionException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getSubject(TokenInterface $token): object
    {
        $class = $this->parameterBag->get('webstack.api_platform_extensions.identifier_class');

        $rsm = new ResultSetMappingBuilder($this->entityManager);
        $rsm->addRootEntityFromClassMetadata($class, 'subject');

        $reflectionClass = new ReflectionClass($class);
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

        return $query->getSingleResult();
    }

    /**
     * @throws NeogationException
     */
    private function getFormatFromRequest(Request $request): ?string
    {
        $flattenedMimeTypes = MimeTypeFlattener::flatten($this->formats);

        $mimeTypes = array_keys($flattenedMimeTypes);

        $formatMatcher = new FormatMatcher($this->formats);

        $accept = $request->headers->get('Accept');

        if (null === ($mediaType = $this->negotiator->getBest($accept, $mimeTypes))) {
            throw $this->getNotAcceptableHttpException($accept, $flattenedMimeTypes);
        }

        return $formatMatcher->getFormat($mediaType->getType());
    }

    private function getNotAcceptableHttpException(string $accept, array $mimeTypes): NotAcceptableHttpException
    {
        return new NotAcceptableHttpException(sprintf('Requested format "%s" is not supported. Supported MIME types are "%s".', $accept, implode('", "', array_keys($mimeTypes))
        ));
    }
}
