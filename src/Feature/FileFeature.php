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
        return 'upload';
    }
    public function getViewHref($id){
        return '/dataset/file/details/'.$id;
    }
    public function getEditHref($id){
        return '/dataset/file/upload/'.$id;
    }
    public function hasFeature($id){
        // Make a DB call for this dataset to see if it's a file dataset
        $dataset = $this->_dataset_repository->findDataset($id);
        //files now stored with regular API/Stream datasets
        //if (strtolower($dataset->type) == 'file') {
        if (strtolower($dataset->type) == 'stream') {
            return true;
        }
        else {
            return false;
        }

    }
    public function getLabel(){
        return '<i class="fas fa-folder-open"></i> Files';
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