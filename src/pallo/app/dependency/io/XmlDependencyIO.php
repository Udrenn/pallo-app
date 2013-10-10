<?php

namespace pallo\app\dependency\io;

use pallo\library\dependency\exception\DependencyException;
use pallo\library\dependency\Dependency;
use pallo\library\dependency\DependencyCall;
use pallo\library\dependency\DependencyCallArgument;
use pallo\library\dependency\DependencyContainer;
use pallo\library\system\file\browser\FileBrowser;
use pallo\library\system\file\File;

use \DOMDocument;
use \DOMElement;

/**
 * Implementation to get a dependency container based on XML files
 */
class XmlDependencyIO implements DependencyIO {

    /**
     * The file name
     * @var string
     */
    const FILE = 'dependencies.xml';

    /**
     * Name of the dependency tag
     * @var string
     */
    const TAG_DEPENDENCY = 'dependency';

    /**
     * Name of the call tag
     * @var string
     */
    const TAG_CALL = 'call';

    /**
     * Name of the argument tag
     * @var string
     */
    const TAG_ARGUMENT = 'argument';

    /**
     * Name of the property tag
     * @var string
     */
    const TAG_PROPERTY = 'property';

    /**
     * Name of the interface attribute
     * @var string
     */
    const ATTRIBUTE_INTERFACE = 'interface';

    /**
     * Name of the class attribute
     * @var string
     */
    const ATTRIBUTE_CLASS = 'class';

    /**
     * Name of the extends attribute
     * @var string
     */
    const ATTRIBUTE_EXTENDS = 'extends';

    /**
     * Name of the id attribute
     * @var string
     */
    const ATTRIBUTE_ID = 'id';

    /**
     * Name of the method attribute
     * @var string
     */
    const ATTRIBUTE_METHOD = 'method';

    /**
     * Name of the name attribute
     * @var string
     */
    const ATTRIBUTE_NAME = 'name';

    /**
     * Name of the type attribute
     * @var string
     */
    const ATTRIBUTE_TYPE = 'type';

    /**
     * Name of the value attribute
     * @var string
     */
    const ATTRIBUTE_VALUE = 'value';

    /**
     * Instance of the file browser
     * @var pallo\library\system\file\browser\FileBrowser
     */
    protected $fileBrowser;

    /**
     * Relative path for the configuration file
     * @var string
     */
    protected $path;

    /**
     * Name of the environment
     * @var string
     */
    protected $environment;

    /**
     * Constructs a new XML dependency IO
     * @param pallo\core\environment\filebrowser\FileBrowser $fileBrowser
     * @param string $environment
     * @return null
     */
    public function __construct(FileBrowser $fileBrowser, $path = null) {
        $this->fileBrowser = $fileBrowser;

        $this->setPath($path);
    }

    /**
     * Sets the relative path for configuration files of this IO
     * @param string $path
     * @throws pallo\library\dependency\exception\DependencyException
     */
    public function setPath($path) {
        if (!is_string($path) || $path == '') {
            throw new DependencyException('Could not set the path: provided path is empty or invalid');
        }

        $this->path = $path;
    }

    /**
     * Gets the relative path for the configuration files of this IO
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Sets the name of the environment
     * @param string $environment Name of the environment
     * @return null
     * @throws Exception when the provided name is empty or not a string
     */
    public function setEnvironment($environment = null) {
        if ($environment !== null && (!is_string($environment) || !$environment)) {
            throw new DependencyException('Could not set the environment: provided environment is empty or not a string');
        }

        $this->environment = $environment;
    }

    /**
     * Gets the name of the environment
     * @return string|null
     */
    public function getEnvironment() {
        return $this->environment;
    }

    /**
     * Gets the dependency container
     * @param pallo\core\Zibo $pallo Instance of pallo
     * @return pallo\core\dependency\DependencyContainer
     */
    public function getDependencyContainer() {
        $container = new DependencyContainer();

        $path = null;
        if ($this->path) {
            $path = $this->path . File::DIRECTORY_SEPARATOR;
        }

        $files = array_reverse($this->fileBrowser->getFiles($path . self::FILE));
        foreach ($files as $file) {
            $this->readDependencies($container, $file);
        }

        if ($this->environment) {
            $path .= $this->environment . File::DIRECTORY_SEPARATOR;

            $files = array_reverse($this->fileBrowser->getFiles($path . self::FILE));
            foreach ($files as $file) {
                $this->readDependencies($container, $file);
            }
        }

        return $container;
    }

