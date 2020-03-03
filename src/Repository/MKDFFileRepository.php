<?php
namespace MKDF\File\Repository;


use Zend\Db\Adapter\Adapter;

class MKDFFileRepository implements MKDFFileRepositoryInterface
{
    private $_config;
    private $_adapter;
    private $_queries;

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
    }

    private function fp($param) {
        return $this->_adapter->driver->formatParameterName($param);
    }
    private function qi($param) {
        return $this->_adapter->platform->quoteIdentifier($param);
    }
    private function buildQueries()
    {
        $this->_queries = [];
    }
}