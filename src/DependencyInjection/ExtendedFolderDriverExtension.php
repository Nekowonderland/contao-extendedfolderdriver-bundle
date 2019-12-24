<?php

namespace Nekowonderland\ExtendedFolderDriver\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MultiColumnWizardExtension
 */
class ExtendedFolderDriverExtension extends Extension
{
    /**
     * The config files.
     *
     * @var array
     */
    private $files = [
        'listener.yml',
        'services.yml',
    ];

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'extendedfolderdriver-bundle';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        foreach ($this->files as $file) {
            $loader->load($file);
        }
    }
}
