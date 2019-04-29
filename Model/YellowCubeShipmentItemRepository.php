<?php

namespace Swisspost\YellowCube\Model;

use Magento\Sales\Api\Data\ShipmentItemInterface;

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

    public function insertShipmentItem(ShipmentItemInterface $shipmentItem, $reference)
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);
        $connection->insert($table_name, [
           'shipment_id' => $shipmentItem->getEntityId(),
           'shipment_item_id' => $shipmentItem->getParentId(),
           'product_id' => $shipmentItem->getProductId(),
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

    public function getByReference($reference)
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);

        $result = $connection->query('SELECT DISTINCT * FROM ' . $table_name . ' WHERE reference = :reference', [':reference' => $reference]);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            $shipmentIds[$row['shipment_item_id']] = $row;
        }
        return $shipmentIds;
    }

    public function getUnshippedShipmentIdsByProductId($product_id, $shipped_timestamp)
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);

        $bind = [
            ':sent' => static::STATUS_SENT,
            ':confirmed' => static::STATUS_CONFIRMED,
            'shipped' => static::STATUS_SHIPPED,
            ':shipped_timestamp' => date('Y-m-d H:i:s', $shipped_timestamp),
        ];

        $result = $connection->query('SELECT DISTINCT shipment_id, reference FROM ' . $table_name . ' WHERE status = :sent OR status = :confirmed OR (status = :shipped AND timestamp > :shipped_timestamp)', $bind);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            $shipmentIds[] = $row['shipment_id'];
        }
        return $shipmentIds;
    }

    public function getShipmentsByStatus($status)
    {
        $connection = $this->resource->getConnection();
        $table_name = $this->resource->getTableName(static::TABLE_NAME);

        $bind = [
            ':status' => $status,
        ];

        $result = $connection->query('SELECT DISTINCT shipment_id, reference FROM ' . $table_name . ' WHERE status = :status', $bind);
        $shipmentIds = [];
        while ($row = $result->fetch()) {
            $shipmentIds[] = $row['shipment_id'];
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
