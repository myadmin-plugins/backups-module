# Backup Services Module for MyAdmin

[![Tests](https://github.com/detain/myadmin-backups-module/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-backups-module/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-backups-module/version)](https://packagist.org/packages/detain/myadmin-backups-module)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-backups-module/downloads)](https://packagist.org/packages/detain/myadmin-backups-module)
[![License](https://poser.pugx.org/detain/myadmin-backups-module/license)](https://packagist.org/packages/detain/myadmin-backups-module)

A MyAdmin plugin module that provides backup service management, including provisioning, activation, suspension, reactivation, and termination of backup accounts. Supports Acronis Cloud Backup and DirectAdmin storage backends.

## Features

- Automated lifecycle management (enable, disable, reactivate, terminate) for backup services
- Acronis Cloud Backup integration with API credential configuration
- DirectAdmin storage backup support
- Configurable billing with prorate support, suspension warnings, and auto-deletion
- Admin settings UI for server management, stock control, and Acronis API credentials
- Email notifications for pending setups and reactivations

## Requirements

- PHP >= 5.0
- ext-soap
- symfony/event-dispatcher ^5.0
- The MyAdmin platform (detain/myadmin-plugin-installer)

## Installation

Install via Composer:

```sh
composer require detain/myadmin-backups-module
```

The module registers itself with the MyAdmin event dispatcher automatically through its plugin hooks.

## Configuration

The plugin exposes the following settings through the MyAdmin admin panel:

| Setting | Description |
|---------|-------------|
| `outofstock_backups` | Enable or disable sales of backup services |
| `acronis_username` | Acronis API login name |
| `acronis_password` | Acronis API password |
| `acronis_api_client_id` | Acronis API client ID |
| `acronis_api_secret` | Acronis API secret |

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

To generate a coverage report:

```sh
vendor/bin/phpunit --coverage-text
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html) license.
