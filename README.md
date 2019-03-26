# Magento 2 integration module for YellowCube from Post AG - Switzerland

## Description

__NOTE__: This module is in early development and not yet functional.

## License

This extension is licensed under OSL v.3.0
Some classes and javascript contain a MIT license.

## System requirements

- Magento CE >= 2.3
- PHP >= 7.1 (as required by Magento 2.3)
- PHP Soap, DOM Library, mbstring,
- Cron enabled and configured for Magento 2

This relies on the new MessageQueue component in Magento 2.3 to synchronize data asynchronously. It defaults to the
MysqlMq implementation, which has a known issue: https://github.com/magento/magento2/issues/21904.

Alternatively, override it to use the RabbitMQ adapter. @todo: Define how. 

The store locale must be set to a locale supported by YellowCube (DE/FR/IT/EN-GB).

## Installation

- `composer require swisspost-yellowcube/magento2-yellowcube`
- `./bin/magento module:enable Swisspost_YellowCube`

## Configuration

In Menu `Stores > Configuration > Sales > Shipping Methods`

@todo Store locale must be set to DE or EN UK to use cm and not inch.

### User Manual / Configuration Manual

@TODO

## Known issues/current state

* The shipping methods are not selected correctly yet after saving, but they are saved.
* Sync operations on settings form are not yet working.
* Stock/Source management is not yet working

