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

        //Does the user have a key on this stream
        $userHasKey = $this->_keys_repository->userHasDatasetKey($user_id,$dataset->id);

        if ($can_write && $userHasKey) {
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

        if ($can_view && $can_read && $userHasKey) {
            $dataset = $this->_dataset_repository->findDataset($id);
            $uuid = $dataset->uuid;
            $files = $this->_repository->findDatasetFiles($uuid);
            $keys = $this->_keys_repository->userDatasetKeys($user_id,$dataset->id);
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'files'   => $files,
                'keys' => $keys,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
                'can_view' => $can_view,
                'can_write' => $can_write,
                'user_has_key' => $userHasKey,
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
        $userHasKey = $this->_keys_repository->userHasDatasetKey($user_id,$dataset->id);

        if ($can_write && $userHasKey) {
            $keys = $this->_keys_repository->userDatasetKeys($user_id,$dataset->id);
            $form = new FileForm($this->_repository);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $keyPassed = $request->getPost('key');
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
                    $this->_repository->createFileEntry($data, $dataset->uuid, $keyPassed);

                    $this->flashMessenger()->addSuccessMessage('File uploaded.');
                    return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $id]);
                }
                else {
                    $this->flashMessenger()->addErrorMessage('Form submission invalid.');
                    if ($_FILES['data-file']['error'] != 0) {
                        $this->flashMessenger()->addErrorMessage('Uploaded file missing or too large.');
                    }
                }
            }

            return new ViewModel([
                'form' => $form,
                'dataset' => $dataset,
                'keys' => $keys,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
            ]);
        }
        else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to write to dataset.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $id]);
        }
    }

    public function downloadAction() {
        $user_id = $this->currentUser()->getId();
        $datasetID =  $this->params()->fromRoute('id', 0);
        $filename = rawurldecode($this->params()->fromRoute('filename'));
        $keyPassed = $this->params()->fromQuery('key', null);
        $dataset = $this->_dataset_repository->findDataset($datasetID);
        $datasetUUID = $dataset->uuid;
        $actions = [];
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $userHasKey = $this->_keys_repository->userHasDatasetKey($user_id,$dataset->id);
        if ($can_read && $userHasKey) {
            $response = $this->_repository->getFile($datasetUUID,$filename,$keyPassed);
            if ($response['response']) {
                $vm = new ViewModel(['data' => $response['response']]);
                $this->getResponse()->setStatusCode($response['curlInfo']['http_code']);
                //force file to download as an attachment rather than render/open in the browser...
                $this->getResponse()->getHeaders()->addHeaders([
                    'Content-Disposition' => 'attachment; filename="' . $filename .'"',
                ]);
                $this->getResponse()->getHeaders()->addHeaders([
                    'Content-Type' => $response['curlInfo']['content_type']
                ]);
                $this->getResponse()->getHeaders()->addHeaders([
                    'Content-Length' => $response['curlInfo']['download_content_length']
                ]);
                $vm->setTerminal(true); //Send response back verbatim, no header/footer padding
                return $vm;
            }
            else {
                //$response['response'] as false suggests no response from backend
                $this->getResponse()->setStatusCode(500);
                return new JsonModel(['error' => 'No response from filestore']);
            }
        }
        else {
            $this->flashMessenger()->addErrorMessage('Unauthorised to read files from this dataset.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $file['dataset_id']]);
        }
    }

    public function deleteConfirmAction() {
        //
        $datasetID =  $this->params()->fromRoute('id', 0);
        $filename = rawurldecode($this->params()->fromRoute('filename'));
        $dataset = $this->_dataset_repository->findDataset($datasetID);
        $datasetUUID = $dataset->uuid;
        $keyPassed = $this->params()->fromQuery('key', null);
        $user_id = $this->currentUser()->getId();
        $can_view = $this->_permissionManager->canView($dataset,$user_id);
        $can_read = $this->_permissionManager->canRead($dataset,$user_id);
        $can_edit = $this->_permissionManager->canEdit($dataset,$user_id);
        $can_write = $this->_permissionManager->canWrite($dataset,$user_id);
        if($can_write){
            $token = uniqid(true);
            $container = new Container('File_Management');
            $container->delete_token = $token;
            $messages[] = [ 'type'=> 'warning', 'message' =>
                'Are you sure you want to delete this file?'];
            return new ViewModel(['filename' => $filename, 'dataset' => $dataset, 'token' => $token, 'key' => $keyPassed, 'messages' => $messages]);
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised to delete file.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $file['dataset_id']]);
        }
    }

    public function deleteAction(){
        $datasetID =  $this->params()->fromRoute('id', 0);
        $filename = $this->params()->fromRoute('filename');
        $dataset = $this->_dataset_repository->findDataset($datasetID);
        $keyPassed = $this->params()->fromQuery('key', null);
        $datasetUUID = $dataset->uuid;
        $token = $this->params()->fromQuery('token', '');
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
        if($can_write && $valid_token){
            $outcome = $this->_repository->deleteFile($datasetUUID, $filename, $keyPassed);
            //print_r($outcome);
            unset($container->delete_token);
            $this->flashMessenger()->addSuccessMessage('The file was deleted successfully.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $dataset->id]);
        }else{
            $this->flashMessenger()->addErrorMessage('Unauthorised. Delete token was ' . (($valid_token)?'valid':'invalid') . '.');
            return $this->redirect()->toRoute('file', ['action'=>'details', 'id' => $file['dataset_id']]);
        }
    }
}