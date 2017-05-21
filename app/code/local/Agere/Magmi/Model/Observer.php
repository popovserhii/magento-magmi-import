<?php
/**
 * Magmi runner by cron
 *
 * @category Agere
 * @package Agere_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 11.11.13 15:19
 */
class Agere_Magmi_Model_Observer extends Varien_Event_Observer {

	public function productImport() {
		/**
		 * @var Agere_Magmi_Import_Product $importer
		 */
		$importer = Agere_Magmi_Import_Factory::create('product');
		$importer->run();
	}

	public function imageImport() {
		/**
		 * @var Agere_Magmi_Import_Image $importer
		 */				
		$importer = Agere_Magmi_Import_Factory::create('image');
		$importer->run();
	}

}