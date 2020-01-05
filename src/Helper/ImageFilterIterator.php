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

namespace Nekowonderland\ExtendedFolderDriver\Helper;

use RecursiveFilterIterator;

/**
 * Class ImageFilterIterator
 *
 * @package Nekowonderland\ExtendedFolderDriver\Helper
 */
class ImageFilterIterator extends RecursiveFilterIterator
{
    /**
     * @var array
     */
    private static $filter = [];

    /**
     * @param array $allowedFiles
     *
     * @return void
     */
    public static function setFilter(array $allowedFiles): void
    {
        self::$filter = $allowedFiles;
    }

    /**
     * @inheritDoc
     */
    public function accept(): bool
    {
        /** @var \SplFileInfo $file */
        $file = $this->current();
        if ($file->isDir()) {
            return true;
        }

        if ($file->isFile() && in_array($file->getExtension(), self::$filter)) {
            return true;
        }

        return false;
    }
}