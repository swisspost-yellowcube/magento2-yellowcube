<?php

namespace Swisspost\YellowCube\Model;

use Magento\Framework\Model\AbstractModel;

class YellowCubeStock extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\YellowCubeStock::class);
    }

    /**
     * Updates the YellowCube stock.
     *
     * @param \YellowCube\BAR\Article[] $articles
     *
     */
    public function updateStock(array $articles)
    {
        $connection = $this->_getResource()->getConnection();
        $connection->beginTransaction();

        $connection->delete(ResourceModel\YellowCubeStock::TABLE_NAME);

        $data = [];

        foreach ($articles as $article) {
            $data[] = [
                'sku' => $article->getArticleNo(),
                'quantity' => $article->getQuantityUOM()->get(),
                'lot' => $article->getLot(),
                'yc_article_no' => $article->getYCArticleNo(),
                'best_before_date' => $article->getBestBeforeDate() ? date('Y-m-d', strtotime($article->getBestBeforeDate())) : null,
            ];
        }
        $connection->insertMultiple(ResourceModel\YellowCubeStock::TABLE_NAME, $data);
        $connection->commit();
    }

    /**
     * Returns the stock information.
     *
     * @return array
     *   List of arrays with properties sku, quantity, lot and best_before_date.
     */
    public function getStock()
    {
        $connection = $this->_getResource()->getConnection();
        $table_name = $this->_getResource()->getMainTable();

        $result = $connection->query('SELECT * FROM ' . $table_name);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            unset($row['id']);
            $shipmentIds[] = $row;
        }
        return $shipmentIds;
    }
}
