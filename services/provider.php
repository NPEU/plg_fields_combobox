<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

use NPEU\Plugin\Fields\ComboBox\Extension\ComboBox;


return new class () implements ServiceProviderInterface {
    /*public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container)
            {
                $dispatcher = $container->get(DispatcherInterface::class);
                $config  = (array) PluginHelper::getPlugin('fields', 'combobox');

                $app = Factory::getApplication();

=                $plugin = new ComboBox(
                    $dispatcher,
                    $config,
                    $app->isClient('administrator')
                );
                $plugin->setApplication($app);

                return $plugin;
            }
        );
    }*/

    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin     = new ComboBox(
                    (array) PluginHelper::getPlugin('fields', 'combobox')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};