<?php
namespace MKDF\File\Repository\Factory;

use Interop\Container\ContainerInterface;
use MKDF\File\Repository\MKDFFileRepository;
use Zend\ServiceManager\Factory\FactoryInterface;

class MKDFFileRepositoryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new MKDFFileRepository($config);
    }

}