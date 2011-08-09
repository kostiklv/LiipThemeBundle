<?php

namespace Liip\ThemeBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class ThemeCompilerPass implements CompilerPassInterface
{
    private $kernel;
    
    public function __construct($kernel)
    {
        $this->kernel = $kernel;
    }
    
    public function process(ContainerBuilder $container)
    {
        // Replace templating.
        $container->getDefinition('templating.locator')
            ->replaceArgument(0, new Reference('liip_theme.file_locator'))
        ;
        // Fix directories, where Assetic looks for templates
        // which may contain assets
        // Without this Assetic won't create routes for such resources
        if ($container->has('assetic.asset_manager')) {
            $themes = $container->getParameter('liip_theme.themes');
            foreach ($this->kernel->getBundles() as $bundle) {
                $rc = new \ReflectionClass($bundle);
                foreach (array('twig', 'php') as $engine) {
                    $bundleName = $bundle->getName();
                    $theme_folders = array();
                    foreach ($themes as $theme) {
                        $theme_folders[] = $container->getParameter('kernel.root_dir').'/Resources/themes/'.$theme.'/'.$bundleName.'/views';
                    }
                    $container->setDefinition(
                        'assetic.'.$engine.'_directory_resource.'.$bundleName,
                        new \Symfony\Bundle\AsseticBundle\DependencyInjection\DirectoryResourceDefinition($bundleName, $engine, array_merge($theme_folders, array(
                            $container->getParameter('kernel.root_dir').'/Resources/'.$bundleName.'/views',
                            dirname($rc->getFileName()).'/Resources/views',
                        )))
                    );
                }
            }
        }
    }
}
