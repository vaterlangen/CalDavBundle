<?php

namespace vaterlangen\CalDavBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class vaterlangenCalDavExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
 public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        
        $calendars = isset($config['calendars']) ? $config['calendars'] : array();
        $container->setParameter('vaterlangen_cal_dav.calendars', $calendars);
        $container->setParameter('vaterlangen_cal_dav.enabled', $config['enabled']);
    }
}
