<?php


namespace OrangeHRM\Pim\Api;

use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CrudEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Serializer\AbstractEndpointResult;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Exception\DaoException;
use OrangeHRM\Core\Exception\ServiceException;
use OrangeHRM\Entity\EmpDependent;
use OrangeHRM\Entity\EmployeeEducation;
use OrangeHRM\Entity\ReportTo;
use OrangeHRM\Pim\Api\Model\EmployeeDependentModel;
use OrangeHRM\Pim\Api\Model\EmployeeEducationModel;
use OrangeHRM\Pim\Api\Model\EmployeeSupervisorModel;
use OrangeHRM\Pim\Dto\EmployeeSupervisorSearchFilterParams;
use OrangeHRM\Pim\Service\EmployeeReportingMethodService;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;

class EmployeeSupervisorAPI  extends Endpoint implements CrudEndpoint
{

    public const PARAMETER_REPORTING_METHOD = 'reportingMethodId';
    public const PARAMETER_SUPERVISOR_EMP_NUMBER = 'empNumber';

    /**
     * @var EmployeeReportingMethodService|null
     */
    protected ?EmployeeReportingMethodService $employeeReportingMethodService = null;

    /**
     * @return EmployeeReportingMethodService
     */
    public function getEmployeeReportingMethodService(): EmployeeReportingMethodService
    {
        if (!$this->employeeReportingMethodService instanceof EmployeeReportingMethodService) {
            $this->employeeReportingMethodService = new EmployeeReportingMethodService();
        }
        return $this->employeeReportingMethodService;
    }


    /**
     * @inheritDoc
     * @throws DaoException
     */
    public function getOne(): EndpointResourceResult
    {
        list($empNumber, $supervisorId) = $this->getUrlAttributes();

        $empSupervisor = $this->getEmployeeReportingMethodService()->getEmployeeReportingMethodDao()->getEmployeeReportToByEmpNumbers($empNumber, $supervisorId);
        $this->throwRecordNotFoundExceptionIfNotExist($empSupervisor, ReportTo::class);

        return new EndpointResourceResult(
            EmployeeSupervisorModel::class, $empSupervisor,
            new ParameterBag([CommonParams::PARAMETER_EMP_NUMBER => $empNumber])
        );

    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                CommonParams::PARAMETER_EMP_NUMBER,
                new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)
            ),
            new ParamRule(
                CommonParams::PARAMETER_ID,
                new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)
            ),
        );
    }


    /**
     * @inheritDoc
     * @throws ServiceException
     */
    public function getAll(): EndpointCollectionResult
    {
        $employeeSupervisorSearchFilterParams = new EmployeeSupervisorSearchFilterParams();
        $this->setSortingAndPaginationParams($employeeSupervisorSearchFilterParams);

        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );

        $employeeSupervisorSearchFilterParams->setEmpNumber(
            $empNumber
        );

        $empSupervisors = $this->getEmployeeReportingMethodService()->getImmediateSupervisorListForEmployee($employeeSupervisorSearchFilterParams);
        return new EndpointCollectionResult(
            EmployeeSupervisorModel::class, $empSupervisors,
            new ParameterBag(
                [
                    CommonParams::PARAMETER_EMP_NUMBER => $empNumber,
                    CommonParams::PARAMETER_TOTAL => $this->getEmployeeReportingMethodService(
                    )->getImmediateSupervisorListCountForEmployee(
                        $employeeSupervisorSearchFilterParams
                    )
                ]
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                CommonParams::PARAMETER_EMP_NUMBER,
                new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)
            ),
            ...$this->getSortingAndPaginationParamsRules(EmployeeSupervisorSearchFilterParams::ALLOWED_SORT_FIELDS)
        );
    }

    /**
     * @inheritDoc
     * @throws DaoException
     */
    public function create(): EndpointResourceResult
    {
        $supervisor = new ReportTo();
        $this->setSupervisorParams($supervisor);

        $supervisor = $this->getEmployeeReportingMethodService()->getEmployeeReportingMethodDao()->saveEmployeeReportTo($supervisor);
        return new EndpointResourceResult(EmployeeSupervisorModel::class, $supervisor);
    }

    public function setSupervisorParams(ReportTo $supervisor): void
    {
        $reportingMethodId = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_BODY, self::PARAMETER_REPORTING_METHOD);
        $supervisorEmpNumber = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_BODY, self::PARAMETER_SUPERVISOR_EMP_NUMBER);
        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );
        $supervisor->getDecorator()->setReportingMethodByReportingMethodId($reportingMethodId);
        $supervisor->getDecorator()->setSubordinateEmployeeByEmpNumber($empNumber);
        $supervisor->getDecorator()->setSupervisorEmployeeByEmpNumber($supervisorEmpNumber);
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            $this->getEmpNumberRule(),
            $this->getReportingMethodIdRule()
        );
    }

    /**
     * @inheritDoc
     * @throws DaoException
     */
    public function delete(): EndpointResult
    {
        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );
        $ids = $this->getRequestParams()->getArray(RequestParams::PARAM_TYPE_BODY, CommonParams::PARAMETER_IDS);
        $this->getEmployeeReportingMethodService()->getEmployeeReportingMethodDao()->deleteEmployeeSupervisors($empNumber, $ids);
        return new EndpointResourceResult(ArrayModel::class, $ids);
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            $this->getEmpNumberRule(),
            $this->getSupervisorIdsRule()
        );
    }

    /**
     * @inheritDoc
     * @throws DaoException
     */
    public function update(): EndpointResourceResult
    {
        list($empNumber, $supervisorId) = $this->getUrlAttributes();
        $reportingMethodId = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_BODY, self::PARAMETER_REPORTING_METHOD);

        $employeeSupervisor = $this->getEmployeeReportingMethodService()
            ->getEmployeeReportingMethodDao()
            ->getEmployeeReportToByEmpNumbers($empNumber, $supervisorId);
        $this->throwRecordNotFoundExceptionIfNotExist($employeeSupervisor, ReportTo::class);

        $employeeSupervisor->getDecorator()->setReportingMethodByReportingMethodId($reportingMethodId);
        $this->getEmployeeReportingMethodService()->getEmployeeReportingMethodDao()->saveEmployeeReportTo($employeeSupervisor);

        return new EndpointResourceResult(
            EmployeeSupervisorModel::class, $employeeSupervisor,
            new ParameterBag([CommonParams::PARAMETER_EMP_NUMBER => $empNumber])
        );
    }

    /**
     * @return array
     */
    private function getUrlAttributes(): array
    {
        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );
        $id = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_ID
        );
        return [$empNumber, $id];
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(CommonParams::PARAMETER_ID, new Rule(Rules::REQUIRED), new Rule(Rules::POSITIVE)),
            $this->getEmpNumberRule(),
            $this->getReportingMethodIdRule()
        );
    }

    /**
     * @return ParamRule
     */
    private function getEmpNumberRule(): ParamRule
    {
        return new ParamRule(
            CommonParams::PARAMETER_EMP_NUMBER,
            new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)
        );
    }

    /**
     * @return ParamRule
     */
    private function getReportingMethodIdRule(): ParamRule
    {
        return new ParamRule(self::PARAMETER_REPORTING_METHOD, new Rule(Rules::POSITIVE));
    }

    private function getSupervisorIdsRule(): ParamRule
    {
        return new ParamRule(CommonParams::PARAMETER_IDS, new Rule(Rules::ARRAY_TYPE));
    }
}