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

namespace Nekowonderland\ExtendedFolderDriver\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerBundle\ContaoManagerBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Nekowonderland\ExtendedFolderDriver\ExtendedFolderDriverBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class Plugin
 *
 * @package Nekowonderland\ExtendedFolderDriver\ContaoManager
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(ExtendedFolderDriverBundle::class)
                        ->setLoadAfter(
                            [
                                ContaoCoreBundle::class,
                                ContaoManagerBundle::class,
                            ]
                        )
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__ . '/../Resources/config/routing.yml')
            ->load(__DIR__ . '/../Resources/config/routing.yml');
    }
}
