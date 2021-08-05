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

namespace OrangeHRM\Tests\Leave\Service;

use Generator;
use OrangeHRM\Core\Dao\ConfigDao;
use OrangeHRM\Leave\Service\LeaveConfigurationService;
use OrangeHRM\Tests\Util\TestCase;

/**
 * @group Leave
 * @group Service
 */
class LeaveConfigurationServiceTest extends TestCase
{
    /**
     * @var LeaveConfigurationService
     */
    private LeaveConfigurationService $service;

    public function testGetLeaveEntitlementConsumptionStrategy(): void
    {
        $strategy = "FIFO";
        $mockDao = $this->getMockBuilder(ConfigDao::class)
            ->onlyMethods(['getValue'])
            ->getMock();
        $mockDao->expects($this->once())
            ->method('getValue')
            ->with(LeaveConfigurationService::KEY_LEAVE_ENTITLEMENT_CONSUMPTION_STRATEGY)
            ->willReturn($strategy);

        $this->service = $this->getMockBuilder(LeaveConfigurationService::class)
            ->onlyMethods(['getConfigDao'])
            ->getMock();
        $this->service->expects($this->once())
            ->method('getConfigDao')
            ->willReturn($mockDao);

        $this->assertEquals($strategy, $this->service->getLeaveEntitlementConsumptionStrategy());
    }

    public function testGetWorkScheduleImplementation(): void
    {
        $implementation = "Basic";
        $mockDao = $this->getMockBuilder(ConfigDao::class)
            ->onlyMethods(['getValue'])
            ->getMock();
        $mockDao->expects($this->once())
            ->method('getValue')
            ->with(LeaveConfigurationService::KEY_LEAVE_WORK_SCHEDULE_IMPLEMENTATION)
            ->willReturn($implementation);

        $this->service = $this->getMockBuilder(LeaveConfigurationService::class)
            ->onlyMethods(['getConfigDao'])
            ->getMock();
        $this->service->expects($this->once())
            ->method('getConfigDao')
            ->willReturn($mockDao);

        $this->assertEquals($implementation, $this->service->getWorkScheduleImplementation());
    }

    /**
     * @dataProvider getIncludePendingLeaveInBalanceDataProvider
     */
    public function testIncludePendingLeaveInBalance(?string $returnValue, bool $expected): void
    {
        $mockDao = $this->getMockBuilder(ConfigDao::class)
            ->onlyMethods(['getValue'])
            ->getMock();
        $mockDao->expects($this->once())
            ->method('getValue')
            ->with(LeaveConfigurationService::KEY_INCLUDE_PENDING_LEAVE_IN_BALANCE)
            ->willReturn($returnValue);

        $this->service = $this->getMockBuilder(LeaveConfigurationService::class)
            ->onlyMethods(['getConfigDao'])
            ->getMock();
        $this->service->expects($this->once())
            ->method('getConfigDao')
            ->willReturn($mockDao);

        $this->assertEquals($expected, $this->service->includePendingLeaveInBalance());
    }

    /**
     * @return Generator
     */
    public function getIncludePendingLeaveInBalanceDataProvider(): Generator
    {
        yield ['0', false];
        yield ['1', true];
        yield [null, true];
    }
}
