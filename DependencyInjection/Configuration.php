<?php

namespace Webstack\ApiPlatformExtensionsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('webstack_api_platform_extensions');

        $rootNode->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('identifier_class')
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
