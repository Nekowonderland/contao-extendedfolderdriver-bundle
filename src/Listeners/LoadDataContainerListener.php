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

declare(strict_types=1);

namespace Nekowonderland\ExtendedFolderDriver\Listeners;

/**
 * Class LoadDataContainerListener
 *
 * @package Nekowonderland\ExtendedFolderDriver\Listeners
 */
class LoadDataContainerListener
{
    /**
     * If the backend is loaded and the table 'tl_files' is active change some settings for the new driver.
     *
     * @param string $name Name of the current table.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function updateFolderSettings(string $name): void
    {
        if ('BE' !== TL_MODE || 'tl_files' !== $name) {
            return;
        }

        $GLOBALS['TL_JAVASCRIPT'][]                               = 'bundles/extendedfolderdriver/js/handler.js';
        $GLOBALS['TL_DCA']['tl_files']['config']['dataContainer'] = 'ExtendedFolder';
    }
}
