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

namespace OrangeHRM\Core\Service;

use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Dao\ReportGeneratorDao;
use OrangeHRM\Core\Dto\FilterParams;
use OrangeHRM\Core\Report\DisplayField\BasicDisplayField;
use OrangeHRM\Core\Report\DisplayField\CombinedDisplayField;
use OrangeHRM\Core\Report\DisplayField\EntityAliasMapping;
use OrangeHRM\Core\Report\DisplayField\GenericBasicDisplayField;
use OrangeHRM\Core\Report\DisplayField\GenericDateDisplayField;
use OrangeHRM\Core\Report\DisplayField\ListableDisplayField;
use OrangeHRM\Core\Report\DisplayField\NormalizableDTO;
use OrangeHRM\Core\Report\DisplayField\Stringable;
use OrangeHRM\Core\Report\FilterField\FilterField;
use OrangeHRM\Core\Report\Header\Column;
use OrangeHRM\Core\Report\Header\Header;
use OrangeHRM\Core\Report\Header\HeaderData;
use OrangeHRM\Core\Report\Header\StackedColumn;
use OrangeHRM\Core\Report\ReportSearchFilterParams;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\AbstractDisplayField;
use OrangeHRM\Entity\CompositeDisplayField;
use OrangeHRM\Entity\DisplayField;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Report;
use OrangeHRM\Entity\SummaryDisplayField;
use OrangeHRM\ORM\QueryBuilderWrapper;

class ReportGeneratorService
{
    use EntityManagerHelperTrait;

    public const SELECTED_FILTER_FIELD_TYPE_RUNTIME = 'Runtime';
    public const SELECTED_FILTER_FIELD_TYPE_PREDEFINED = 'Predefined';

    protected ?ReportGeneratorDao $reportGeneratorDao = null;

    /**
     * @var array<string, array>
     */
    protected array $generatedReportParamPool = [];

    /**
     * @return ReportGeneratorDao
     */
    public function getReportGeneratorDao(): ReportGeneratorDao
    {
        if (!$this->reportGeneratorDao instanceof ReportGeneratorDao) {
            $this->reportGeneratorDao = new ReportGeneratorDao();
        }
        return $this->reportGeneratorDao;
    }

    /**
     * @param int $reportId
     * @return bool
     */
    public function isPimReport(int $reportId): bool
    {
        $report = $this->getReportGeneratorDao()->getReport($reportId);
        if ($report instanceof Report) {
            return $report->getType() == 'PIM_DEFINED';
        }
        return false;
    }

    /**
     * @param int $reportId
     * @return HeaderData
     */
    public function getHeaderData(int $reportId): HeaderData
    {
        $selectedDisplayFields = [];
        $compositeFields = $this->getReportGeneratorDao()->getSelectedCompositeDisplayFieldsByReportId($reportId);
        $summaryFields = $this->getReportGeneratorDao()->getSummaryDisplayFieldByReportId($reportId);
        $displayFields = $this->getReportGeneratorDao()->getSelectedDisplayFieldsByReportId($reportId);

        $selectedDisplayFields = array_merge($selectedDisplayFields, $compositeFields, $displayFields, $summaryFields);
        $selectedDisplayGroupIds = $this->getReportGeneratorDao()->getSelectedDisplayFieldGroupIdsByReportId($reportId);

        return $this->getHeaderGroupsForDisplayFields($selectedDisplayFields, $selectedDisplayGroupIds);
    }

    /**
     * @param Array<DisplayField|CompositeDisplayField|SummaryDisplayField> $displayFields
     * @param int[] $selectedDisplayGroupIds
     * @return HeaderData
     */
    private function getHeaderGroupsForDisplayFields(array $displayFields, array $selectedDisplayGroupIds): HeaderData
    {
        /** @var StackedColumn[] $headerGroups */
        $headerGroups = [];
        $headerData = new HeaderData();

        // Default Group - for headers without a display group
        $defaultGroup = new StackedColumn([]);

        foreach ($displayFields as $displayField) {
            $column = new Column($displayField->getFieldAlias());
            $column->setName($displayField->getLabel());
            $column->setSize($displayField->getWidth());
            $headerData->incrementColumnCount();

            if ($displayField instanceof AbstractDisplayField) {
                if (is_null($displayField->getDisplayFieldGroup())) {
                    $defaultGroup->addChild($column);
                } elseif (!isset($headerGroups[$displayField->getDisplayFieldGroup()->getId()])) {
                    $displayFieldGroup = $displayField->getDisplayFieldGroup();

                    if (in_array($displayField->getDisplayFieldGroup()->getId(), $selectedDisplayGroupIds)) {
                        $groupName = $displayFieldGroup->getName();
                        $headerGroup = new StackedColumn([$column]);
                        $headerGroup->setName($groupName);
                        $headerGroups[$displayField->getDisplayFieldGroup()->getId()] = $headerGroup;
                        $headerData->incrementGroupCount();
                        $headerData->incrementGroupedColumnCount();
                    } else {
                        $defaultGroup->addChild($column);
                    }
                } else {
                    $headerGroups[$displayField->getDisplayFieldGroup()->getId()]->addChild($column);
                    $headerData->incrementGroupedColumnCount();
                }
            }
        }

        // Add the default group if it has any headers
        if (count($defaultGroup) > 0) {
            array_push($headerGroups, ...$defaultGroup->getChildren());
        }
        $headerData->setColumns($headerGroups);
        return $headerData;
    }

