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

namespace OrangeHRM\Performance\Api;

use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Entity\PerformanceTrackerReviewer;
use OrangeHRM\Performance\Api\Model\EmployeeTrackerModel;
use OrangeHRM\Performance\Dto\EmployeeTrackerSearchFilterParams;
use OrangeHRM\Performance\Service\PerformanceTrackerService;

class EmployeeTrackerAPI extends Endpoint implements CollectionEndpoint
{
    use UserRoleManagerTrait;

    public const FILTER_INCLUDE_EMPLOYEES = 'includeEmployees';
    public const FILTER_NAME_OR_ID = 'nameOrId';

    public const PARAM_RULE_FILTER_NAME_OR_ID_MAX_LENGTH = 100;

    private ?PerformanceTrackerService $employeeTrackerService = null;

    /**
     * @return PerformanceTrackerService
     */
    public function getPerformanceTrackerService(): PerformanceTrackerService
    {
        if (!$this->employeeTrackerService instanceof PerformanceTrackerService) {
            $this->employeeTrackerService = new PerformanceTrackerService();
        }
        return $this->employeeTrackerService;
    }

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointResult
    {
        $employeeTrackerSearchFilterParams = $this->getEmployeeTrackerSearchFilterParams();
        $this->setSortingAndPaginationParams($employeeTrackerSearchFilterParams);

        $empNumber = $this->getRequestParams()->getIntOrNull(
            RequestParams::PARAM_TYPE_QUERY,
            CommonParams::PARAMETER_EMP_NUMBER
        );

        if (!is_null($empNumber)) {
            $employeeTrackerSearchFilterParams->setEmpNumbers([$empNumber]);
        } else {
            $accessibleEmpNumbers = $this->getUserRoleManager()->getAccessibleEntityIds(PerformanceTrackerReviewer::class);
            $employeeTrackerSearchFilterParams->setEmpNumbers($accessibleEmpNumbers);
        }

        $employeeTrackerList = $this->getPerformanceTrackerService()
            ->getPerformanceTrackerDao()
            ->getEmployeeTrackerList($employeeTrackerSearchFilterParams);
        $employeeTrackerCount = $this->getPerformanceTrackerService()
            ->getPerformanceTrackerDao()
            ->getEmployeeTrackerCount($employeeTrackerSearchFilterParams);

        return new EndpointCollectionResult(
            EmployeeTrackerModel::class,
            $employeeTrackerList,
            new ParameterBag([CommonParams::PARAMETER_TOTAL => $employeeTrackerCount])
        );
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    CommonParams::PARAMETER_EMP_NUMBER,
                    new Rule(Rules::CALLBACK, [
                        function ($empNumber) {
                            if (!(is_numeric($empNumber) && $empNumber > 0)) {
                                return false;
                            }

                            return in_array(
                                $empNumber,
                                $this->getUserRoleManager()
                                    ->getAccessibleEntityIds(PerformanceTrackerReviewer::class)
                            );
                        }
                    ])
                )
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::FILTER_NAME_OR_ID,
                    new Rule(Rules::STRING_TYPE),
                    new Rule(Rules::LENGTH, [null, self::PARAM_RULE_FILTER_NAME_OR_ID_MAX_LENGTH]),
                )
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::FILTER_INCLUDE_EMPLOYEES,
                    new Rule(Rules::IN, [EmployeeTrackerSearchFilterParams::INCLUDE_EMPLOYEES])
                )
            ),
            ...$this->getSortingAndPaginationParamsRules(
                EmployeeTrackerSearchFilterParams::ALLOWED_SORT_FIELDS
            )
        );
    }

    /**
     * @return EmployeeTrackerSearchFilterParams
     */
    protected function getEmployeeTrackerSearchFilterParams(): EmployeeTrackerSearchFilterParams
    {
        $employeeTrackerSearchFilterParams = new EmployeeTrackerSearchFilterParams();
        $employeeTrackerSearchFilterParams->setNameOrId(
            $this->getRequestParams()->getStringOrNull(
                RequestParams::PARAM_TYPE_QUERY,
                self::FILTER_NAME_OR_ID
            )
        );
        $employeeTrackerSearchFilterParams->setIncludeEmployees(
            $this->getRequestParams()->getString(
                RequestParams::PARAM_TYPE_QUERY,
                self::FILTER_INCLUDE_EMPLOYEES,
                EmployeeTrackerSearchFilterParams::INCLUDE_EMPLOYEES_ONLY_CURRENT
            )
        );
        return $employeeTrackerSearchFilterParams;
    }

    /**
     * @inheritDoc
     */
    public function create(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function delete(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}