<?php
/**
 * Enter description here...
 *
 * @category Agere
 * @package Agere_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 11.09.13 10:20
 */

class Agere_Magmi_IndexController extends Mage_Core_Controller_Front_Action {

	public function indexAction() {

	}

	public function productImportAction() {
		/**
		 * @var Agere_Magmi_Import_Product $importer
		 */
		$importer = Agere_Magmi_Import_Factory::create('product');
		$importer->run();
	}

	public function imageImportAction() {
		/**
		 * @var Agere_Magmi_Import_Image $importer
		 */
		$importer = Agere_Magmi_Import_Factory::create('image');
		$importer->run();
	}

}