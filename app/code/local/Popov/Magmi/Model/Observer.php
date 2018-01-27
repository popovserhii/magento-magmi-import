<?php
/**
 * Magmi runner by cron
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 11.11.13 15:19
 */
class Popov_Magmi_Model_Observer extends Varien_Event_Observer {

	public function productImport() {
		/**
		 * @var Popov_Magmi_Import_Product $importer
		 */
		$importer = Popov_Magmi_Import_Factory::create('product');
		$importer->run();
	}

	public function imageImport() {
		/**
		 * @var Popov_Magmi_Import_Image $importer
		 */				
		$importer = Popov_Magmi_Import_Factory::create('image');
		$importer->run();
	}

}