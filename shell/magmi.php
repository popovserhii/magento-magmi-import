<?php
/**
 * Image Photo Grabber
 *
 * @category Popov
 * @package Popov_Shell
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 22.12.15 12:20
 */
$rootPath = realpath($file = './');
require_once $rootPath . '/shell/abstract.php';

class Mage_Shell_Magmi extends Mage_Shell_Abstract {

	/** @var  Import/Export log file */
	protected $logFile;

	public function run() {
		/** Magento Import/Export Profiles */
		if ($importType = $this->getArg('import')){
			$importer = Popov_Magmi_Import_Factory::create($importType);
			$importer->run();
			echo 'Import finished successfully' . "\r\n";
		} else {
			echo $this->usageHelp();
		}
	}
	
	/**
	 * Retrieve Usage Help Message
	 */
	public function usageHelp()	{
		return <<<USAGE
Usage:  php magmi.php -- [options]

  --import <type>            Available two type of import: "product" and "image"

USAGE;
	}

}

$shell = new Mage_Shell_Magmi();
$shell->run();