<?php
namespace MKDF\File\Repository;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;

class MKDFFileRepository implements MKDFFileRepositoryInterface
{
    private $_config;
    private $_adapter;
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
        $this->_uploadDestination = $this->_config['mkdf-file']['destination'];
    }


    private function _formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = array('b', 'kb', 'mb', 'gb', 'tb');

        return round(pow(1024, $base - floor($base)), $precision) .''. $suffixes[floor($base)];
    }

    public function getFileURI($datasetID, $filename) {
        $server = $this->_config['mkdf-stream']['server-url'];
        $path = '/file/' . $datasetID . '/' . rawurlencode($filename);
        $url = $server . $path;
        return $url;
    }

    public function findDatasetFiles ($datasetId) {
        $files = [];
        // FIXME - use correct user access key here for getting file list
        $repsonse = $this->sendQuery('GET','/file/' . $datasetId, array());
        //echo ($repsonse);
        $files = json_decode($repsonse,true);
        foreach ($files as $key=>$value) {
            $files[$key]['sizeStr'] = $this->_formatBytes($value['size']);
            $files[$key]['uri'] = $this->getFileURI($datasetId, $value['filenameOriginal']);
        }
        return $files;
    }

    public function createFileEntry($formData, $datasetID, $keyPassed){
        //$username = $this->_config['mkdf-stream']['user'];
        //$password = $this->_config['mkdf-stream']['pass'];
        $username = $keyPassed;
        $password = $keyPassed;
        $server = $this->_config['mkdf-stream']['server-url'];
        $localFile = $formData['data-file']['tmp_name'];
        $filename = basename($formData['data-file']['name']);

        $path = '/file/' . $datasetID;
        $url = $server . $path;
        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
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

    public function getFile($datasetID,$filename,$key) {
        //$username = $this->_config['mkdf-stream']['user'];
        //$password = $this->_config['mkdf-stream']['pass'];
        $username = $key;
        $password = $key;
        $server = $this->_config['mkdf-stream']['server-url'];
        //$parameters = array_merge(array('user' => $username,'pwd'=>$password), $parameters);
        $path = '/file/' . $datasetID . '/' . rawurlencode($filename);
        $url = $server . $path;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERPWD => $username . ":" . $password,
        ));

        $response = curl_exec($curl);
        $curlInfo = null;
        if (!curl_errno($curl)) {
            $curlInfo = curl_getinfo($curl);
        }
        curl_close($curl);
        $data = [
            'response' => $response,
            'curlInfo' => $curlInfo
        ];
        return $data;
    }

    public function deleteFile($datasetID,$filename,$key) {
        $username = $key;
        $password = $key;
        $server = $this->_config['mkdf-stream']['server-url'];
        //$parameters = array_merge(array('user' => $username,'pwd'=>$password), $parameters);
        $path = '/file/' . $datasetID . '/' . rawurlencode($filename);
        $url = $server . $path;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_USERPWD => $username . ":" . $password,
        ));

        $response = curl_exec($curl);
        //echo ($response);
        $curlInfo = null;
        if (!curl_errno($curl)) {
            $curlInfo = curl_getinfo($curl);
        }
        curl_close($curl);
        $data = [
            'response' => $response,
            'curlInfo' => $curlInfo
        ];
        return $data;
    }

    /**
     * @param $method
     * @param $path
     * @param $parameters
     * @return bool|string
     * @throws \Exception
     */
    // FIXME - This is currently using admin credentials from config. For some operations, user keys should be supplied and used.
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
      return true;
    }
}
