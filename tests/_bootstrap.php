<?php
/**
 * ownCloud - galleryplus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Olivier Paroz <owncloud@interfasys.ch>
 *
 * @copyright Olivier Paroz 2016
 */

use Codeception\Util\Autoload;

define('PHPUNIT_RUN', 1);

// Add core
require_once __DIR__ . '/../../../lib/base.php';

// Give access to core tests to Codeception
Autoload::addNamespace('Test', '/../../../tests/lib');

// Load all apps
OC_App::loadApps();

OC_Hook::clear();
