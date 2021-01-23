<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Catalog\Ui\DataProvider\Product;

/**
 * Collection which is used for rendering product list in the backend.
 *
 * Used for product grid and customizes behavior of the default Product collection for grid needs.
 */
class ProductCollection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * Disables using of price index for grid rendering
     *
     * Admin area shouldn't use price index and should rely on actual product data instead.
     *
     * @codeCoverageIgnore
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function _productLimitationJoinPrice()
    {
        $this->_productLimitationFilters->setUsePriceIndex(false);
        return $this->_productLimitationPrice(true);
    }

    /**
     * Return approximately amount if too much entities.
     *
     * @return int|mixed
     */
    public function getSize()
    {
        $sql = $this->getSelectCountSql();
        $possibleCount = $this->analyzeCount($sql);

        if ($possibleCount > 20000) {
            return $possibleCount;
        }

        return parent::getSize();
    }

    /**
     * Analyze amount of entities in DB.
     *
     * @param $sql
     * @return int|mixed
     * @throws \Zend_Db_Statement_Exception
     */
    private function analyzeCount($sql)
    {
        $results = $this->getConnection()->query('EXPLAIN ' . $sql)->fetchAll();
        $alias = self::MAIN_TABLE_ALIAS;

        foreach ($results as $result) {
            if (isset($result['table']) && $result['table'] == $alias) {
                return $result['rows'];
            }
            // Postgres Compatibility
            if (isset($result['QUERY PLAN'])) {
                $search = ($this->isEnabledFlat() ? $this->getEntity()->getFlatTableName()
                        : $this->getEntity()->getEntityTable()) . ' ' . self::MAIN_TABLE_ALIAS;
                $rows = explode('rows=', $result['QUERY PLAN']);
                if (strpos($result['QUERY PLAN'], $search) && isset($rows[1])) {
                    return (int)(explode(' ', $rows[1])[0]);
                }
            }
        }

        return 0;
    }

    /**
     * Identify main table alias or its name if alias is not defined.
     *
     * @return string
     * @throws \LogicException
     */
    private function getMainTableAlias()
    {
        foreach ($this->getSelect()->getPart(\Magento\Framework\DB\Select::FROM) as $tableAlias => $tableMetadata) {
            if ($tableMetadata['joinType'] == 'from') {
                return $tableAlias;
            }
        }
        throw new \LogicException("Main table cannot be identified.");
    }
}
