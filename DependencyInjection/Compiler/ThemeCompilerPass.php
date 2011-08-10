<?php

namespace Liip\ThemeBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class ThemeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Replace templating.
        $container->getDefinition('templating.locator')
            ->replaceArgument(0, new Reference('liip_theme.file_locator'))
        ;
        
        // Fix directories, where Assetic looks for templates
        // Without this Assetic won't create routes for assets mentioned in theme templates
        if ($container->has('assetic.asset_manager')) {
            $themes = $container->getParameter('liip_theme.themes');
            
            // Fix bundle folders
            foreach ($container->getParameter('kernel.bundles') as $bundleName => $bundleClass) {
                foreach (array('twig', 'php') as $engine) {
                    if (!$container->hasDefinition('assetic.'.$engine.'_directory_resource.'.$bundleName)) {
                        continue;
                    }
                    $newResources = array();
                    foreach ($themes as $theme) {
                        $newResources[] = new \Symfony\Bundle\AsseticBundle\DependencyInjection\DirectoryResourceDefinition(
                            $bundleName, 
                            $engine, 
                            array($container->getParameter('kernel.root_dir').'/Resources/themes/'.$theme.'/'.$bundleName.'/views')
                        );
                    }
                    
                    // Bundle DirectoryResourceDefinition are always CoalescingDirectoryResource
                    // so we can safely merge our theme folders on top of existing folders
                    $container->getDefinition('assetic.'.$engine.'_directory_resource.'.$bundleName)
                        ->replaceArgument(
                            0,
                            array_merge(
                                $newResources, 
                                $container->getDefinition('assetic.'.$engine.'_directory_resource.'.$bundleName)
                                    ->getArgument(0)
                            ) 
                        );
                }
            }
            
            // Fix base Resources/views folder
            $folders = array();
            foreach ($themes as $theme) {
                $folders[] = $container->getParameter('kernel.root_dir').'/Resources/themes/'.$theme.'/views';
            }
            $folders[] = $container->getParameter('kernel.root_dir').'/Resources/views';
            
            // Base (Kernel's) DirectoryResourceDefinition is normally 
            // a single directory, so we need to rebuild it from scratch (can't just merege params)
            // DirectoryResourceDefinition automatically creates CoalescingDirectoryResource
            // when there are more than 1 directory
            foreach (array('twig', 'php') as $engine) {
                $container->setDefinition(
                    'assetic.'.$engine.'_directory_resource.kernel',
                    new \Symfony\Bundle\AsseticBundle\DependencyInjection\DirectoryResourceDefinition('', $engine, $folders)
                );
            }
        }
    }
}
