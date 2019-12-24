<?php

namespace Nekowonderland\ExtendedFolderDriver;

use Nekowonderland\ExtendedFolderDriver\DependencyInjection\ExtendedFolderDriverExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MultiColumnWizardBundle
 *
 * @package MenAtWork\SyncCtoBundle
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
