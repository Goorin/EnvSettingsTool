<?php

/**
 * Abstract magento database handler class
 *
 * @author Dmytro Zavalkin <dmytro.zavalkin@aoe.com>
 */
abstract class Est_Handler_Magento_AbstractDatabase extends Est_Handler_AbstractDatabase
{
    /**@+
     * Actions to apply on row
     *
     * @var string
     */
    const ACTION_NO_ACTION = 0;
    const ACTION_INSERT = 1;
    const ACTION_UPDATE = 2;
    const ACTION_DELETE = 3;
    /**@-*/

    /**
     * Table prefix
     *
     * @var string
     */
    protected $_tablePrefix = '';

    /**
     * Read database connection parameters from local.xml file
     *
     * @return array
     * @throws Exception
     */
    protected function _getDatabaseConnectionParameters()
    {
        $localXmlFile = 'app/etc/local.xml';
        $configPhpFile = 'app/etc/config.php';

        if (is_file($localXmlFile)) {
            $config = simplexml_load_file($localXmlFile);
            if ($config === false) {
                throw new Exception(sprintf('Could not load xml file "%s"', $localXmlFile));
            }

            $this->_tablePrefix = (string)$config->global->resources->db->table_prefix;

            return array(
                'host' => (string)$config->global->resources->default_setup->connection->host,
                'database' => (string)$config->global->resources->default_setup->connection->dbname,
                'username' => (string)$config->global->resources->default_setup->connection->username,
                'password' => (string)$config->global->resources->default_setup->connection->password
            );
        } elseif (is_file($configPhpFile)) {
            $config = include($configPhpFile);
            if (!is_array($config)) {
                throw new Exception(sprintf('Could not load php file "%s"', $configPhpFile));
            }
            return array(
                'host' => $config['db']['connection']['default']['host'],
                'database' => $config['db']['connection']['default']['dbname'],
                'username' => $config['db']['connection']['default']['username'],
                'password' => $config['db']['connection']['default']['password']
            );
        }

        throw new Exception('No valid configuration found.');
    }

    /**
     * Check if at least one of the paramters contains a wildcard
     *
     * @param array $parameters
     * @return bool
     */
    protected function _containsPlaceholder(array $parameters)
    {
        foreach ($parameters as $value) {
            if (strpos($value, '%') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Look up store id for a given store code
     *
     * @param $code
     * @return mixed
     * @throws Exception
     */
    protected function _getStoreIdFromCode($code)
    {
        $query = $this->getDbConnection()
                      ->prepare('SELECT `store_id` FROM `' . $this->_tablePrefix
                                . 'core_store` WHERE `code` LIKE :code');
        $query->execute(array(':code' => $code));
        $query->setFetchMode(PDO::FETCH_ASSOC);
        $results = $query->fetch();
        if (count($results) == 0) {
            throw new Exception("Could not find a store for code '$code'");
        } elseif (count($results) > 1) {
            throw new Exception("Found more than once store for code '$code'");
        }
        $result = end($results);

        return $result['store_id'];
    }

    /**
     * Look up website id for a given website code
     *
     * @param $code
     * @return mixed
     * @throws Exception
     */
    protected function _getWebsiteIdFromCode($code)
    {
        $query = $this->getDbConnection()
                      ->prepare('SELECT `website_id` FROM `' . $this->_tablePrefix
                                . 'core_website` WHERE `code` LIKE :code');
        $query->execute(array(':code' => $code));
        $query->setFetchMode(PDO::FETCH_ASSOC);
        $results = $query->fetch();
        if (count($results) == 0) {
            throw new Exception("Could not find a website for code '$code'");
        } elseif (count($results) > 1) {
            throw new Exception("Found more than once website for code '$code'");
        }
        $result = end($results);

        return $result['website_id'];
    }

    /**
     * Fetch entity type id by a given entity type code
     *
     * @param string $code Entity type code
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityTypeFromCode($code)
    {
        $query = $this->getDbConnection()
            ->prepare('SELECT `entity_type_id` FROM `' . $this->_tablePrefix . 'eav_entity_type` WHERE `entity_type_code` = :code');
        $query->execute(array('code' => $code));
        $query->setFetchMode(PDO::FETCH_ASSOC);

        $result = $query->fetch();
        if (!$result || 0 == count($result)) {
            throw new Exception("Could not find an entity type with code '$code'");
        } else if (1 < count($result)) {
            throw new Exception("Found more than one entity type with code '$code'");
        }

        $result = end($result);

        return $result;
    }

    /**
     * @param string $table
     * @throws Exception
     */
    protected function _checkIfTableExists($table)
    {
        $result = $this->getDbConnection()
                       ->query("SHOW TABLES LIKE \"{$this->_tablePrefix}{$table}\"");
        if ($result->rowCount() == 0) {
            throw new Exception("Table \"{$this->_tablePrefix}{$table}\" doesn't exist");
        }
    }

    /**
     * Output constructed csv
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws Exception
     * @return string
     */
    protected function _outputQuery($query, array $sqlParameters)
    {
        $statement = $this->getDbConnection()->prepare($query);
        $statement->execute($sqlParameters);
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        $rows = $statement->fetchAll();

        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            array_unshift($row, get_class($this));
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $output = stream_get_contents($buffer);
        fclose($buffer);

        return $output;
    }

    /**
     * Get first row query
     *
     * @param string $query
     * @param array $sqlParameters
     * @return mixed
     */
    protected function _getFirstRow($query, array $sqlParameters)
    {
        $statement = $this->getDbConnection()->prepare($query);
        $statement->execute($sqlParameters);
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        return $statement->fetch();
    }

    /**
     * Process delete query
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws Exception
     */
    protected function _processDelete($query, array $sqlParameters)
    {
        $pdoStatement = $this->getDbConnection()->prepare($query);
        $result       = $pdoStatement->execute($sqlParameters);

        if ($result === false) {
            throw new Exception('Error while deleting rows');
        }

        $rowCount = $pdoStatement->rowCount();
        if ($rowCount > 0) {
            $this->addMessage(new Est_Message(sprintf('Deleted "%s" row(s)', $rowCount)));
        } else {
            $this->addMessage(new Est_Message('No rows deleted.', Est_Message::SKIPPED));
        }
    }

    /**
     * Process insert query
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws Exception
     */
    protected function _processInsert($query, array $sqlParameters)
    {
        $result = $this->getDbConnection()
            ->prepare($query)
            ->execute($sqlParameters);

        if ($result === false) {
            // TODO: include speaking error message
            throw new Exception('Error while updating value');
        }

        $this->addMessage(new Est_Message(sprintf('Inserted new value "%s"', $this->value)));
    }

    /**
     * Process update query
     *
     * @param string $query
     * @param array $sqlParameters
     * @param string $oldValue
     * @throws Exception
     */
    protected function _processUpdate($query, array $sqlParameters, $oldValue)
    {
        $result = $this->getDbConnection()
            ->prepare($query)
            ->execute($sqlParameters);

        if ($result === false) {
            // TODO: include speaking error message
            throw new Exception('Error while updating value');
        }

        $this->addMessage(new Est_Message(sprintf('Updated value from "%s" to "%s"',
            $oldValue,
            $this->value))
        );
    }
}
