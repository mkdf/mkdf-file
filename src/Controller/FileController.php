<?php
namespace MKDF\File\Controller;

use MKDF\File\Form\FileForm;
use MKDF\Datasets\Repository\MKDFDatasetRepositoryInterface;
use MKDF\Datasets\Service\DatasetPermissionManager;
use MKDF\Keys\Repository\MKDFKeysRepositoryInterface;
use MKDF\Stream\Repository\MKDFStreamRepositoryInterface;
use Zend\Mvc\Controller\AbstractActionController;
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
            return new ViewModel([
                'message' => $message,
                'dataset' => $dataset,
                'features' => $this->datasetsFeatureManager()->getFeatures($id),
                'actions' => $actions,
                'can_edit' => $can_edit,
                'can_read' => $can_read,
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
            $actions = [
                'label' => 'Actions',
                'class' => '',
                'buttons' => [
                ]
            ];

            $form = new FileForm($this->_repository);
            $request = $this->getRequest();
            if ($request->isPost()) {
                // Make certain to merge the $_FILES info!
                $post = array_merge_recursive(
                    $request->getPost()->toArray(),
                    $request->getFiles()->toArray()
                );

                $form->setData($post);
                if ($form->isValid()) {
                    $data = $form->getData();

                    // Form is valid, save the form!
                    return $this->redirect()->toRoute('upload-form/success');
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

}