<?php

/*
 * This file is part of the FTDSaasBundle package.
 *
 * (c) Felix Niedballa <https://felixniedballa.de/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FTD\SaasBundle\Form;

use FTD\SaasBundle\Entity\User;
use FTD\SaasBundle\Tests\Form\ValidatorExtensionTypeTestCase;

/**
 * @author Felix Niedballa <schreib@felixniedballa.de>
 */
class PasswordResetTypeTest extends ValidatorExtensionTypeTestCase
{
    public function testSubmitValidData()
    {
        $user = new User();
        $user->setPlainPassword('hans.zimmer');

        $formData = [
            'plainPassword' => 'hans.zimmer',
        ];

        $objectToCompare = new User();
        $form = $this->factory->create(PasswordResetType::class, $objectToCompare);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame($user->getUsername(), $objectToCompare->getUsername());
        $this->assertSame($user->getEmail(), $objectToCompare->getEmail());
    }
}
