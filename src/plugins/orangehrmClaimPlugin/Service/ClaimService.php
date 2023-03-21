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

namespace OrangeHRM\Claim\Service;

use OrangeHRM\Claim\Dao\ClaimDao;
use OrangeHRM\Core\Api\V2\Exception\EndpointExceptionTrait;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Entity\ClaimRequest;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\WorkflowStateMachine;

class ClaimService
{
    use DateTimeHelperTrait;
    use UserRoleManagerTrait;
    use EndpointExceptionTrait;

    /**
     * @var ClaimDao
     */
    protected ClaimDao $claimDao;

    /**
     * @return ClaimDao
     */
    public function getClaimDao(): ClaimDao
    {
        return $this->claimDao ??= new ClaimDao();
    }

    /**
     * @return string
     */
    public function getReferenceId(): string
    {
        $nextId = $this->getClaimDao()->getNextId();
        $date = $this->getDateTimeHelper()->getNow()->format('Ymd');
        return $date . str_pad("$nextId", 7, 0, STR_PAD_LEFT);
    }

    /**
     * @param int $action
     * @param ClaimRequest $claimRequest
     * @return bool
     */
    public function isActionAllowed(int $action, ClaimRequest $claimRequest): bool
    {
        $isActionAllowed = $this->getUserRoleManager()->isActionAllowed(
            WorkflowStateMachine::FLOW_CLAIM,
            $claimRequest->getStatus(),
            $action,
            [],
            [],
            [Employee::class => $claimRequest->getEmployee()->getEmpNumber()]
        );
        if (!$isActionAllowed) {
            throw $this->getForbiddenException();
        }
        return true;
    }
}
