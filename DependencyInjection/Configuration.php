<?php

namespace vaterlangen\CalDavBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('vaterlangen_cal_dav');

        $rootNode
        	->children()
        		->scalarNode('enabled')->defaultValue(false)->end()
        		->arrayNode('calendars')
        			->useAttributeAsKey('id')
        			->prototype('array')
        				->children()
			        		->scalarNode('server')->isRequired()->end()
			        		->scalarNode('ssl')->defaultValue(true)->end()
			        		->integerNode('port')->defaultValue(0)->end()
			        		->scalarNode('user')->isRequired()->end()
			        		->scalarNode('email')->defaultValue(NULL)->end()
			        		->scalarNode('password')->isRequired()->end()
			        		->scalarNode('resource')->isRequired()->end()
			        		->arrayNode('categories')
			        			->prototype('scalar')->end()
			        		->end()
                            ->arrayNode('organizer')
                                ->children()
                                    ->scalarNode('name')->defaultValue(NULL)->end()
                                    ->scalarNode('mail')->defaultValue(NULL)->end()
                                ->end()
                            ->end()
                            ->arrayNode('reminder')
                                ->children()
                                    ->scalarNode('enabled')->defaultValue(false)->end()
                                    ->scalarNode('trigger')->defaultValue("-PT30M")->end()
                                    ->scalarNode('message')->defaultValue("Reminder!")->end()
                                    ->scalarNode('repeatcount')->defaultValue(1)->end()
                                    ->scalarNode('repeatdelay')->defaultValue("-PT15M")->end()
                                ->end()
                            ->end()
			        	->end()
			        ->end()
			   	->end()
			->end()
        ;

        return $treeBuilder;
    }
}
