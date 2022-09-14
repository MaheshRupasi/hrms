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

namespace OrangeHRM\Dashboard\Dto\ActionSummary;

use OrangeHRM\Performance\Dto\PerformanceReviewSearchFilterParams;
use OrangeHRM\Performance\Traits\Service\PerformanceReviewServiceTrait;

class PendingAppraisalReviewSummary implements ActionSummary
{
    use PerformanceReviewServiceTrait;

    /**
     * @var PerformanceReviewSearchFilterParams
     */
    private PerformanceReviewSearchFilterParams $performanceReviewSearchFilterParams;

    /**
     * @param PerformanceReviewSearchFilterParams $performanceReviewSearchFilterParams
     */
    public function __construct(PerformanceReviewSearchFilterParams $performanceReviewSearchFilterParams)
    {
        $this->performanceReviewSearchFilterParams = $performanceReviewSearchFilterParams;
    }

    /**
     * @inheritDoc
     */
    public function getGroupId(): int
    {
        return 3;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string
    {
        return 'Pending Appraisal Reviews';
    }

    /**
     * @inheritDoc
     */
    public function getPendingActionCount(): int
    {
        return $this->getPerformanceReviewService()
            ->getPerformanceReviewDao()
            ->getPerformanceReviewCount($this->performanceReviewSearchFilterParams);
    }
}
