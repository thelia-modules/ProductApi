<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Run from the root of the Thelia project the module is installed in, against a disposable database whose name
 * ends with `_test` (see the Readme). The caller provides the DATABASE_* variables and KERNEL_CLASS.
 *
 * THELIA_TEST_CACHE_DIR (optional, relative to the project root) gives these tests their own compiled container:
 * a cache directory shared with other test runs holds the module list of another database.
 */

use Symfony\Component\Dotenv\Dotenv;

$projectRoot = getcwd();

if (false !== $cacheDir = getenv('THELIA_TEST_CACHE_DIR')) {
    \define('THELIA_CACHE_DIR', $projectRoot.'/'.trim($cacheDir, '/').'/');
}

require $projectRoot.'/bootstrap.php';
require $projectRoot.'/vendor/autoload.php';

(new Dotenv())->bootEnv($projectRoot.'/.env');
