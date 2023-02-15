<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 */

namespace OrangeHRM\Authentication\Controller;

use OrangeHRM\Authentication\Auth\User as AuthUser;
use OrangeHRM\Authentication\Traits\Service\PasswordStrengthServiceTrait;
use OrangeHRM\Core\Controller\AbstractVueController;
use OrangeHRM\Core\Controller\PublicControllerInterface;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Vue\Component;
use OrangeHRM\Core\Vue\Prop;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Services;

class WeakPasswordResetController extends AbstractVueController implements PublicControllerInterface
{
    use PasswordStrengthServiceTrait;
    use AuthUserTrait;

    /**
     * @inheritDoc
     */
    public function preRender(Request $request): void
    {
        $resetCode = $request->attributes->get('resetCode');
        if ($this->getPasswordStrengthService()->validateUrl($resetCode)) {
            $component = new Component('reset-weak-password');
            $username = $this->getPasswordStrengthService()->getUserNameByResetCode($resetCode);
            $component->addProp(
                new Prop('username', Prop::TYPE_STRING, $username)
            );
            if ($this->getAuthUser()->hasFlash(AuthUser::FLASH_LOGIN_ERROR)) {
                $error = $this->getAuthUser()->getFlash(AuthUser::FLASH_LOGIN_ERROR);
                $component->addProp(
                    new Prop(
                        'error',
                        Prop::TYPE_OBJECT,
                        $error[0] ?? []
                    )
                );
            }
            $session = $this->getContainer()->get(Services::SESSION);
            $session->invalidate();
        } else {
            $component = new Component('auth-login');
        }
        $this->setComponent($component);
        $this->setTemplate('no_header.html.twig');
    }
}
