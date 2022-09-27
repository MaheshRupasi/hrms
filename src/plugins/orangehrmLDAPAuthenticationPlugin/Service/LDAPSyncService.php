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

namespace OrangeHRM\LDAP\Service;

use OrangeHRM\Admin\Traits\Service\UserServiceTrait;
use OrangeHRM\Authentication\Dto\UserCredential;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Core\Traits\Service\ConfigServiceTrait;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\User;
use OrangeHRM\Entity\UserAuthProvider;
use OrangeHRM\LDAP\Dao\LDAPDao;
use OrangeHRM\LDAP\Dto\EntryCollection;
use OrangeHRM\LDAP\Dto\EntryCollectionLookupSettingPair;
use OrangeHRM\LDAP\Dto\LDAPEmployeeSearchFilterParams;
use OrangeHRM\LDAP\Dto\LDAPSetting;
use OrangeHRM\LDAP\Dto\LDAPUser;
use OrangeHRM\LDAP\Dto\LDAPUserCollection;
use OrangeHRM\LDAP\Dto\LDAPUserLookupSetting;
use OrangeHRM\LDAP\Exception\LDAPSettingException;
use OrangeHRM\LDAP\Exception\LDAPSyncException;
use OrangeHRM\LDAP\Traits\LDAPLoggerTrait;
use OrangeHRM\ORM\Exception\TransactionException;
use OrangeHRM\Pim\Traits\Service\EmployeeServiceTrait;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Entry;
use Throwable;

use function md5;
use function serialize;

class LDAPSyncService
{
    use ConfigServiceTrait;
    use EntityManagerHelperTrait;
    use UserServiceTrait;
    use DateTimeHelperTrait;
    use EmployeeServiceTrait;
    use LDAPLoggerTrait;

    private ?LDAPService $ldapService = null;
    private ?LDAPSetting $ldapSetting = null;
    private LDAPDao $ldapDao;

    /**
     * @return LDAPDao
     */
    public function getLDAPDao(): LDAPDao
    {
        return $this->ldapDao ??= new LDAPDao();
    }

    /**
     * @return LDAPService
     */
    protected function getLDAPService(): LDAPService
    {
        if (!$this->ldapService instanceof LDAPService) {
            $this->ldapService = new LDAPService();
            $bindCredentials = new UserCredential();
            if (!$this->getLDAPSetting()->isBindAnonymously()) {
                $bindCredentials->setUsername($this->getLDAPSetting()->getBindUserDN());
                $bindCredentials->setPassword($this->getLDAPSetting()->getBindUserPassword());
            }
            $this->ldapService->bind($bindCredentials);
        }
        return $this->ldapService;
    }

    /**
     * @return LDAPSetting
     */
    protected function getLDAPSetting(): LDAPSetting
    {
        if (!$this->ldapSetting instanceof LDAPSetting) {
            $this->ldapSetting = $this->getConfigService()->getLDAPSetting();
            if ($this->ldapSetting === null) {
                throw new LDAPSettingException('LDAP settings not configured');
            }
        }
        return $this->ldapSetting;
    }

    /**
     * @return LDAPUserCollection
     */
    public function fetchAllLDAPUsers(): LDAPUserCollection
    {
        $ldapUsers = [];
        $duplicateUsernames = [];
        $usersOfDuplicateUsernames = [];
        $failedUsers = [];
        foreach ($this->fetchEntryCollections()->getCollections() as $entryCollectionLookupSettingPair) {
            foreach ($entryCollectionLookupSettingPair->getCollection()->toArray() as $entry) {
                $ldapUser = $this->prepareLDAPUserFromEntry(
                    $entry,
                    $entryCollectionLookupSettingPair->getLookupSetting()
                );
                if (is_null($ldapUser)) {
                    $failedUsers[] = $entry->getDn();
                    continue;
                }
                $username = $ldapUser->getUsername();
                if (isset($ldapUsers[$username])) {
                    $duplicateUsernames[] = $username;
                    $usersOfDuplicateUsernames[$username][] = $ldapUser;
                } else {
                    $ldapUsers[$username] = $ldapUser;
                }
            }
        }
        foreach ($duplicateUsernames as $duplicateUsername) {
            $usersOfDuplicateUsernames[$duplicateUsername][] = $ldapUsers[$duplicateUsername];
            unset($ldapUsers[$duplicateUsername]);
        }

        return new LDAPUserCollection($ldapUsers, $duplicateUsernames, $usersOfDuplicateUsernames, $failedUsers);
    }

