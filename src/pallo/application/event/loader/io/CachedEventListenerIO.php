<?php

namespace pallo\application\event\loader\io;

use pallo\library\event\loader\io\EventListenerIO;
use pallo\library\system\file\File;

/**
 * Cache decorator for another EventListenerIO. This IO will get the events
 * from the wrapped IO and generate a PHP script to include. When the generated
 * PHP script exists, this will be used to define the events. It should be
 * faster since only 1 include is done which contains plain PHP variable
 * initialization
 */
class CachedEventListenerIO implements EventListenerIO {

    /**
     * EventIO which is cached by this instance
     * @var EventIO
     */
    private $io;

    /**
     * File to write the cache to
     * @var pallo\library\system\file\File
     */
    private $file;

    /**
     * Constructs a new cached EventIO
     * @param pallo\library\event\loader\io\EventListenerIO $io EventIO which
     * needs a cache
     * @param pallo\library\system\file\File $file File for the cache
     * @return null
     */
    public function __construct(EventListenerIO $io, File $file) {
        $this->io = $io;
        $this->setFile($file);
    }

    /**
     * Sets the file for the generated code
     * @param pallo\library\system\file\File $file File to generate the code in
     * @return null
     */
    public function setFile(File $file) {
        $this->file = $file;
    }

    /**
     * Gets the file for the generated code
     * @return pallo\library\system\file\File File to generate the code in
     * @return null
     */
    public function getFile() {
        return $this->file;
    }

    /**
     * Reads all the event listeners from the data source
     * @return array Hierarchic array with the name of the event as key and an
     * array with Event instances as value
     */
    public function readEventListeners() {
        if ($this->file->exists()) {
            // the generated script exists, include it
            include $this->file->getPath();

            if (isset($eventListeners)) {
                // the script defined events, return it
                return $eventListeners;
            }
        }
        // we have no events, use the wrapped IO to get one
        $eventListeners = $this->io->readEventListeners();

        // generate the PHP code for the obtained container
        $php = $this->generatePhp($eventListeners);

        // make sure the parent directory of the script exists
        $parent = $this->file->getParent();
        $parent->create();

        // write the PHP code to file
        $this->file->write($php);

        // return the events
        return $eventListeners;
    }

    /**
     * Generates a PHP source file for the provided events
     * @param array $eventListeners
     * @return string
     */
    protected function generatePhp(array $eventListeners) {
        $output = "<?php\n\n";
        $output .= "/*\n";
        $output .= " * This file is generated by pallo\application\event\loader\io\CachedEventListenerIO.\n";
        $output .= " */\n";
        $output .= "\n";
        $output .= "use pallo\\library\\event\\EventListener;\n";
        $output .= "\n";
        $output .= '$eventListeners' . " = array(\n";

        foreach ($eventListeners as $event => $eventListeners) {
            $output .= "\t" . var_export($event, true) . " => array(\n";
            foreach ($eventListeners as $eventListener) {
                $output .= "\t\tnew EventListener(" . var_export($eventListener->getEvent(), true) . ", " . var_export($eventListener->getCallback(), true) . ', ' . var_export($eventListener->getWeight(), true) . "),\n";
            }
            $output .= "\t),\n";
        }
        $output .= ");\n";

        return $output;
    }

}