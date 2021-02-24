<?php

namespace Webstack\ApiPlatformExtensionsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('webstack_api_platform_extensions');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('identifier_class')
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