    /**
     * @param LDAPUser $ldapUser
     * @return string
     */
    private function getHashOfLDAPUser(LDAPUser $ldapUser): string
    {
        return md5(serialize($ldapUser));
    }

    /**
     * @param LDAPUser[] $ldapUsers
     * @todo refactor
     */
    public function createSystemUsers(array $ldapUsers): void
    {
        $defaultUserRole = $this->getUserService()->getUserRole('ESS');
        foreach ($ldapUsers as $ldapUser) {
            $user = $this->getLDAPDao()->getUserByUserName($ldapUser->getUsername());

            if ($user instanceof User) {
                $ldapAuthProvider = $this->filterLDAPAuthProvider($user->getAuthProviders());
                if ($ldapAuthProvider instanceof UserAuthProvider) {
                    if ($ldapAuthProvider->getLDAPUserHash() === $this->getHashOfLDAPUser($ldapUser)) {
                        continue;
                    }

                    $employee = $user->getEmployee();
                    $employee->setFirstName($ldapUser->getFirstName());
                    $employee->setLastName($ldapUser->getLastName());
                    $employee->setMiddleName($ldapUser->getMiddleName());
                    $employee->setEmployeeId($ldapUser->getEmployeeId());
                    $employee->setWorkEmail($ldapUser->getWorkEmail());
                    $user->setStatus($ldapUser->isUserEnabled());
                    $user->setDateModified($this->getDateTimeHelper()->getNow());
                    //$user->setModifiedUserId(); TODO

                    // Change user data
                    $ldapAuthProvider->setLDAPUserDN($ldapUser->getUserDN());
                    $ldapAuthProvider->setLDAPUserUniqueId($ldapUser->getUserUniqueId());
                    $ldapAuthProvider->setLDAPUserHash($this->getHashOfLDAPUser($ldapUser));

                    // TODO:: trigger employee changed
                    if ($this->trySaveEmployee($employee, $ldapUser) === null) {
                        continue;
                    }
                    $this->getEntityManager()->persist($user);
                    $this->getEntityManager()->persist($ldapAuthProvider);
                    $this->getEntityManager()->flush();
                } elseif ($this->getLDAPSetting()->shouldMergeLDAPUsersWithExistingSystemUsers()) {
                    // TODO:: check employees who have multiple users
                    // local auth, may be skipped
                    $user->setStatus($ldapUser->isUserEnabled());
                    $user->setDateModified($this->getDateTimeHelper()->getNow());
                    //$user->setModifiedUserId(); TODO

                    $employee = $user->getEmployee();
                    $employee->setFirstName($ldapUser->getFirstName());
                    $employee->setLastName($ldapUser->getLastName());
                    $employee->setMiddleName($ldapUser->getMiddleName());
                    $employee->setEmployeeId($ldapUser->getEmployeeId());
                    $employee->setWorkEmail($ldapUser->getWorkEmail());

                    $authProvider = new UserAuthProvider();
                    $authProvider->setUser($user);
                    $authProvider->setType(UserAuthProvider::TYPE_LDAP);
                    $authProvider->setLDAPUserDN($ldapUser->getUserDN());
                    $authProvider->setLDAPUserUniqueId($ldapUser->getUserUniqueId());
                    $authProvider->setLDAPUserHash($this->getHashOfLDAPUser($ldapUser));

                    // TODO:: trigger employee changed
                    if ($this->trySaveEmployee($employee, $ldapUser) === null) {
                        continue;
                    }
                    $this->getEntityManager()->persist($user);
                    $this->getEntityManager()->persist($ldapAuthProvider);
                    $this->getEntityManager()->flush();
                    // TODO:: check/handle empty $user->getUserPassword()
                }
            } else {
                // try to find a user who have user unique id
                if ($ldapUser->getUserUniqueId() !== null) {
                    $ldapAuthProvider = $this->getLDAPDao()
                        ->getAuthProviderByUserUniqueId($ldapUser->getUserUniqueId());
                    if ($ldapAuthProvider instanceof UserAuthProvider) {
                        $user = $ldapAuthProvider->getUser();
                        $user->setUserName($ldapUser->getUsername());
                        $user->setStatus($ldapUser->isUserEnabled());
                        $user->setDateModified($this->getDateTimeHelper()->getNow());
                        //$user->setModifiedUserId(); TODO

                        $employee = $user->getEmployee();
                        $employee->setFirstName($ldapUser->getFirstName());
                        $employee->setLastName($ldapUser->getLastName());
                        $employee->setMiddleName($ldapUser->getMiddleName());
                        $employee->setEmployeeId($ldapUser->getEmployeeId());
                        $employee->setWorkEmail($ldapUser->getWorkEmail());

                        // Change user data
                        $ldapAuthProvider->setLDAPUserDN($ldapUser->getUserDN());
                        $ldapAuthProvider->setLDAPUserHash($this->getHashOfLDAPUser($ldapUser));

                        // TODO:: trigger employee changed
                        if ($this->trySaveEmployee($employee, $ldapUser) === null) {
                            continue;
                        }
                        $this->getEntityManager()->persist($user);
                        $this->getEntityManager()->persist($ldapAuthProvider);
                        $this->getEntityManager()->flush();

                        continue;
                    }
                }

                $employee = null;
                $employeeSearchFilterParams = $ldapUser->getEmployeeSearchFilterParams();
                if ($employeeSearchFilterParams instanceof LDAPEmployeeSearchFilterParams) {
                    $employee = $this->getLDAPDao()->getEmployee($employeeSearchFilterParams);
                }

                // Create a new user if not found the employee for given mapping configurations
                $employee = $employee ?? new Employee();
                $employee->setFirstName($ldapUser->getFirstName());
                $employee->setLastName($ldapUser->getLastName());
                $employee->setMiddleName($ldapUser->getMiddleName());
                $employee->setEmployeeId($ldapUser->getEmployeeId());
                $employee->setWorkEmail($ldapUser->getWorkEmail());

                $user = new User();
                $user->setUserName($ldapUser->getUsername());
                $user->setStatus($ldapUser->isUserEnabled());
                $user->setEmployee($employee);
                $user->setDateEntered($this->getDateTimeHelper()->getNow());
                //$user->setCreatedBy(); TODO
                $user->setUserRole($defaultUserRole);

                $authProvider = new UserAuthProvider();
                $authProvider->setUser($user);
                $authProvider->setType(UserAuthProvider::TYPE_LDAP);
                $authProvider->setLDAPUserDN($ldapUser->getUserDN());
                $authProvider->setLDAPUserUniqueId($ldapUser->getUserUniqueId());
                $authProvider->setLDAPUserHash($this->getHashOfLDAPUser($ldapUser));

                // TODO:: trigger employee changed
                if ($this->trySaveEmployee($employee, $ldapUser) === null) {
                    continue;
                }
                $this->getEntityManager()->persist($user);
                $this->getEntityManager()->persist($authProvider);
                $this->getEntityManager()->flush();
            }
        }
        // TODO:: soft delete LDAP users who removed from the server
    }

