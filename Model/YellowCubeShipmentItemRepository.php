<?php

namespace Swisspost\YellowCube\Model;

class YellowCubeShipmentItemRepository
{
    const TABLE_NAME = 'yellowcube_shipment_item';

    const STATUS_SENT = 0;
    const STATUS_CONFIRMED = 1;
    const STATUS_SHIPPED = 2;
    const STATUS_ERROR = 99;

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

    public function insertShipmentItem($shipmentId, $shipmentItemId, $reference)
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);
        $connection->insert($table_name, [
           'shipment_id' => $shipmentId,
           'shipment_item_id' => $shipmentItemId,
            'reference' => $reference,
        ]);
    }

    public function getUnconfirmedShipments()
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);

        $result = $connection->query('SELECT DISTINCT shipment_id, reference FROM ' . $table_name . ' WHERE status = ' . static::STATUS_SENT);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            $shipmentIds[$row['shipment_id']] = $row['reference'];
        }
        return $shipmentIds;
    }

    public function updateByShipmentId($shipmentId, $status, $message = NULL)
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);
        $connection->update($table_name, ['status' => $status, 'message' => $message], 'shipment_id = ' . ((int) $shipmentId));
    }
}
