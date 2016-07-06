<?php

/**
 * Parameters
 *
 * - scope
 * - scopeId
 * - path
 */
class Est_Handler_Magento_CoreConfigData extends Est_Handler_AbstractDatabase {

    protected $tablePrefix = '';

	/**
	 * Protected method that actually applies the settings. This method is implemented in the inheriting classes and
	 * called from ->apply
	 *
	 * @throws Exception
	 * @return bool
	 */
	protected function _apply() {

		$scope = $this->param1;
		$scopeId = $this->param2;
		$path = $this->param3;

		if (empty($scope)) {
			throw new Exception("No scope found (param1)");
		}
		if (is_null($scopeId)) {
			throw new Exception("No scopeId found (param2)");
		}
		if (empty($path)) {
			throw new Exception("No path found (param2)");
		}

        if (!in_array($scope, array('default', 'stores', 'websites', '%'))) {
            throw new Exception("Scope must be 'default', 'stores', 'websites', or '%'");
        }

        if ($scope == 'stores' && !is_numeric($scopeId)) {
            // do a store code lookup
            $code = $scopeId;
            $scopeId = $this->getStoreIdFromCode($code);
            $this->addMessage(new Est_Message("Found store id '$scopeId' for code '$code'", Est_Message::INFO));
        }

        if ($scope == 'websites' && !is_numeric($scopeId)) {
            // do a website code lookup
            $code = $scopeId;
            $scopeId = $this->getWebsiteIdFromCode($code);
            $this->addMessage(new Est_Message("Found website id '$scopeId' for code '$code'", Est_Message::INFO));
        }

		$sqlParameters = array(
			':scope' => $scope,
			':scopeId' => $scopeId,
			':path' => $path
		 );

		$conn = $this->getDbConnection();

		if (strtolower(trim($this->value)) == '--delete--') {
			$query = $conn->prepare('DELETE FROM `'.$this->tablePrefix.'core_config_data` WHERE `scope` LIKE :scope AND `scope_id` LIKE :scopeId AND `path` LIKE :path');
			$res = $query->execute($sqlParameters);

			if ($res === false) {
				throw new Exception('Error while deleting rows');
			}

			$rowCount = $query->rowCount();

			if ($rowCount > 0) {
				$this->addMessage(new Est_Message(sprintf('Deleted "%s" row(s)', $rowCount)));
			} else {
				$this->addMessage(new Est_Message('No rows deleted.', Est_Message::SKIPPED));
			}

		} else {

			$query = $conn->prepare('SELECT `value` FROM `'.$this->tablePrefix.'core_config_data` WHERE `scope` LIKE :scope AND `scope_id` LIKE :scopeId AND `path` LIKE :path');
			$query->execute($sqlParameters);

			$query->setFetchMode(PDO::FETCH_ASSOC);
			$currentValue = $query->fetch();

			$containsPlaceholder = $this->containsPlaceholder($sqlParameters);

			$sqlParameters[':value'] = $this->value;

			if ($currentValue === false && $containsPlaceholder) {

				$this->addMessage(new Est_Message('Trying to update using placeholders where no rows existed', Est_Message::SKIPPED));

			} elseif ($currentValue === false) {
				// value doesn't exist: insert instead of update

				$res = $conn->prepare('INSERT INTO `'.$this->tablePrefix.'core_config_data` (`scope`, `scope_id`, `path`, value) VALUES (:scope, :scopeId, :path, :value)')
					->execute($sqlParameters);

				if ($res === false) {
					// TODO: include speaking error message
                    // var_dump( $conn->errorInfo());
					throw new Exception('Error while updating value');
				}

				$this->addMessage(new Est_Message(sprintf('Inserted new value "%s"', $this->value)));

			} elseif($currentValue['value'] == $this->value) {
				$this->addMessage(new Est_Message(sprintf('Value "%s" is already in place. Skipping.', $currentValue['value']), Est_Message::SKIPPED));
			} else {

				$res = $conn->prepare('UPDATE `'.$this->tablePrefix.'core_config_data` SET `value` = :value WHERE `scope` LIKE :scope AND `scope_id` LIKE :scopeId AND `path` LIKE :path')
					->execute($sqlParameters);

				if ($res === false) {
					// TODO: include speaking error message
					throw new Exception('Error while updating value');
				}

				$this->addMessage(new Est_Message(sprintf('Updated value from "%s" to "%s"', $currentValue['value'], $this->value)));
			}
		}

		return true;
	}

    /**
     * Look up store id for a given store code
     *
     * @param $code
     * @return mixed
     * @throws Exception
     */
    protected function getStoreIdFromCode($code) {
        $conn = $this->getDbConnection();
        $query = $conn->prepare('SELECT `store_id` FROM `'.$this->tablePrefix.'core_store` WHERE `code` LIKE :code');
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
    protected function getWebsiteIdFromCode($code) {
        $conn = $this->getDbConnection();
        $query = $conn->prepare('SELECT `website_id` FROM `'.$this->tablePrefix.'core_website` WHERE `code` LIKE :code');
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
	 * Read database connection parameters from local.xml file
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getDatabaseConnectionParameters() {
		$localXmlFile = 'app/etc/local.xml';
		$debug = true;

		if (!is_file($localXmlFile)) {
			throw new Exception(sprintf('File "%s" not found', $localXmlFile));
		}

		$config = simplexml_load_file($localXmlFile);
		if ($config === false) {
			throw new Exception(sprintf('Could not load xml file "%s"', $localXmlFile));
		}

        $this->tablePrefix = (string)$config->global->resources->db->table_prefix;

		if($debug) {
			echo "--start debug info--" . PHP_EOL;
			echo "executing script: " . __FILE__ . PHP_EOL;
			$stack = debug_backtrace();
			$firstFrame = $stack[count($stack) - 1];
			$initialFile = $firstFrame['file'];
			echo "initial script execution file: " . $initialFile . PHP_EOL;
			echo "database connection username: "
				. (string)$config->global->resources->default_setup->connection->username;
			echo "--end debug info--";
		}

		return array(
			'host' => (string)$config->global->resources->default_setup->connection->host,
			'database' => (string)$config->global->resources->default_setup->connection->dbname,
			'username' => (string)$config->global->resources->default_setup->connection->username,
			'password' => (string)$config->global->resources->default_setup->connection->password
		);
	}

	/**
	 * Check if at least one of the paramters contains a wildcard
	 *
	 * @param array $parameters
	 * @return bool
	 */
	protected function containsPlaceholder(array $parameters) {
		foreach ($parameters as $value) {
			if (strpos($value, '%') !== false) {
				return true;
			}
		}
		return false;
	}


}