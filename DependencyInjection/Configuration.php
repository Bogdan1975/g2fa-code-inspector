<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 23.03.2018
 * Time: 10:21
 */

namespace Targus\G2faCodeInspector\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('g2fa_code_inspector');

        $rootNode
            ->children()
                ->scalarNode('oneTimeCode')
                    ->defaultValue(false)
                ->end()
                ->scalarNode('window')
                    ->defaultValue(1)
                ->end()
                ->scalarNode('headerName')
                    ->defaultValue('X-G2FA-VERIFICATION-CODE')
                ->end()
            ->end();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
