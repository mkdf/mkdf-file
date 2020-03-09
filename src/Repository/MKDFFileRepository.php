<?php
namespace MKDF\File\Repository;


use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;

class MKDFFileRepository implements MKDFFileRepositoryInterface
{
    private $_config;
    private $_adapter;
    private $_queries;
    private $_uploadDestination;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_adapter = new Adapter([
            'driver'   => 'Pdo_Mysql',
            'database' => $this->_config['db']['dbname'],
            'username' => $this->_config['db']['user'],
            'password' => $this->_config['db']['password'],
            'host'     => $this->_config['db']['host'],
            'port'     => $this->_config['db']['port']
        ]);
        $this->buildQueries();
        $this->_uploadDestination = $this->_config['mkdf-file']['destination'];
    }

    private function fp($param) {
        return $this->_adapter->driver->formatParameterName($param);
    }
    private function qi($param) {
        return $this->_adapter->platform->quoteIdentifier($param);
    }
    private function buildQueries()
    {
        $this->_queries = [
            'isReady'           => 'SELECT id FROM file LIMIT 1',
            'deleteFile'        => 'DELETE FROM file WHERE id = '.$this->fp('id'),
            'findDatasetFiles'  => 'SELECT id, title, description, dataset_id, filename, filename_original, file_type, file_size, date_created, date_modified '.
                'FROM file '.
                'WHERE dataset_id = '.$this->fp('dataset_id'),
            'findFile'  => 'SELECT id, title, description, dataset_id, filename, filename_original, file_type, file_size, date_created, date_modified '.
                'FROM file '.
                'WHERE id = '.$this->fp('id'),
            'insertFile'     => 'INSERT INTO file ('.
                'title, description, dataset_id, filename, filename_original, file_type, file_size, date_created, date_modified'.
                ') VALUES ('.
                $this->fp('title').', '.
                $this->fp('description').', '.
                $this->fp('dataset_id').', '.
                $this->fp('filename').', '.
                $this->fp('filename_original').', '.
                $this->fp('file_type').', '.
                $this->fp('file_size').', '.
                'CURRENT_TIMESTAMP, '.
                'CURRENT_TIMESTAMP'.
            ')',
        ];
    }

    private function getQuery($query){
        return $this->_queries[$query];
    }

    private function _formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = array('b', 'kb', 'mb', 'gb', 'tb');

        return round(pow(1024, $base - floor($base)), $precision) .''. $suffixes[floor($base)];
    }

    public function findDatasetFiles ($datasetId) {
        $files = [];
        $parameters = [
            'dataset_id' => $datasetId
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('findDatasetFiles'));
        $result = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                $row['file_size_str'] = $this->_formatBytes((int)$row['file_size'],1);
                array_push($files, $row);
            }
        }
        return $files;
    }

    public function createFileEntry($formData, $dataset){
        //print_r($formData);
        //print_r($dataset);
        $destination = $this->_uploadDestination . $dataset->uuid . "/";
        //keep random upload fiename. Original filename will be stored in DB for use when
        //file is downloaded
        $filename = basename($formData['data-file']['tmp_name']);

        if (!file_exists($destination)) {
            mkdir($destination, 0777, true);
        }
        rename ($formData['data-file']['tmp_name'],$destination.$filename);

        //update DB
        $parameters = [
            'title'             => $formData['title'],
            'description'       => $formData['description'],
            'dataset_id'        => $dataset->id,
            'filename'          => $filename,
            'filename_original' => $formData['data-file']['name'],
            'file_type'         => $formData['data-file']['type'],
            'file_size'         => $formData['data-file']['size']
        ];
        $statement = $this->_adapter->createStatement($this->getQuery('insertFile'));
        $statement->execute($parameters);
        $id = $this->_adapter->getDriver()->getLastGeneratedValue();
        return $id;
    }

    public function findFile($id){
        $parameters = [
            'id' => $id
        ];
        $f = null;
        $statement = $this->_adapter->createStatement($this->getQuery('findFile'));
        $result = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $f = $result->current();
            $f['location'] = $this->_uploadDestination;
        }
        return $f;
    }

    public function deleteFile($id) {
        //First get file entry details
        $parameters = [
            'id' => $id
        ];
        $f = null;
        $statement = $this->_adapter->createStatement($this->getQuery('findFile'));
        $result = $statement->execute($parameters);
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $f = $result->current();
            $f['location'] = $this->_uploadDestination;
        }

        if ($f != null){
            //database call to remove entry
            $statement = $this->_adapter->createStatement($this->getQuery('deleteFile'));
            $result = $statement->execute($parameters);


            //file operation to remove file
            $file_pointer = $f['location'] . $f['filename'];

            if (!unlink($file_pointer)) {
                //FIXME - DELETION NOT WORKING
                return false;
            }
            else {
                return true;
            }

        }
        else {
            return false;
        }

    }
}