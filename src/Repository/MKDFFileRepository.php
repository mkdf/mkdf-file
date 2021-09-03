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
            'findFile'  => 'SELECT f.id, f.title, f.description, f.dataset_id, f.filename, '.
                'f.filename_original, f.file_type, f.file_size, f.date_created, f.date_modified, d.uuid '.
                'FROM file f JOIN dataset d ON d.id = f.dataset_id '.
                'WHERE f.id = '.$this->fp('id'),
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
        $repsonse = $this->sendQuery('GET','/file/' . $datasetId, array());
        //echo ($repsonse);
        $files = json_decode($repsonse,true);
        foreach ($files as $key=>$value) {
            $files[$key]['sizeStr'] = $this->_formatBytes($value['size']);
        }

        return $files;
    }

    public function createFileEntry($formData, $datasetID){
        $username = $this->_config['mkdf-stream']['user'];
        $password = $this->_config['mkdf-stream']['pass'];
        $server = $this->_config['mkdf-stream']['server-url'];
        $filename = basename($formData['data-file']['tmp_name']);

        $localFile = $formData['data-file']['tmp_name'];
        $filename = basename($formData['data-file']['name']);

        $path = '/file/' . $datasetID;
        $url = $server . $path;
        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => 'http://apif-beta.local:8080/file/793f9a38-d5c6-462b-987f-f78e257aa416',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'title' => $formData['title'],
                'description' => $formData['description'],
                'file' => curl_file_create($localFile, $formData['data-file']['type'], $filename)
            ),
            CURLOPT_USERPWD => $username . ":" . $password,
        ));

        $response = curl_exec($ch);

        curl_close($ch);
        echo $response;


        return true;
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
            $file_pointer = $f['location'] . $f['uuid'] . "/" . $f['filename'];

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

    /**
     * @param $method
     * @param $path
     * @param $parameters
     * @return bool|string
     * @throws \Exception
     */
    private function sendQuery($method, $path, $parameters) {
        $username = $this->_config['mkdf-stream']['user'];
        $password = $this->_config['mkdf-stream']['pass'];
        $server = $this->_config['mkdf-stream']['server-url'];
        //$parameters = array_merge(array('user' => $username,'pwd'=>$password), $parameters);
        $url = $server . $path;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);

        switch ($method){
            case "PUT":
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
                break;
            case "POST":
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
                break;
            case "GET":
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                $url = $url . '?' . http_build_query($parameters);
                curl_setopt($ch, CURLOPT_URL, $url);
                break;
            default:
                //unexpected method
                throw new \Exception("Unexpected method");
        }
        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec ($ch);

        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case 201:  # OK Created
                    //self::log('Message from API Factory server: ',$server_output);
                    //echo "201";
                    break;
                case 200:  # OK Updated
                    //self::log('Message from API Factory server: ',$server_output);
                    //echo "200";
                    break;
                default:
                    throw new \Exception('Unexpected HTTP code: '. $http_code ."\n\nURL: ". $url . "\n\n" . $server_output);
                //echo "Something else: ".$http_code;
            }
        }else{
            //self::logErr('Curl Error: ', $curl_errno($ch));
            throw new \Exception('cURL error: '. curl_error($ch) ."\n\nURL: ". $url . "\n\n" . $server_output);
        }
        curl_close ($ch);
        return $server_output;
    }

    public function init(){
      try {
          $statement = $this->_adapter->createStatement($this->getQuery('isReady'));
          $result    = $statement->execute();
          return false;
      } catch (\Exception $e) {
          // XXX Maybe raise a warning here?
      }
      $sql = file_get_contents(dirname(__FILE__) . '/../../sql/setup.sql');
      $this->_adapter->getDriver()->getConnection()->execute($sql);
      return true;
    }
}
