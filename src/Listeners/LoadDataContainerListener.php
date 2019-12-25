<?php

namespace Nekowonderland\ExtendedFolderDriver\Listeners;

/**
 * Class LoadDataContainerListener
 *
 * @package Nekowonderland\ExtendedFolderDriver\Listeners
 */
class LoadDataContainerListener
{
    /**
     * @param $name
     */
    public function updateFolderSettings($name)
    {
        if ('BE' !== TL_MODE || 'tl_files' !== $name) {
            return;
        }

        $GLOBALS['TL_JAVASCRIPT'][]                               = 'bundles/extendedfolderdriver/js/handler.js';
        $GLOBALS['TL_DCA']['tl_files']['config']['dataContainer'] = 'ExtendedFolder';
    }
}