    /**
     * @param Employee $employee
     * @param LDAPUser $ldapUser
     * @return Employee|null
     */
    private function trySaveEmployee(Employee $employee, LDAPUser $ldapUser): ?Employee
    {
        try {
            $this->saveEmployee($employee);
            return $employee;
        } catch (LDAPSyncException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getLogger()->error(serialize($ldapUser));
            return null;
        }
    }

    /**
     * @param Employee $employee
     * @throws LDAPSyncException
     */
    private function saveEmployee(Employee $employee): void
    {
        if ($employee->getWorkEmail() !== null
            && !$this->getEmployeeService()->isUniqueEmail($employee->getWorkEmail())) {
            throw LDAPSyncException::nonUniqueWorkEmail();
        }
        if ($employee->getEmployeeId() !== null
            && !$this->getEmployeeService()
                ->getEmployeeDao()
                ->isUniqueEmployeeId($employee->getEmployeeId())) {
            throw LDAPSyncException::nonUniqueEmployeeId();
        }

        $this->getEntityManager()->persist($employee);
    }

    /**
     * @param UserAuthProvider[] $authProviders
     */
    public function filterLDAPAuthProvider(iterable $authProviders): ?UserAuthProvider
    {
        foreach ($authProviders as $authProvider) {
            if ($authProvider->getType() === UserAuthProvider::TYPE_LDAP) {
                return $authProvider;
            }
        }
        return null;
    }

    public function sync(): void
    {
        $this->beginTransaction();
        try {
            $ldapUserCollection = $this->fetchAllLDAPUsers();
            $ldapUsers = $ldapUserCollection->getLDAPUsers();
            $this->createSystemUsers(array_values($ldapUsers));
            $this->deleteLocalUsersWhoRemovedFromLDAPServer($ldapUserCollection);
            $this->commitTransaction();
        } catch (Throwable $e) {
            $this->rollBackTransaction();
            throw new TransactionException($e);
        }
    }

