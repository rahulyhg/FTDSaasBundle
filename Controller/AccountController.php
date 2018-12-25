<?php

/*
 * This file is part of the FTDSaasBundle package.
 *
 * (c) Felix Niedballa <https://felixniedballa.de/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FTD\SaasBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use FTD\SaasBundle\Entity\User;
use FTD\SaasBundle\Event\AccountEvent;
use FTD\SaasBundle\Event\UserEvent;
use FTD\SaasBundle\Form\AccountType;
use FTD\SaasBundle\Form\PasswordResetType;
use FTD\SaasBundle\FTDSaasBundleEvents;
use FTD\SaasBundle\Manager\AccountManager;
use FTD\SaasBundle\Service\Authentication;
use FTD\SaasBundle\Util\TokenGenerator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The class contains API-Endpoints to handle accounts (User-entities) from outside the secured area.
 *
 * @author Felix Niedballa <schreib@felixniedballa.de>
 */
class AccountController
{
    /**
     * @var Authentication
     */
    private $authentication;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var AccountManager
     */
    private $accountManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private $passwordResetTime;

    /**
     * @param Authentication           $authentication
     * @param FormFactoryInterface     $formFactory
     * @param AccountManager           $accountManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param int                      $settingsPasswordResetTime
     */
    public function __construct(
        Authentication $authentication,
        FormFactoryInterface $formFactory,
        AccountManager $accountManager,
        EventDispatcherInterface $eventDispatcher,
        int $settingsPasswordResetTime
    ) {
        $this->authentication = $authentication;
        $this->formFactory = $formFactory;
        $this->accountManager = $accountManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->passwordResetTime = $settingsPasswordResetTime;
    }

    /**
     * Endpoint to create an account.
     *
     * @param Request                  $request
     * @param JWTTokenManagerInterface $jwtManager
     *
     * @return View
     *
     * @Rest\Post("account")
     */
    public function postAccountAction(Request $request, JWTTokenManagerInterface $jwtManager)
    {
        $account = $this->accountManager->create();
        $form = $this->formFactory->create(AccountType::class, $account);

        $form->submit($request->request->all());
        if ($form->isValid()) {
            $this->accountManager->update($account);
            $this->eventDispatcher->dispatch(FTDSaasBundleEvents::ACCOUNT_CREATE, new AccountEvent($account));

            return View::create(['token' => $jwtManager->create($account)], Response::HTTP_CREATED);
        }

        return View::create(['form' => $form], Response::HTTP_BAD_REQUEST);
    }

    /**
     * The endpoint contains the password-resetting. It needs a valid username or email as query parameter.
     *
     * @param Request             $request
     * @param TokenGenerator      $tokenGenerator
     * @param TranslatorInterface $translator
     *
     * @return View
     *
     * @throws \Exception
     *
     * @Rest\Delete("account/password")
     */
    public function deletePasswordAction(
        Request $request,
        TokenGenerator $tokenGenerator,
        TranslatorInterface $translator
    ) {
        $user = $this->accountManager->getAccountByEmail($request->query->get('email'));

        if ($user instanceof User) {
            if (
                !$user->getConfirmationRequestAt() instanceof \DateTime
                || $user->getConfirmationRequestAt() < new \DateTime(
                    sprintf('now - %s seconds', $this->passwordResetTime)
                )
            ) {
                $user->setConfirmationRequestAt(new \DateTime());
                $user->setConfirmationToken($tokenGenerator->generateToken());

                $this->accountManager->update($user);
                $this->eventDispatcher->dispatch(FTDSaasBundleEvents::ACCOUNT_PASSWORD_RESET, new UserEvent($user));

                return View::create([], Response::HTTP_CREATED);
            }

            return View::create(
                ['error' => $translator->trans('error.accountPasswordDelete.notEnoughTimeAgo')],
                Response::HTTP_BAD_REQUEST
            );
        }

        return View::create(
            ['error' => $translator->trans('error.accountPasswordDelete.accountNotFound')],
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * The endpoint contains functionality to set a new password. It needs a valid confirmation token as query
     * parameter.
     *
     * @param Request                  $request
     * @param JWTTokenManagerInterface $jwtManager
     * @param TranslatorInterface      $translator
     *
     * @return View
     *
     * @Rest\Post("account/password")
     */
    public function postPasswordAction(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        TranslatorInterface $translator
    ) {
        $user = $this->accountManager->getRepository()->findByConfirmationToken(
            $request->request->get('confirmationToken')
        );
        if (!$user instanceof User) {
            return View::create(
                ['errors' => $translator->trans('error.accountPasswordPost.noValidConfirmationToken')],
                Response::HTTP_NOT_FOUND
            );
        }

        $form = $this->formFactory->create(PasswordResetType::class, $user);
        $form->submit($request->request->all());

        if ($form->isValid()) {
            $user->setConfirmationToken(null);
            $user->setConfirmationRequestAt(null);
            $this->accountManager->update($user);
            $this->eventDispatcher->dispatch(FTDSaasBundleEvents::ACCOUNT_PASSWORD_UPDATE, new UserEvent($user));

            return View::create(['token' => $jwtManager->create($user)], Response::HTTP_OK);
        }

        return View::create(['form' => $form], Response::HTTP_BAD_REQUEST);
    }
}
