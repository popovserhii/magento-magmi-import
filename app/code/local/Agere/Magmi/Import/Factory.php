<?php
/**
 * Magmi importer factory
 *
 * @category Agere
 * @package Agere_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 09.12.13 15:42
 */

class Agere_Magmi_Import_Factory {

	static public $namespace = 'Agere_Magmi_Import';

	static protected $created = [];

	public static function create($name) {
		$className = self::$namespace . '_' . ucfirst($name);

        if (isset(self::$created[$className])) {
            return self::$created[$className];
        }

        if (!class_exists($className)) {
			Mage::throwException(sprintf('Cannot found import class %s', $className));
		}

		return self::$created[$className] = new $className();
	}

}