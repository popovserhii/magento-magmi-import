<?php
/**
 * Enter description here...
 *
 * @category Popov
 * @package Popov_<package>
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 11.09.13 10:20
 */

class Popov_Magmi_IndexController extends Mage_Core_Controller_Front_Action {

	public function indexAction() {

	}

	public function productImportAction() {
		/**
		 * @var Popov_Magmi_Import_Product $importer
		 */
		$importer = Popov_Magmi_Import_Factory::create('product');
		$importer->run();
	}

	public function imageImportAction() {
		/**
		 * @var Popov_Magmi_Import_Image $importer
		 */
		$importer = Popov_Magmi_Import_Factory::create('image');
		$importer->run();
	}

}