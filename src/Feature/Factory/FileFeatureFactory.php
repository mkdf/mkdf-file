<?php
namespace MKDF\File\Feature\Factory;

use Interop\Container\ContainerInterface;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\File\Repository\MKDFFileRepositoryInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use MKDF\File\Feature\FileFeature;

class FileFeatureFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $dataset_repository = $container->get(MKDFDatasetRepositoryInterface::class);
        $file_repository = $container->get(MKDFFileRepositoryInterface::class);
        return new FileFeature($file_repository,$dataset_repository);
    }
}