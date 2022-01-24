<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
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
