<?php

/*
 * This file is part of the FTDSaasBundle package.
 *
 * (c) Felix Niedballa <https://felixniedballa.de/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FTD\SaasBundle\Manager;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use FTD\SaasBundle\Model\ApiResource;
use FTD\SaasBundle\Service\Authentication;

/**
 * Base Manager to handle entities connected to database.
 *
 * @author Felix Niedballa <schreib@felixniedballa.de>
 */
abstract class BaseEntityManager implements CRUDEntityManagerInterface
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Authentication
     */
    protected $authentication;

    /**
     * @param EntityManagerInterface $entityManager
     * @param Authentication         $authentication
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        Authentication $authentication
    ) {
        $this->entityManager = $entityManager;
        $this->authentication = $authentication;
    }

    /**
     * @return string
     */
    abstract public function getClass(): string;

    /**
     * The function saves the passing entity to database.
     *
     * @param mixed $entity
     * @param bool  $flush
     *
     * @throws \Exception
     */
    public function update($entity, $flush = true): void
    {
        if ($entity instanceof ApiResource) {
            if (null === $entity->getCreatedAt()) {
                $entity->setCreatedAt(new \DateTime());
            }
            if (
                null !== ($user = $this->authentication->getCurrentUser())
                && null === $entity->getCreatedBy()
            ) {
                $entity->setCreatedBy($user);
            }
        }

        $this->entityManager->persist($entity);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * The function removes the passing entity from database.
     *
     * @param mixed $entity
     * @param bool  $flush
     */
    public function remove($entity, $flush = true): void
    {
        $this->entityManager->remove($entity);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return ObjectRepository
     */
    public function getRepository()
    {
        return $this->entityManager->getRepository($this->getClass());
    }
}
