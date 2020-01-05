<?php

/**
 * This file is part of ExtendedFolderDriver
 *
 * (c) 2019-2020 Stefan Heimes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package   ExtendedFolderDriver
 * @author    Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright 2019-2020 Stefan Heimes
 * @license   https://github.com/Nekowonderland/contao-extendedfolderdriver-bundle/blob/master/LICENSE LGPL-3.0-or-later
 */

namespace Nekowonderland\ExtendedFolderDriver;

use Nekowonderland\ExtendedFolderDriver\DependencyInjection\ExtendedFolderDriverExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class ExtendedFolderDriverBundle
 *
 * @package Nekowonderland\ExtendedFolderDriver
 */
class ExtendedFolderDriverBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new ExtendedFolderDriverExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        // disable automatic command registration
    }
}
