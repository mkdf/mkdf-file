<?php
namespace MKDF\File\Controller\Factory;

use Interop\Container\ContainerInterface;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManagerInterface;
use MKDF\File\Repository\MKDFFileRepositoryInterface;
use MKDF\Keys\Repository\MKDFKeysRepositoryInterface;
use MKDF\File\Controller\FileController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Session\SessionManager;

class FileControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(MKDFFileRepositoryInterface::class);
        //$core_repository = $container->get(MKDFCoreRepositoryInterface::class);
        $dataset_repository = $container->get(MKDFDatasetRepositoryInterface::class);
        $keys_repository = $container->get(MKDFKeysRepositoryInterface::class);
        $sessionManager = $container->get(SessionManager::class);
        $permissionManager = $container->get(DatasetPermissionManagerInterface::class);
        return new FileController($keys_repository, $dataset_repository, $repository, $config, $permissionManager);
    }
}