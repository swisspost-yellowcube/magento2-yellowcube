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
- `./bin/magento deploy:mode:set production`


To add the Patch for MysqlMq:

```
composer require cweagans/composer-patches

# Add inside extra:

        "patches": {
            "magento/module-mysql-mq": {
                " MysqlMq: Use new MessageQueue Config interface, update unit tests #21942": "https://raw.githubusercontent.com/swisspost-yellowcube/magento2-yellowcube/master/21942.diff"
            }
        }
        
composer update --lock


```

## Configuration

In Menu `Stores > Configuration > Sales > Shipping Methods`. enable YellowCube and configure it based on the received
information.

In Menu `Stores > Stocks`, create a Stock that contains at least the automatically created YellowCube source. 

### User Manual / Configuration Manual

@TODO

## Known issues/current state

* Sync operations on settings form are not yet working.

