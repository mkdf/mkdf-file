<?php
namespace MKDF\File\Form;

use MKDF\File\Repository\MKDFFileRepositoryInterface;
use Zend\Form\Form;
use Zend\InputFilter;
use Zend\Form\Element\Hidden;
use Zend\Form\Element\File;
use Zend\Form\Element\Text;
use Zend\Form\Element\Textarea;
use Zend\Form\Element\Radio;
use Zend\Form\Element\Submit;

class FileForm extends Form
{
    private $_repository;

    // Constructor.
    public function __construct(MKDFFileRepositoryInterface $repository)
    {
        // Define form name
        parent::__construct('file-form');
        // Set POST method for this form
        $this->setAttribute('method', 'post');

        $this->_repository = $repository;
        $this->addElements();
        $this->addInputFilter();

    }

    /**
     * This method adds elements to form (input fields and submit button).
     */
    protected function addElements()
    {
        // Add "title" field
        $this->add([
            'type'  => 'text',
            'name' => 'title',
            'options' => [
                'label' => 'Title',
            ],
        ]);

        // Add "description" field
        $this->add([
            'type'  => 'textarea',
            'name' => 'description',
            'options' => [
                'label' => 'Description',
            ],
        ]);

        // File Input
        $file = new File('data-file');
        $file->setLabel('File');
        $file->setAttribute('id', 'data-file');
        $this->add($file);

        /*
        // Add the max file size hidden attribute
        $this->add([
            'type'  => 'hidden',
            'name' => 'MAX_FILE_SIZE',
            'attributes' => [
                'value' => '1024'
            ],
        ]);
        */

        // Add the Submit button
        $this->add([
            'type'  => 'submit',
            'name' => 'upload',
            'attributes' => [
                'value' => 'Upload'
            ],
        ]);

        // Add the Update button
        $this->add([
            'type'  => 'submit',
            'name' => 'update',
            'attributes' => [
                'value' => 'Update'
            ],
        ]);
    }

    /**
     * This method creates input filter (used for form filtering/validation).
     */
    private function addInputFilter()
    {
        // Create main input filter
        $inputFilter = $this->getInputFilter();

        // Add input for "title" field
        $inputFilter->add([
            'name'     => 'title',
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name'    => 'StringLength',
                    'options' => [
                        'min' => 1,
                        'max' => 64
                    ],
                ],
            ],
        ]);

        // Add input for "description" field
        $inputFilter->add([
            'name'     => 'description',
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name'    => 'StringLength',
                    'options' => [
                        'min' => 1,
                        'max' => 1024
                    ],
                ],
            ],
        ]);

        // File Input
        $fileInput = new InputFilter\FileInput('data-file');
        $fileInput->setRequired(true);
        $fileInput->getFilterChain()->attachByName(
            'filerenameupload',
            [
                'target'    => './data/tmpuploads/upload',
                'randomize' => true,
            ]
        );
        $inputFilter->add($fileInput);

        $this->setInputFilter($inputFilter);
    }
}