    /**
     * @param int $reportId
     * @return Header
     */
    public function getHeaderDefinitionByReportId(int $reportId): Header
    {
        $headerData = $this->getHeaderData($reportId);
        $header = new Header($headerData->getColumns());
        $report = $this->getReportGeneratorDao()->getReport($reportId);
        $header->setMeta(
            new ParameterBag(
                [
                    'name' => $report->getName(),
                    'columnCount' => $headerData->getColumnCount(),
                    'groupCount' => $headerData->getGroupCount(),
                    'groupedColumnCount' => $headerData->getGroupedColumnCount(),
                ]
            )
        );
        return $header;
    }

    /**
     * @param ReportSearchFilterParams $filterParams
     * @return array
     */
    public function getNormalizedReportData(ReportSearchFilterParams $filterParams): array
    {
        list(
            $queryBuilderWrapper,
            $combinedDisplayFields,
            $listedDisplayFields,
            $displayFieldGroups
            ) = $this->getReportDataQueryBuilder($filterParams);

        $results = $queryBuilderWrapper->getQueryBuilder()->getQuery()->execute();
        // Normalize DTO objects
        foreach ($results as $i => $result) {
            foreach ($combinedDisplayFields as $combinedDisplayField) {
                if ($result[$combinedDisplayField] instanceof Stringable) {
                    $results[$i][$combinedDisplayField] = $result[$combinedDisplayField]->toString();
                }
            }
            foreach ($listedDisplayFields as $listedDisplayField) {
                if ($result[$listedDisplayField] instanceof NormalizableDTO) {
                    $results[$i] = array_merge(
                        $results[$i],
                        $result[$listedDisplayField]->toArray($displayFieldGroups[$listedDisplayField])
                    );
                }
                unset($results[$i][$listedDisplayField]);
            }
        }
        return $results;
    }

    /**
     * @param ReportSearchFilterParams $filterParams
     * @return int
     */
    public function getReportDataCount(ReportSearchFilterParams $filterParams): int
    {
        list($queryBuilderWrapper) = $this->getReportDataQueryBuilder($filterParams);
        return $this->getPaginator($queryBuilderWrapper->getQueryBuilder())->count();
    }

