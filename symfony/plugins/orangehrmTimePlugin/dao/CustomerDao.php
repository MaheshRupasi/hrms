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

namespace OrangeHRM\Time\Dao;

use Exception;
use OrangeHRM\Core\Dao\BaseDao;
use OrangeHRM\Core\Exception\DaoException;
use OrangeHRM\Entity\Customer;
use OrangeHRM\Entity\ProjectActivity;
use OrangeHRM\ORM\Doctrine;
use OrangeHRM\ORM\Paginator;

class CustomerDao extends BaseDao
{
    /**
     *
     * @param int $limit
     * @param int $offset
     * @param string $sortField
     * @param string $sortOrder
     * @param bool $activeOnly
     * @param false $noOfRecords
     * @return Customer []
     * @throws DaoException
     */
    public function getCustomerList($limit = 0, $offset = 0, $sortField = 'customer.name', $sortOrder = 'ASC', $activeOnly = true, $noOfRecords = false): array
    {

        $sortField = ($sortField == "") ? 'name' : $sortField;
        $sortOrder = strcasecmp($sortOrder, 'DESC') === 0 ? 'DESC' : 'ASC';

        try {
//            $q = Doctrine_Query:: create()
//                ->from('Customer');
//
//            if ($activeOnly == true) {
//                $q->addWhere('is_deleted = 0');
//            }
//
//            $q->orderBy($sortField . ' ' . $sortOrder)
//                ->offset($offset)
//                ->limit($limit);

            //
            $q = Doctrine::getEntityManager()->getRepository(Customer::class)->createQueryBuilder('customer');

            if ($activeOnly == true) {
                $q->andWhere('customer.is_deleted = :isDeleted');
                $q->setParameter('isDeleted', '0');
            }

            $q->addOrderBy($sortField, $sortOrder);
            if (!empty($limit)) {
                $q->setFirstResult($offset)
                    ->setMaxResults($limit);
            }

            if ($noOfRecords) {
                $paginator = new Paginator($q, true);
                $noOfRecords = $paginator->count();
                return $noOfRecords;

            }
            return $q->getQuery()->execute();
            // end


        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     *
     * @param type $activeOnly
     * @return type
     */
    public function getCustomerCount($activeOnly = true)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('Customer');

            if ($activeOnly == true) {
                $q->addWhere('is_deleted = ?', 0);
            }
            $count = $q->execute()->count();


            $q = Doctrine::getEntityManager()->getRepository(Customer::class)->createQueryBuilder('customer');

            if ($activeOnly == true) {
                $q->andWhere('customer.is_deleted = :isDeleted');
                $q->setParameter('isDeleted', '0');
            }


            return $count;
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     *
     * @param type $customerId
     * @return type
     */
    public function getCustomerById($customerId)
    {
        try {
            return Doctrine:: getTable('Customer')->find($customerId);
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Get Customer by name
     *
     * @param $customerId
     * @return mixed
     * @throws DaoException
     */
    public function getCustomerByName($customerName)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('Customer')
                ->where('name = ?', $customerName)
                ->andWhere('is_deleted = ?', 0);
            return $q->count();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     *
     * @param type $customerId
     */
    public function deleteCustomer($customerId)
    {
        try {
            $customer = Doctrine:: getTable('Customer')->find($customerId);
            $customer->setIsDeleted(Customer::DELETED);
            $customer->save();
            $this->_deleteRelativeProjectsForCustomer($customerId);
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    public function undeleteCustomer($customerId)
    {
        try {
            $customer = Doctrine:: getTable('Customer')->find($customerId);
            $customer->setIsDeleted(Customer::ACTIVE);
            $customer->save();
            $this->_undeleteRelativeProjectsForCustomer($customerId);
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    private function _deleteRelativeProjectsForCustomer($customerId)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('Project')
                ->where('is_deleted = ?', Project::ACTIVE_PROJECT)
                ->andWhere('customer_id = ?', $customerId);
            $projects = $q->execute();

            foreach ($projects as $project) {
                $project->setIsDeleted(Project::DELETED_PROJECT);
                $project->save();
                $this->_deleteRelativeProjectActivitiesForProject($project->getProjectId());
                $this->_deleteRelativeProjectAdminsForProject($project->getProjectId());
            }
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    private function _undeleteRelativeProjectsForCustomer($customerId)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('Project')
                ->where('is_deleted = ?', Project::DELETED_PROJECT)
                ->andWhere('customer_id = ?', $customerId);
            $projects = $q->execute();

            foreach ($projects as $project) {
                $project->setIsDeleted(Project::ACTIVE_PROJECT);
                $project->save();
                $this->_undeleteRelativeProjectActivitiesForProject($project->getProjectId());
            }
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    private function _deleteRelativeProjectActivitiesForProject($projectId)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('ProjectActivity')
                ->where('is_deleted = ?', ProjectActivity::ACTIVE_PROJECT_ACTIVITY)
                ->andWhere('project_id = ?', $projectId);
            $projectActivities = $q->execute();

            foreach ($projectActivities as $projectActivity) {
                $projectActivity->setIsDeleted(ProjectActivity::DELETED_PROJECT_ACTIVITY);
                $projectActivity->save();
            }
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    private function _undeleteRelativeProjectActivitiesForProject($projectId)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('ProjectActivity')
                ->where('is_deleted = ?', ProjectActivity::DELETED_PROJECT_ACTIVITY)
                ->andWhere('project_id = ?', $projectId);
            $projectActivities = $q->execute();

            foreach ($projectActivities as $projectActivity) {
                $projectActivity->setIsDeleted(ProjectActivity::ACTIVE_PROJECT_ACTIVITY);
                $projectActivity->save();
            }
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    private function _deleteRelativeProjectAdminsForProject($projectId)
    {
        try {
            $q = Doctrine_Query:: create()
                ->delete('ProjectAdmin pa')
                ->where('pa.project_id = ?', $projectId);
            $q->execute();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     *
     * @param type $activeOnly
     * @return type
     */
    public function getAllCustomers($activeOnly = true)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('Customer');
            if ($activeOnly == true) {
                $q->where('is_deleted =?', 0);
            }
            return $q->execute();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Return an array of Customer Names
     *
     * @param Array $customerIdList List of Customer Ids
     * @param Boolean $excludeDeletedCustomers Exclude deleted Customers or not
     * @return Array of Customer Names
     * @version 2.7.1
     */
    public function getCustomerNameList($customerIdList, $excludeDeletedCustomers = true)
    {
        try {

            if (!empty($customerIdList)) {

                $customerIdString = implode(',', $customerIdList);

                $q = "SELECT c.customer_id AS customerId, c.name
                		FROM ohrm_customer AS c
                		WHERE c.customer_id IN ({$customerIdString})";

                if ($excludeDeletedCustomers) {
                    $q .= ' AND c.is_deleted = 0';
                }

                $pdo = Doctrine_Manager::connection()->getDbh();
                $query = $pdo->prepare($q);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_ASSOC);
            }

            return $results;

            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            throw new DaoException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     *
     * @param type $customerId
     * @return type
     */
    public function hasCustomerGotTimesheetItems($customerId)
    {
        try {
            $q = Doctrine_Query:: create()
                ->select("COUNT(*)")
                ->from('TimesheetItem ti')
                ->leftJoin('ti.Project p')
                ->leftJoin('p.Customer c')
                ->where('c.customerId = ?', $customerId);
            $count = $q->fetchOne(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);
            return ($count > 0);
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Get Customer by Id ( active )
     *
     * @param $customerId
     * @return mixed
     * @throws DaoException
     */
    public function getActiveCustomerById($id)
    {
        try {
            $q = Doctrine_Query:: create()
                ->from('Customer')
                ->where('customer_id = ?', $id)
                ->andWhere('is_deleted = ?', 0);
            return $q->fetchOne();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * @param Customer $customer
     * @return Customer
     * @throws DaoException
     */
    public function saveCustomer(Customer $customer): Customer
    {
        try {
            $this->persist($customer);
            return $customer;
        } catch (Exception $e) {
            throw new DaoException($e->getMessage(), $e->getCode(), $e);
        }
    }
}

?>
