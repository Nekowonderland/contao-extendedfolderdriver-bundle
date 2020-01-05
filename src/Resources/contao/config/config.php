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

$GLOBALS['TL_HOOKS']['loadDataContainer'][] = array(
    Nekowonderland\ExtendedFolderDriver\Listeners\LoadDataContainerListener::class,
    'updateFolderSettings'
);
