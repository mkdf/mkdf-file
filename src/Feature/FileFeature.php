<?php
namespace MKDF\File\Feature;

use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetsFeatureInterface;
use MKDF\File\Repository\MKDFFileRepositoryInterface;

class FileFeature implements DatasetsFeatureInterface
{
    private $active = false;

    private $_dataset_repository;
    private $_repository;

    public function __construct(MKDFFileRepositoryInterface $repository, MKDFDatasetRepositoryInterface $datasetRepository)
    {
        $this->_dataset_repository = $datasetRepository;
        $this->_repository = $repository;
    }

    public function getController() {
        return \MKDF\File\Controller\FileController::class;
    }
    public function getViewAction(){
        return 'details';
    }
    public function getEditAction(){
        return 'index';
    }
    public function getViewHref($id){
        return '/dataset/file/details/'.$id;
    }
    public function getEditHref($id){
        return '/dataset/file/details/'.$id;
    }
    public function hasFeature($id){
        // Make a DB call for this dataset to see if it's a stream dataset
        $dataset = $this->_dataset_repository->findDataset($id);
        if ($dataset->type == 2) {
            return true;
        }
        else {
            return false;
        }
    }
    public function getLabel(){
        return 'Files';
    }
    public function isActive(){
        return $this->active;
    }
    public function setActive($bool){
        $this->active = !!$bool;
    }

    public function initialiseDataset($id) {

    }
}