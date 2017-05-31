<?php
/**
 * Magmi importer factory
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 09.12.13 15:42
 */

class Popov_Magmi_Import_Factory {

	static public $namespace = 'Popov_Magmi_Import';

	static protected $created = [];

	public static function create($name) {
		$className = self::$namespace . '_' . ucfirst($name);

        if (isset(self::$created[$className])) {
            return self::$created[$className];
        }

        if (!class_exists($className)) {
			Mage::throwException(sprintf('Cannot found import class %s', $className));
		}

        $importNode = $name . '_import';
        $config = (array) Mage::getConfig()->getNode('popov_magmi/' . $importNode)->asArray();

        /** @var Popov_Magmi_Import_Abstract $import */
		$import = self::$created[$className] = new $className();
		$import->setConfig($config);
		#$import->setRunMode(Popov_Magmi_Import_Abstract::RUN_MODE_DEBUG);

		return $import;
	}

}