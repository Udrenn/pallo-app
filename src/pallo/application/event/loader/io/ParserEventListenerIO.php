<?php

namespace pallo\application\event\loader\io;

use pallo\library\config\io\AbstractIO;
use pallo\library\config\parser\Parser;
use pallo\library\config\ConfigHelper;
use pallo\library\event\exception\EventException;
use pallo\library\event\loader\io\EventListenerIO;
use pallo\library\event\EventListener;
use pallo\library\system\file\browser\FileBrowser;
use pallo\library\system\file\File;

use \Exception;

/**
 * Interface to read event definitions from the data source
 */
class ParserEventListenerIO extends AbstractIO implements EventListenerIO {

	/**
	 * Instance of the configuration
	 * @var pallo\library\config\parser\Parser
	 */
	protected $parser;

	/**
	 * Instance of the config helper
	 * @var pallo\library\config\ConfigHelper
	 */
	protected $helper;

	/**
	 * Constructs a new parser event listener IO
	 * @param pallo\library\system\file\browser\FileBrowser $fileBrowser
	 * @param pallo\library\config\parser\Parser $parser
	 * @param string $file
	 * @param string $path
	 * @return null
	 */
	public function __construct(FileBrowser $fileBrowser, Parser $parser, ConfigHelper $configHelper, $file, $path = null) {
		parent::__construct($fileBrowser, $file, $path);

		$this->parser = $parser;
		$this->helper = $configHelper;
	}

	/**
	 * Reads all the event listeners from the data source
	 * @return array Hierarchic array with the name of the event as key and an
	 * array with EventListener instances as value
	 */
	public function readEventListeners() {
		$path = null;
		if ($this->path) {
			$path = $this->path . File::DIRECTORY_SEPARATOR;
		}

		$files = array_reverse($this->fileBrowser->getFiles($path . $this->file));

		if ($this->environment) {
			$path .= $this->environment . File::DIRECTORY_SEPARATOR;

			$files += array_reverse($this->fileBrowser->getFiles($path . $this->file));
		}

		$eventListeners = array();
		foreach ($files as $file) {

			$fileEventListeners = $this->readEventListenersFromFile($file);
			foreach ($fileEventListeners as $eventListener) {
				$event = $eventListener->getEvent();

				if (!isset($eventListeners[$event])) {
					$eventListeners[$event] = array($eventListener);
				} else {
					$eventListeners[$event][] = $eventListener;
				}
			}
		}

		return $eventListeners;
	}

	/**
	 * Reads the events file
	 * @param pallo\library\system\file\File $file File to read
	 * @return array Array with EventListener objects
	 */
	public function readEventListenersFromFile(File $file) {
		try {
			$content = $file->read();
			$content = $this->parser->parseToPhp($content);
		} catch (Exception $exception) {
			throw new EventException('Could not read events from ' . $file, 0, $exception);
		}

		$eventListeners = array();

		$content = $this->helper->flattenConfig($content);
		foreach ($content as $key => $value) {
			$tokens = explode('.', $key);

			$property = array_pop($tokens);
			$index = array_pop($tokens);
			$event = implode('.', $tokens);
			$index .= $event;

			if (isset($eventListeners[$index])) {
				$eventListeners[$index][$property] = $this->processParameter($value);
			} else {
				$eventListeners[$index] = array(
					'event' => $event,
					$property => $this->processParameter($value),
				);
			}
		}

		foreach ($eventListeners as $index => $eventListener) {
			$event = $eventListener['event'];

			if (isset($eventListener['interface']) && isset($eventListener['method'])) {
				if (isset($eventListener['id'])) {
					$eventListener['interface'] .= '#' . $eventListener['id'];
				}

				$callback = array($eventListener['interface'], $eventListener['method']);
			} elseif (isset($eventListener['function'])) {
				$callback = $eventListener['function'];
			} else {
				throw new EventException('Could not parse listener for event ' . $event . ': no callback defined, try setting interface and method or function');
			}

			if (isset($eventListener['weight'])) {
				$weight = $eventListener['weight'];
			} else {
				$weight = null;
			}

			$eventListeners[$index] = new EventListener($event, $callback, $weight);
		}

		return $eventListeners;
	}

}