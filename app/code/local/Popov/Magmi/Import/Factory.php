<?php
/**
 * Magmi importer factory
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
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
        $importConfig = (array) Mage::getConfig()->getNode('popov_magmi/' . $importNode)->asArray();
        $generalConfig = (array) Mage::getConfig()->getNode('popov_magmi/general_import')->asArray();

        /** @var Popov_Magmi_Import_Abstract $import */
		$import = self::$created[$className] = new $className();
		$import->setConfig($importConfig);
		$import->setGeneralConfig($generalConfig);
		//$import->setRunMode(Popov_Magmi_Import_Abstract::RUN_MODE_DEBUG);

		return $import;
	}

}