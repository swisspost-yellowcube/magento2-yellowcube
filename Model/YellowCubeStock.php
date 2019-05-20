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
        $connection = $this->_resource->getConnection();
        $connection->beginTransaction();

        $connection->delete(ResourceModel\YellowCubeStock::TABLE_NAME);

        $data = [];

        foreach ($articles as $article) {
            $data[] = [
                'sku' => $article->getArticleNo(),
                'quantity' => $article->getQuantityUOM()->get(),
                'lot' => $article->getLot(),
                'best_before_date' => date('Y-m-d', strtotime($article->getBestBeforeDate())),
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
        $connection = $this->_resource->getConnection();
        $table_name = $this->_resource->getTableName(ResourceModel\YellowCubeStock::TABLE_NAME);

        $result = $connection->query('SELECT * FROM ' . $table_name);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            unset($row['id']);
            $shipmentIds[] = $row;
        }
        return $shipmentIds;
    }
}