    /**
     * Reads the dependencies from the provided file and adds them to the
     * provided container
     * @param pallo\library\dependency\DependencyContainer $container
     * @param pallo\library\system\file\File $file
     * @return null
     */
    private function readDependencies(DependencyContainer $container, File $file) {
        $dom = new DOMDocument();
        $dom->load($file);

        $dependencyElements = $dom->getElementsByTagName(self::TAG_DEPENDENCY);
        foreach ($dependencyElements as $dependencyElement) {
            $interface = $dependencyElement->getAttribute(self::ATTRIBUTE_INTERFACE);
            $className = $dependencyElement->getAttribute(self::ATTRIBUTE_CLASS);
            $id = $dependencyElement->getAttribute(self::ATTRIBUTE_ID);
            if (!$id) {
                $id = null;
            }

            $extends = $dependencyElement->getAttribute(self::ATTRIBUTE_EXTENDS);
            if ($extends) {
                if (!$interface) {
                    $interface = $className;
                }

                $dependencies = $container->getDependencies($interface);
                if (isset($dependencies[$extends])) {
                    $dependency = clone $dependencies[$extends];
                    $dependency->setId($id);
                    if ($className) {
                        $dependency->setClassName($className);
                    }
                } else {
                    throw new DependencyException('No dependency set to extend interface ' . $interface . ' with id ' . $extends);
                }
            } else {
                $dependency = new Dependency($className, $id);
            }

            $this->readCalls($dependencyElement, $dependency);
            $this->readInterfaces($dependencyElement, $dependency, $interface, $className);

            $container->addDependency($dependency);
        }
    }

    /**
     * Reads the calls from the provided dependency element and adds them to
     * the dependency instance
     * @param DOMElement $dependencyElement
     * @param pallo\library\dependency\Dependency $dependency
     * @return null
     */
    private function readCalls(DOMElement $dependencyElement, Dependency $dependency) {
        $calls = array();

        $callElements = $dependencyElement->getElementsByTagName(self::TAG_CALL);
        foreach ($callElements as $callElement) {
            $methodName = $callElement->getAttribute(self::ATTRIBUTE_METHOD);

            $call = new DependencyCall($methodName);

            $argumentElements = $callElement->getElementsByTagName(self::TAG_ARGUMENT);
            foreach ($argumentElements as $argumentElement) {
                $name = $argumentElement->getAttribute(self::ATTRIBUTE_NAME);
                $type = $argumentElement->getAttribute(self::ATTRIBUTE_TYPE);
                $properties = array();

                $propertyElements = $argumentElement->getElementsByTagName(self::TAG_PROPERTY);
                foreach ($propertyElements as $propertyElement) {
                    $propertyName = $propertyElement->getAttribute(self::ATTRIBUTE_NAME);
                    $propertyValue = $propertyElement->getAttribute(self::ATTRIBUTE_VALUE);

                    $properties[$propertyName] = $propertyValue;
                }

                $call->addArgument(new DependencyCallArgument($name, $type, $properties));
            }

            $dependency->addCall($call);
        }
    }

    /**
     * Reads the interfaces from the provided dependency element and adds them
     * to the dependency instance
     * @param DOMElement $dependencyElement
     * @param pallo\library\dependency\Dependency $dependency
     * @param string $interface Class name of the interface
     * @param string $className Class name of the instance
     * @return null
     */
    private function readInterfaces(DOMElement $dependencyElement, Dependency $dependency, $interface, $className) {
        $interfaces = array();

        $interfaceElements = $dependencyElement->getElementsByTagName(self::ATTRIBUTE_INTERFACE);
        foreach ($interfaceElements as $interfaceElement) {
            $interfaceName = $interfaceElement->getAttribute(self::ATTRIBUTE_NAME);

            $interfaces[$interfaceName] = true;
        }

        if ($interface) {
            $interfaces[$interface] = true;
        }

        if (!$interfaces) {
            $interfaces[$className] = true;
        }

        $dependency->setInterfaces($interfaces);
    }

}