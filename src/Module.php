<?php


namespace MKDF\File;


use MKDF\Datasets\Service\DatasetsFeatureManagerInterface;
use MKDF\File\Feature\FileFeature;
use Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        $moduleConfig = include __DIR__ . '/../config/module.config.php';
        return $moduleConfig;
    }

    /**
     * This method is called once the MVC bootstrapping is complete and allows
     * to register event listeners.
     */
    public function onBootstrap(MvcEvent $event)
    {
        $featureManager = $event->getApplication()->getServiceManager()->get(DatasetsFeatureManagerInterface::class);
        $featureManager->registerFeature($event->getApplication()->getServiceManager()->get(FileFeature::class));
    }
}