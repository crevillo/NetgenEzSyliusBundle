<?php

namespace Netgen\Bundle\EzSyliusBundle\Provider;

use eZ\Publish\API\Repository\Values\User\UserReference;
use Netgen\Bundle\EzSyliusBundle\Entity\EzSyliusUser;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\MVC\Symfony\Security\UserInterface as EzUserInterface;
use Sylius\Component\User\Model\UserInterface as SyliusUserInterface;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use Sylius\Bundle\UserBundle\Provider\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\EntityRepository;

class EmailOrNameBased implements UserProviderInterface
{
    /**
     * @var \Sylius\Bundle\UserBundle\Provider\UserProviderInterface
     */
    protected $innerUserProvider;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $entityRepository;

    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * @var string
     */
    protected $syliusUserType;

    /**
     * Constructor.
     *
     * @param \Sylius\Bundle\UserBundle\Provider\UserProviderInterface $innerUserProvider
     * @param \Doctrine\ORM\EntityRepository $entityRepository
     * @param \eZ\Publish\API\Repository\Repository $repository
     * @param string $syliusUserType
     */
    public function __construct(
        UserProviderInterface $innerUserProvider,
        EntityRepository $entityRepository,
        Repository $repository,
        $syliusUserType
    ) {
        $this->innerUserProvider = $innerUserProvider;
        $this->entityRepository = $entityRepository;
        $this->repository = $repository;
        $this->syliusUserType = $syliusUserType;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($usernameOrEmail)
    {
        $user = $this->innerUserProvider->loadUserByUsername($usernameOrEmail);

        $apiUser = $this->loadAPIUser($user);

        if ($user instanceof EzUserInterface && $apiUser instanceof UserReference) {
            $user->setAPIUser($apiUser);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        $user = $this->innerUserProvider->refreshUser($user);

        $apiUser = $this->loadAPIUser($user);

        if ($user instanceof EzUserInterface && $apiUser instanceof UserReference) {
            $user->setAPIUser($apiUser);
            $this->repository->setCurrentUser($apiUser);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $this->innerUserProvider->supportsClass($class);
    }

    /**
     * Loads eZ API user based on provided Sylius user.
     *
     * @param \Sylius\Component\User\Model\UserInterface $user
     *
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    protected function loadAPIUser(SyliusUserInterface $user)
    {
        $eZSyliusUser = $this->entityRepository->findOneBy(
            array(
                'syliusUserId' => $user->getId(),
                'syliusUserType' => $this->syliusUserType,
            )
        );

        if (!$eZSyliusUser instanceof EzSyliusUser) {
            return null;
        }

        try {
            return $this->repository->getUserService()->loadUser(
                $eZSyliusUser->getEzUserId()
            );
        } catch (NotFoundException $e) {
            return null;
        }
    }
}