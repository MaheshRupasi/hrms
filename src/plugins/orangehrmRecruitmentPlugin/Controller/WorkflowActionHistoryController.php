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

namespace OrangeHRM\Recruitment\Controller;

use OrangeHRM\Core\Authorization\Controller\CapableViewController;
use OrangeHRM\Core\Controller\AbstractVueController;
use OrangeHRM\Core\Controller\Common\NoRecordsFoundController;
use OrangeHRM\Core\Controller\Exception\RequestForwardableException;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Core\Vue\Component;
use OrangeHRM\Core\Vue\Prop;
use OrangeHRM\Entity\Candidate;
use OrangeHRM\Entity\CandidateHistory;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Recruitment\Traits\Service\CandidateServiceTrait;

class WorkflowActionHistoryController extends AbstractVueController implements CapableViewController
{
    use UserRoleManagerTrait;
    use CandidateServiceTrait;

    /**
     * @inheritDoc
     */
    public function preRender(Request $request): void
    {
        $component = new Component('view-action-history');
        $candidateId = $request->attributes->getInt('candidateId');
        $historyId = $request->attributes->getInt('historyId');

        if (is_null($this->getCandidateService()->getCandidateDao()->getCandidateById($candidateId)) ||
            is_null(
                $this->getCandidateService()->getCandidateDao()->getCandidateHistoryRecordByCandidateIdAndHistoryId(
                    $candidateId,
                    $historyId
                )
            )
        ) {
            throw new RequestForwardableException(NoRecordsFoundController::class . '::handle');
        }

        $component->addProp(new Prop('candidate-id', Prop::TYPE_NUMBER, $candidateId));
        $component->addProp(new Prop('history-id', Prop::TYPE_NUMBER, $historyId));
        $this->setComponent($component);
    }

    public function isCapable(Request $request): bool
    {
        if ($request->attributes->getInt('candidateId') && $request->attributes->getInt('historyId')) {
            $candidateId = $request->attributes->getInt('candidateId');
            $historyId = $request->attributes->getInt('historyId');
            if (!$this->getUserRoleManager()->isEntityAccessible(Candidate::class, $candidateId)) {
                return false;
            }
            if (!$this->getUserRoleManager()->isEntityAccessible(CandidateHistory::class, $historyId)) {
                return false;
            }
            if (!$this->getCandidateService()
                    ->getCandidateDao()
                    ->getCandidateHistoryRecordByCandidateIdAndHistoryId($candidateId, $historyId)
                instanceof CandidateHistory) {
                return false;
            }
            return true;
        }
        return false;
    }
}
