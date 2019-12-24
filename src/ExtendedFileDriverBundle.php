<?php

namespace Nekowonderland\ExtendedFileDriver;

use Nekowonderland\ExtendedFileDriver\DependencyInjection\ExtendedFileDriverExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MultiColumnWizardBundle
 *
 * @package MenAtWork\SyncCtoBundle
 */
class ExtendedFileDriverBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new ExtendedFileDriverExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        // disable automatic command registration
    }
}
