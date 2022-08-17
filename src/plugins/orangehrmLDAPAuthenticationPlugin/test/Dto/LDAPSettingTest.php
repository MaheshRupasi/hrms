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
 * Boston, MA 02110-1301, USA
 */

namespace OrangeHRM\Tests\LDAP\Dto;

use InvalidArgumentException;
use OrangeHRM\LDAP\Dto\LDAPSetting;
use OrangeHRM\Tests\Util\TestCase;

class LDAPSettingTest extends TestCase
{
    public function testFromString(): void
    {
        $setting = new LDAPSetting('example.com', 1389, 'OpenLDAP', 'tls', 'dc=example,dc=com');
        $this->assertEquals(
            '{"host":"example.com","port":1389,"encryption":"tls","implementation":"OpenLDAP","version":"3","optReferrals":false,"bindAnonymously":true,"bindUserDN":null,"bindUserPassword":null,"baseDN":"dc=example,dc=com","searchScope":"sub"}',
            (string)$setting
        );
        $this->expectException(InvalidArgumentException::class);
        new LDAPSetting('example.com', 1389, 'OpenLDAP', 'invalid', 'dc=example,dc=com');
    }
}