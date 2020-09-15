<?php
namespace MKDF\File\Controller;

use MKDF\File\Form\FileForm;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManager;
use MKDF\Keys\Repository\MKDFKeysRepositoryInterface;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\View\Model\ViewModel;

class FileController extends AbstractActionController
{
    private $_config;
    private $_repository;
    private $_dataset_repository;
    private $_keys_repository;
    private $_permissionManager;

    public function __construct(MKDFKeysRepositoryInterface $keysRepository, MKDFDatasetRepositoryInterface $datasetRepository, $repository, array $config, DatasetPermissionManager $permissionManager)
    {
        $this->_config = $config;
        $this->_repository = $repository;
        $this->_dataset_repository = $datasetRepository;
        $this->_keys_repository = $keysRepository;
        $this->_permissionManager = $permissionManager;
    }

    public function detailsAction() {
        $user_id = $this->currentUser()->getId();
        $id = (int) $this->params()->fromRoute('id', 0);
        //FIXME - Also make sure this is a file dataset that we are retrieving.
        $dataset = $this->_dataset_repository->findDataset($id);
        $message = "Dataset: " . $id;
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);

        $actions = [
            'label' => 'Actions',
            'class' => '',
            'buttons' => [
            ]
        ];
        if ($can_write) {
            $actions['buttons'][] = [

                    'type' => 'primary',
                    'label' => 'Upload new file',
                    'icon' => 'create',
                    'target' => 'file',
                    'params' => [
                        'action' => 'upload',
                        'id' => $id
                    ]

            ];
        }
        if ($can_view) {
            $files = $this->_repository->findDatasetFiles($id);
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'files'   => $files,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                'can_view' => $can_view
            ]);
        }
        else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to view dataset.');
            return $this->redirect()->toRoute('dataset', ['action'=>'index']);
        }
    }

    public function uploadAction() {
        $user_id = $this->currentUser()->getId();
        $id = (int) $this->params()->fromRoute('id', 0);
        $dataset = $this->_dataset_repository->findDataset($id);
        $message = "Dataset: " . $id;
        $actions = [];
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);

        if ($can_write) {
            $form = new FileForm($this->_repository);
            $request = $this->getRequest();
            if ($request->isPost()) {
                // Make certain to merge the $_FILES info!
                $post = array_merge_recursive(
                    $request->getPost()->toArray(),
                    $request->getFiles()->toArray()
                );

                $form->setData($post);
                if ($form->isValid() && $_FILES['data-file']['error'] == 0) {
                    $data = $form->getData();
                    // Form is valid, save the form!

                    //Move file to correct location and create DB entry...
                    $this->_repository->createFileEntry($data, $dataset);

                    $this->flashMessenger()->addSuccessMessage('File uploaded.');
                    return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $id]);
                }
                else {
                    if ($_FILES['data-file']['error'] != 0) {
                        $this->flashMessenger()->addErrorMessage('File error: '.$_FILES['data-file']['error']);
                    }
                }
            }

            return new ViewModel([
                'form' => $form,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
            ]);
            /*
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
            ]);
            */
        }
        else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to write to dataset.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $id]);
        }
    }

    public function downloadAction() {
        $user_id = $this->currentUser()->getId();
        $fileId = (int) $this->params()->fromRoute('id', 0);
        $file = $this->_repository->findFile($fileId);
        $dataset = $this->_dataset_repository->findDataset($file['dataset_id']);
        $actions = [];
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        if ($can_read && !is_null($file)) {
            $fileName = $file['location'].$dataset->uuid."/".$file['filename'];

            $response = new \Zend\Http\Response\Stream();
            $response->setStream(fopen($fileName, 'r'));
            $response->setStatusCode(200);
            $response->setStreamName($file['filename_original']);
            $headers = new \Zend\Http\Headers();
            $headers->addHeaders(array(
                'Content-Disposition' => 'attachment; filename="' . $file['filename_original'] .'"',
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => filesize($fileName),
                'Expires' => '@0', // @0, because zf2 parses date as string to \DateTime() object
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public'
            ));
            $response->setHeaders($headers);
            return $response;
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to read files from this dataset.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $file['dataset_id']]);
        }
    }

    public function deleteConfirmAction() {
        //
        $fileId = (int) $this->params()->fromRoute('id', 0);
        $file = $this->_repository->findFile($fileId);
        $dataset = $this->_dataset_repository->findDataset($file['dataset_id']);
        $user_id = $this->currentUser()->getId();
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);
        if($can_edit){
            $token = uniqid(true);
            $container = new Container('File_Management');
            $container->delete_token = $token;
            $messages[] = [ 'type'=> 'warning', 'message' =>
                'Are you sure you want to delete this file?'];
            return new ViewModel(['file' => $file, 'dataset' => $dataset, 'token' => $token, 'messages' => $messages]);
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to delete file.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $file['dataset_id']]);
        }
    }

    public function deleteAction(){
        $fileId = (int) $this->params()->fromRoute('id', 0);
        $token = $this->params()->fromQuery('token', '');
        $file = $this->_repository->findFile($fileId);
        $dataset = $this->_dataset_repository->findDataset($file['dataset_id']);
        $user_id = $this->currentUser()->getId();
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);
        if($dataset == null){
            throw new \Exception('Not found');
        }

        $container = new Container('File_Management');
        $valid_token = ($container->delete_token == $token);
        if($can_edit && $valid_token){
            $outcome = $this->_repository->deleteFile($fileId);
            unset($container->delete_token);
            $this->flashMessenger()->addSuccessMessage('The file was deleted successfully.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $dataset->id]);
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised. Delete token was ' . (($valid_token)?'valid':'invalid') . '.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $file['dataset_id']]);
        }
    }

}