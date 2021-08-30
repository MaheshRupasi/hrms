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

namespace OrangeHRM\Leave\Dto;

use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\LeaveRequest;

class LeaveRequestCommentSearchFilterParams extends DateRangeSearchFilterParams
{
    use EntityManagerHelperTrait;

    public const ALLOWED_SORT_FIELDS = ['leaveRequestComment.createdAt'];

    /**
     * @var LeaveRequest|null
     */
    private ?LeaveRequest $leaveRequest = null;

    public function __construct()
    {
        $this->setSortField('leaveRequestComment.createdAt');
    }

    /**
     * @return LeaveRequest|null
     */
    public function getLeaveRequest(): ?LeaveRequest
    {
        return $this->leaveRequest;
    }

    /**
     * @param LeaveRequest|null $leaveRequest
     */
    public function setLeaveRequest(?LeaveRequest $leaveRequest): void
    {
        $this->leaveRequest = $leaveRequest;
    }

    /**
     * @param int $leaveRequestId
     */
    public function setLeaveRequestById(int $leaveRequestId): void
    {
        /** @var LeaveRequest|null $leaveRequest */
        $leaveRequest = $this->getReference(LeaveRequest::class, $leaveRequestId);
        $this->setLeaveRequest($leaveRequest);
    }
}
