<?php

/**
 * Created by PhpStorm.
 * User: administrator
 * Date: 27/8/18
 * Time: 5:18 PM
 */
class purgeEmployeeAction extends sfAction
{

    /**
     * Execute any application/business logic for this component.
     *
     * In a typical database-driven application, execute() handles application
     * logic itself and then proceeds to create a model instance. Once the model
     * instance is initialized it handles all business logic for the action.
     *
     * A model should represent an entity in your application. This could be a
     * user account, a shopping cart, or even a something as simple as a
     * single product.
     *
     * @param sfRequest $request The current sfRequest object
     *
     * @return mixed     A string containing the view name associated with this action
     */
    public function execute($request)
    {
        // TODO: Implement execute() method.

        $value = $request->hasParameter('check_authenticate');
//        $loadEmployeeData = $request->hasParameter('check_employee_data');
        $data = $request->getParameterHolder()->getAll();
        var_dump($data);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $value) {
            $userId = sfContext::getInstance()->getUser()->getAttribute('auth.userId');

            if ($this->getAuthenticateVerifyService()->isCurrentPassword($userId, $data['confirm_password'])) {
                $this->setTemplate('purgeAllRecords', 'maintenance');
                $this->form = new PurgeForm();
            } else {
                $this->form = new PurgeAuthenticateForm();
                $this->getUser()->setFlash('success', __(CommonMessages::CREDENTIALS_REQUIRED));
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->form = new PurgeAuthenticateForm();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$value) {
            if (empty($data['employee']['empId']) or $data['employee']['empName'] == 'Type for hints...') {
                $this->getUser()->setFlash('success', __(TopLevelMessages::SELECT_RECORDS));
                $this->setTemplate('purgeAllRecords', 'maintenance');
                $this->form = new PurgeForm();
            } else{
//                $this->purge($data);
                $this->setTemplate('purgeAllRecords', 'maintenance');
                $this->form = new PurgeForm();
//                var_dump('asdadasdasd');
            }
        }
    }

    protected function getAuthenticateVerifyService()
    {
        if (!isset($this->authVerifyService)) {
            $this->authVerifyService = new AuthenticateVerifyService();
        }
        return $this->authVerifyService;
    }


    /////////////
    public function getEmployeeService()
    {
        if (!isset($this->employeeService)) {
            $this->employeeService = new EmployeeService();
        }
        return $this->employeeService;
    }

    public function getMaintenanceService()
    {
        if (!isset($this->maintenanceService)) {
            $this->maintenanceService = new MaintenanceService();
        }
        return $this->maintenanceService;
    }

    protected function purge($data)
    {
        try {
            $empNumber = $data['employee']['empId'];
            $employee = $this->getEmployeeService()->getEmployee($empNumber);

            if (empty($employee) || empty($employee->getTerminationId())) {
                $this->getUser()->setFlash('success', __(TopLevelMessages::SELECT_RECORDS));
                $this->setTemplate('purgeAllRecords', 'pim');
                $this->form = new PurgeForm();
            } else {
                $this->getMaintenanceService()->purgeEmployee($empNumber);
                $this->getUser()->setFlash('success', __(TopLevelMessages::DELETE_SUCCESS));
                $this->setTemplate('purgeAllRecords', 'pim');
                $this->form = new PurgeForm();
            }
        } catch (Exception $e) {
            $this->getUser()->setFlash('success', __(TopLevelMessages::DELETE_FAILURE));
            $this->setTemplate('purgeAllRecords', 'pim');
            $this->form = new PurgeForm();

        }

    }


}