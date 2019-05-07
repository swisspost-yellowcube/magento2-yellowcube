<?php

namespace Swisspost\YellowCube\Model;

class YellowCubeStock
{
    const TABLE_NAME = 'yellowcube_stock';

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;

    /**
     * YellowCubeShipmentItemRepository constructor.
     *
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(\Magento\Framework\App\ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Updates the YellowCube stock.
     *
     * @param \YellowCube\BAR\Article[] $articles
     *
     */
    public function updateStock(array $articles)
    {
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();

        $connection->delete(static::TABLE_NAME);

        $data = [];

        foreach ($articles as $article) {
            $data[] = [
                'sku' => $article->getArticleNo(),
                'quantity' => $article->getQuantityUOM()->get(),
                'lot' => $article->getLot(),
                'best_before_date' => date('Y-m-d', strtotime($article->getBestBeforeDate())),
            ];
        }
        $connection->insertMultiple(static::TABLE_NAME, $data);
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
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);

        $result = $connection->query('SELECT * FROM ' . $table_name);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            unset($row['id']);
            $shipmentIds[] = $row;
        }
        return $shipmentIds;
    }
}
