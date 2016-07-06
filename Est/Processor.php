<?php

class Est_Processor {

	/**
	 * @var string
	 */
	protected $environment;

	/**
	 * @var string
	 */
	protected $settingsFilePath;

	/**
	 * @var array
	 */
	protected $handlerCollection;

    /**
     * @var array
     */
    protected $arguments = array();

    protected $groups = array();
    protected $excludedGroups = array();

	protected $debugMode = true;


    /**
     * Constructor
     *
     * @param $environment
     * @param $settingsFilePath
     * @param array $arguments
     * @throws InvalidArgumentException
     */
	public function __construct($environment, $settingsFilePath, array $arguments=array()) {
		if (empty($environment)) {
			throw new InvalidArgumentException('No environment parameter set.');
		}
		if (empty($settingsFilePath)) {
			throw new InvalidArgumentException('No settings file set.');
		}
		if (!file_exists($settingsFilePath)) {
			throw new InvalidArgumentException('Could not read settings file.');
		}

		$this->environment = $environment;
		$this->settingsFilePath = $settingsFilePath;
        $this->arguments = $arguments;

        if (isset($this->arguments['groups'])) {
            $this->groups = Est_Div::trimExplode(',', $this->arguments['groups']);
        }
        if (isset($this->arguments['exclude-groups'])) {
            $this->excludedGroups = Est_Div::trimExplode(',', $this->arguments['exclude-groups']);
        }

		$this->handlerCollection = new Est_HandlerCollection();
	}

	/**
	 * Apply settings to current environment
	 *
	 * @throws Exception
	 * @return bool
	 */
	public function apply() {
		$this->handlerCollection->buildFromSettingsCSVFile(
            $this->settingsFilePath,
            $this->environment,
            'DEFAULT',
            $this->groups,
            $this->excludedGroups
        );
		foreach ($this->handlerCollection as $handler) { /* @var $handler Est_Handler_Abstract */
			$res = $handler->apply();
			if (!$res) {
				throw new Exception('Exception thrown in Est_Processor::apply(). An error in handler '
					. $handler->getLabel());
			}
		}
		return true;
	}

	/**
	 * Apply settings to current environment
	 *
	 * @throws Exception
	 * @return bool
	 */
	public function dryRun() {
		$this->handlerCollection->buildFromSettingsCSVFile($this->settingsFilePath,$this->environment);
		foreach ($this->handlerCollection as $handler) { /* @var $handler Est_Handler_Abstract */
			$this->output($handler->getLabel());
		}
		return true;
	}

	/**
	 * Get value
	 *
	 * @param $handler
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @throws Exception
	 * @return Est_Handler_Abstract
	 */
	public function getHandler($handlerClassName, $param1, $param2, $param3) {
		$this->handlerCollection->buildFromSettingsCSVFile($this->settingsFilePath,$this->environment);
		$handler = $this->handlerCollection->getHandler($handlerClassName, $param1, $param2, $param3);
		if ($handler === false) {
			throw new Exception('No handler found with given specification: '."$handlerClassName, $param1, $param2, $param3");
		}
		return $handler;
	}

	/**
	 * Print result
	 */
	public function printResults() {

		$statistics = array();
		foreach ($this->handlerCollection as $handler) { /* @var $handler Est_Handler_Abstract */
			// Collecting some statistics
			$statistics[$handler->getStatus()][] = $handler;

			// skipping handlers that weren't executed
			if ($handler->getStatus() == Est_Handler_Interface::STATUS_NOTEXECUTED) {
				continue;
			}

			$this->output();
			$label = $handler->getLabel();
			$this->output($label);
			$this->output(str_repeat('-', strlen($label)));

			foreach ($handler->getMessages() as $message) { /* @var $message Est_Message */
				$this->output($message->getColoredText());
			}
		}

		$this->output();
		$this->output('Status summary:');
		$this->output(str_repeat('=', strlen(('Status summary:'))));

		foreach ($statistics as $status => $handlers) {
			$this->output(sprintf("%s: %s handler(s)", $status, count($handlers)));
		}

        if (count($this->groups) || count($this->excludedGroups)) {
            $this->output();
            $this->output('Groups:');
            $this->output(str_repeat('=', strlen(('Groups:'))));

            if (count($this->groups)) {
                $this->output('Groups: ' . implode(', ', $this->groups));
            }
            if (count($this->excludedGroups)) {
                $this->output('Excluded groups: ' . implode(', ', $this->excludedGroups));
            }
        }

	}

	protected function output($message='', $newLine=true) {
		echo $message;
		if ($newLine) {
			echo "\n";
		}
	}

}
