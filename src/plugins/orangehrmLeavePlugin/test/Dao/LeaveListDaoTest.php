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

namespace OrangeHRM\Tests\Leave\Dao;

use DateTime;
use Exception;
use OrangeHRM\Config\Config;
use OrangeHRM\Core\Service\DateTimeHelperService;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Framework\Services;
use OrangeHRM\Leave\Dao\LeaveListDao;
use OrangeHRM\Leave\Dto\LeaveListSearchFilterParams;
use OrangeHRM\Tests\Util\KernelTestCase;
use OrangeHRM\Tests\Util\TestDataService;

class LeaveListDaoTest extends KernelTestCase
{
    private LeaveListDao $leaveListDao;
    protected string $fixture;

    /**
     * Set up method
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->leaveListDao = new LeaveListDao();
        $this->fixture = Config::get(Config::PLUGINS_DIR) . '/orangehrmLeavePlugin/test/fixtures/LeaveListDao.yml';
        TestDataService::populate($this->fixture);
    }

    public function testGetEmployeeOnLeaveList(): void
    {
        $this->createKernelWithMockServices([
            Services::DATETIME_HELPER_SERVICE =>  new DateTimeHelperService(),
        ]);

        $leaveListSearchFilterParams = new LeaveListSearchFilterParams();
        $leaveListSearchFilterParams->setDate(new DateTime('2022-09-01'));
        $employeeOnLeave = $this->leaveListDao->getEmployeeOnLeaveList($leaveListSearchFilterParams);

        $this->assertCount(1, $employeeOnLeave);
        $this->assertInstanceOf(Leave::class, $employeeOnLeave[0]);
        $this->assertEquals('0004', $employeeOnLeave[0]->getEmployee()->getEmployeeId());
        $this->assertEquals('0004', $employeeOnLeave[0]->getEmployee()->getEmpNumber());
        $this->assertEquals(null, $employeeOnLeave[0]->getDecorator()->getEndTime());
        $this->assertEquals(null, $employeeOnLeave[0]->getDecorator()->getStartTime());

        $leaveListSearchFilterParams->setDate(new DateTime('2022-12-08'));
        $employeeOnLeave = $this->leaveListDao->getEmployeeOnLeaveList($leaveListSearchFilterParams);
        $this->assertCount(1, $employeeOnLeave);
        $this->assertEquals('Linda', $employeeOnLeave[0]->getEmployee()->getFirstName());
        $this->assertEquals('full_day', $employeeOnLeave[0]->getDecorator()->getLeaveDuration());
        $this->assertEquals('2022-12-08', $employeeOnLeave[0]->getDecorator()->getLeaveDate());

        $leaveListSearchFilterParams->setDate(new DateTime('2022-09-30'));
        $employeeOnLeave = $this->leaveListDao->getEmployeeOnLeaveList($leaveListSearchFilterParams);
        $this->assertEquals('14:00', $employeeOnLeave[0]->getDecorator()->getEndTime());
        $this->assertEquals('09:00', $employeeOnLeave[0]->getDecorator()->getStartTime());

        $leaveListSearchFilterParams->setDate(new DateTime('2023-01-03'));
        $leaveListSearchFilterParams->setLimit(2);
        $leaveListSearchFilterParams->setOffset(1);
        $employeeOnLeave = $this->leaveListDao->getEmployeeOnLeaveList($leaveListSearchFilterParams);
        $this->assertCount(2, $employeeOnLeave);
    }

    public function testGetEmployeeOnLeaveCount(): void
    {
        $this->createKernelWithMockServices([
            Services::DATETIME_HELPER_SERVICE =>  new DateTimeHelperService(),
        ]);

        $leaveListSearchFilterParams = new LeaveListSearchFilterParams();
        $leaveListSearchFilterParams->setDate(new DateTime('2022-09-01'));
        $count = $this->leaveListDao->getEmployeeOnLeaveCount($leaveListSearchFilterParams);
        $this->assertEquals(1, $count);

        $leaveListSearchFilterParams->setDate(new DateTime('2023-01-03'));
        $count = $this->leaveListDao->getEmployeeOnLeaveCount($leaveListSearchFilterParams);
        $this->assertEquals(3, $count);
    }
}