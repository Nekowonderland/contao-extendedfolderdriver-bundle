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
        if ('tl_files' !== $name) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_files']['config']['dataContainer'] = 'ExtendedFolder';
    }
}