    /**
     * @return EntryCollection
     */
    public function fetchEntryCollections(): EntryCollection
    {
        $entryCollectionLookupSettingPairArray = [];
        foreach ($this->getLDAPSetting()->getUserLookupSettings() as $lookupSetting) {
            $entryCollectionLookupSettingPairArray[] = new EntryCollectionLookupSettingPair(
                $this->fetchEntryCollection($lookupSetting),
                $lookupSetting
            );
        }
        return new EntryCollection(...$entryCollectionLookupSettingPairArray);
    }

    /**
     * @param LDAPUserLookupSetting $lookupSetting
     * @return CollectionInterface
     */
    private function fetchEntryCollection(LDAPUserLookupSetting $lookupSetting): CollectionInterface
    {
        $options = [
            'scope' => $lookupSetting->getSearchScope(),
            'filter' => $this->getSearchAttributes($lookupSetting),
        ];
        $q = $this->getLDAPService()->query(
            $lookupSetting->getBaseDN(),
            $lookupSetting->getUserSearchFilter(),
            $options,
        );
        return $q->execute();
    }

    /**
     * @param Entry $entry
     * @param LDAPUserLookupSetting $lookupSetting
     * @return LDAPUser|null
     */
    private function prepareLDAPUserFromEntry(
        Entry $entry,
        LDAPUserLookupSetting $lookupSetting
    ): ?LDAPUser {
        $dataMapping = $this->getLDAPSetting()->getDataMapping();
        try {
            $username = $this->getAttribute($entry, $lookupSetting->getUserNameAttribute());
            return (new LDAPUser())
                ->setUserDN($entry->getDn())
                ->setUsername($username)
                ->setUserUniqueId($this->getAttribute($entry, $lookupSetting->getUserUniqueIdAttribute()))
                ->setUserEnabled($this->getAttribute($entry, $dataMapping->getUserStatusAttribute()) ?? true)
                ->setFirstName($this->getAttribute($entry, $dataMapping->getFirstNameAttribute()))
                ->setMiddleName($this->getAttribute($entry, $dataMapping->getMiddleNameAttribute()) ?? '')
                ->setLastName($this->getAttribute($entry, $dataMapping->getLastNameAttribute()))
                ->setEmployeeId($this->getAttribute($entry, $dataMapping->getEmployeeIdAttribute()))
                ->setWorkEmail($this->getAttribute($entry, $dataMapping->getWorkEmailAttribute()))
                ->setUserLookupSetting($lookupSetting)
                ->setEntry($entry);
        } catch (Throwable $e) {
            $this->getLogger()->warning($e->getMessage());
            $this->getLogger()->warning($e->getTraceAsString());
            return null;
        }
    }

    /**
     * @param Entry $entry
     * @param string|null $attributeName
     * @return string|null
     */
    private function getAttribute(Entry $entry, ?string $attributeName): ?string
    {
        if (empty($attributeName)) {
            return null;
        }
        return $entry->getAttribute($attributeName)[0] ?? null;
    }

    /**
     * @param LDAPUserLookupSetting $lookupSetting
     * @return string[]
     */
    private function getSearchAttributes(LDAPUserLookupSetting $lookupSetting): array
    {
        $dataMapping = $this->getLDAPSetting()->getDataMapping();
        $attributes = array_values(array_filter($dataMapping->toArray()));
        $attributes[] = $lookupSetting->getUserNameAttribute();
        if (!empty($lookupSetting->getUserUniqueIdAttribute())) {
            $attributes[] = $lookupSetting->getUserUniqueIdAttribute();
        }
        $attributes = array_merge($attributes, $lookupSetting->getEmployeeSelectorMapping()->getAllAttributeNames());
        return array_unique($attributes);
    }

    public function deleteLocalUsersWhoRemovedFromLDAPServer(LDAPUserCollection $ldapUserCollection): void
    {
        $ldapAuthProviders = [];
        foreach ($this->getLDAPDao()->getAllLDAPAuthProviders() as $authProvider) {
            $ldapAuthProviders[$authProvider->getUserDN()] = $authProvider->getUserId();
        }

        foreach ($ldapUserCollection->getLDAPUsers() as $ldapUser) {
            unset($ldapAuthProviders[$ldapUser->getUserDN()]);
        }
        foreach ($ldapUserCollection->getFailedUsers() as $failedUserDNs) {
            unset($ldapAuthProviders[$failedUserDNs]);
        }
        foreach ($ldapUserCollection->getUsersOfDuplicateUsernames() as $duplicateUsers) {
            foreach ($duplicateUsers as $ldapUser) {
                unset($ldapAuthProviders[$ldapUser->getUserDN()]);
            }
        }

        $this->getUserService()
            ->geUserDao()
            ->deleteSystemUsers(array_values($ldapAuthProviders));
    }
}