    /**
     * @param ReportSearchFilterParams $filterParams
     * @return array
     */
    protected function getReportDataQueryBuilder(ReportSearchFilterParams $filterParams): array
    {
        $key = $this->getHashKeyForFilterParamObject($filterParams);
        if (isset($this->generatedReportParamPool[$key])) {
            return $this->generatedReportParamPool[$key];
        }

        $displayFields = $this->getReportGeneratorDao()
            ->getSelectedDisplayFieldsByReportId($filterParams->getReportId());

        // TODO:: Support for time, attendance, currently only supports for PIM reports
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->from(Employee::class, 'employee');

        $combinedDisplayFields = [];
        $listedDisplayFields = [];
        $joinAliases = [];
        $displayFieldGroups = [];

        foreach ($displayFields as $displayField) {
            $displayFieldClassName = $displayField->getClassName();
            $displayFieldClass = new $displayFieldClassName();
            if ($displayFieldClass instanceof \OrangeHRM\Core\Report\DisplayField\DisplayField) {
                if ($displayFieldClass instanceof GenericBasicDisplayField ||
                    $displayFieldClass instanceof GenericDateDisplayField) {
                    $displayFieldClass->setDisplayField($displayField);
                }

                if ($displayField->isValueList()) {
                    $displayFieldGroupId = $displayField->getDisplayFieldGroup()->getId();
                    $fieldAlias = 'displayFieldGroup' . $displayFieldGroupId;
                    if (!isset($displayFieldGroups[$fieldAlias])) {
                        $displayFieldGroups[$fieldAlias] = [$displayField->getFieldAlias()];
                    } else {
                        $displayFieldGroups[$fieldAlias][] = $displayField->getFieldAlias();
                        // if this field is a list field and already added to field groups
                        continue;
                    }
                } else {
                    $fieldAlias = $displayField->getFieldAlias();
                }

                // Don't use user input here
                $qb->addSelect($displayFieldClass->getSelectPart() . ' AS ' . $fieldAlias);

                // Track alias and DTO result alias
                if ($displayFieldClass instanceof ListableDisplayField) {
                    $listedDisplayFields[] = $fieldAlias;
                    array_push($joinAliases, ...$displayFieldClass->getEntityAliases());
                } elseif ($displayFieldClass instanceof CombinedDisplayField) {
                    $combinedDisplayFields[] = $fieldAlias;
                    array_push($joinAliases, ...$displayFieldClass->getEntityAliases());
                } elseif ($displayFieldClass instanceof BasicDisplayField) {
                    $joinAliases[] = $displayFieldClass->getEntityAlias();
                }
            }
        }

        $queryBuilderWrapper = $this->getQueryBuilderWrapper($qb);
        $selectedFilterFields = $this->getReportGeneratorDao()
            ->getSelectedFilterFieldsByReportId($filterParams->getReportId());
        foreach ($selectedFilterFields as $selectedFilterField) {
            $filterField = $selectedFilterField->getFilterField();
            $filterFieldClassName = $filterField->getClassName();
            $filterFieldClass = new $filterFieldClassName(
                $selectedFilterField->getOperator(),
                $selectedFilterField->getX(),
                $selectedFilterField->getY(),
                $selectedFilterField->getFilterFieldOrder()
            );
            if ($filterFieldClass instanceof FilterField) {
                $filterFieldClass->addWhereToQueryBuilder($queryBuilderWrapper);
                array_push($joinAliases, ...$filterFieldClass->getEntityAliases());
            }
        }

        $qb->groupBy('employee.empNumber');
        $this->setJoinsToQueryBuilder($queryBuilderWrapper, array_unique($joinAliases));
        $this->setSortingAndPaginationParams($queryBuilderWrapper, $filterParams);

        $this->generatedReportParamPool[$key] = [
            $queryBuilderWrapper,
            $combinedDisplayFields,
            $listedDisplayFields,
            $displayFieldGroups,
            $joinAliases,
        ];
        return $this->generatedReportParamPool[$key];
    }

    /**
     * @param ReportSearchFilterParams $filterParams
     * @return string
     */
    protected function getHashKeyForFilterParamObject(ReportSearchFilterParams $filterParams): string
    {
        return md5(serialize($filterParams));
    }

    /**
     * @param QueryBuilderWrapper $queryBuilderWrapper
     * @param FilterParams $filterParams
     */
    protected function setSortingAndPaginationParams(
        QueryBuilderWrapper $queryBuilderWrapper,
        FilterParams $filterParams
    ): void {
        $qb = $queryBuilderWrapper->getQueryBuilder();
        if (!is_null($filterParams->getSortField())) {
            $qb->addOrderBy(
                $filterParams->getSortField(),
                $filterParams->getSortOrder()
            );
        }
        // If limit = 0, will not paginate
        if (!empty($filterParams->getLimit())) {
            $qb->setFirstResult($filterParams->getOffset())
                ->setMaxResults($filterParams->getLimit());
        }
    }

    /**
     * @param QueryBuilderWrapper $queryBuilderWrapper
     * @param string[] $joinAliases
     */
    protected function setJoinsToQueryBuilder(QueryBuilderWrapper $queryBuilderWrapper, array $joinAliases): void
    {
        foreach ($joinAliases as $joinAlias) {
            $this->setJoinToQueryBuilder($queryBuilderWrapper, $joinAlias);
        }
    }

    /**
     * @param QueryBuilderWrapper $queryBuilderWrapper
     * @param string $joinAlias
     */
    protected function setJoinToQueryBuilder(QueryBuilderWrapper $queryBuilderWrapper, string $joinAlias): void
    {
        $qb = $queryBuilderWrapper->getQueryBuilder();
        if (isset(EntityAliasMapping::ALIAS_DEPENDENCIES[$joinAlias])) {
            // alias have dependencies
            if (!in_array($joinAlias, $qb->getAllAliases())) {
                $this->setJoinToQueryBuilder($queryBuilderWrapper, EntityAliasMapping::ALIAS_DEPENDENCIES[$joinAlias]);
                $qb->leftJoin(EntityAliasMapping::ALIAS_MAPPING[$joinAlias], $joinAlias);
            } // else: alias already added
        } elseif (isset(EntityAliasMapping::ALIAS_MAPPING[$joinAlias])) {
            $qb->leftJoin(EntityAliasMapping::ALIAS_MAPPING[$joinAlias], $joinAlias);
        } // else: no need to left join since alias in root alias
    }
}
