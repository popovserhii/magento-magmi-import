<?php
/**
 * Magmi product importer
 *
 * @category Popov
 * @package Popov_<package>
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 09.12.13 11:36
 */

class Popov_Magmi_Import_Product extends Popov_Magmi_Import_Abstract {

	protected $reconXmlFileName = 'message.xml';

	/**
	 * Import file glob pattern
	 *
	 * @var string
	 */
	protected $filePattern = 'items_*[0-9].csv';

    /**
     * Current open imported file available by store code
     *
     * @var array
     */
	protected $importFiles = [];

	/**
	 * @todo move import files for websites in different names that same as website code
	 */
	protected $__websitesDirs;

	public function __construct() {
		$this->__websitesDirs = [
			//'default' => Mage::getBaseDir('var') . '/import/default', // @todo
			'base' => Mage::getBaseDir('var') . '/import/base',
			'oodji' => Mage::getBaseDir('var') . '/import/oodji',
		];
	}

	public function getReconXmlFileName()
    {
        return $this->reconXmlFileName;
    }

	protected function isReindexAllow()
    {
	   return $this->isCurrentWebsiteLast();
	}

	public function preImport() {
	    parent::preImport();

		$importFile = $this->getImportFile();

		if (($exist = $importFile->isFile()) === true) {
			$this->setCmdFlag('profile', 'md-fashion');
			$this->setCmdFlag('mode', 'create');
			$this->setCmdFlag('CSV:filename', $importFile->getPathname());
			$this->unsetCmdFlag('REINDEX:indexes');

			if (!$this->isReindexAllow()) {
				$this->setCmdFlag('REINDEX:indexes', 'none'); // disable indexing for current website
			}
		}

		return $exist;
	}

	public function postImport() {
		$this->fix404Error();
		$this->writeRecon();
		$this->backupImportFile();
		$this->hideProductsHaveNoPhoto();
		$this->changeColorPosition();
		#$this->reindex();
		$this->clearCache();

        parent::postImport();
	}

	protected function getReceivedNum() {
	    $importFile = $this->getImportFile();
		$filename = $importFile->getBasename('.' . $importFile->getExtension());
		$part = explode('_', $filename);
		if (!isset($part[1]) || !is_numeric($part[1])) {
			Mage::throwException(
			    'Import file name not contains receivedNo. File name must have format "import_%receivedNo%.csv".'
            );
		}

		return $part[1];
	}

	public function getImportFile($website = null) {
		$_website = $website ?: $this->currentWebsite;
		$pathPattern = $this->__websitesDirs[$_website] . '/' . $this->filePattern;

		if (!isset($this->importFiles[$_website])) {
            $files = glob($pathPattern);
            $this->importFiles[$_website] = new \SplFileInfo(end($files));
        }

		return $this->importFiles[$_website];
	}

	/**
	 * Create reconciliation (response) message
	 */
	protected function writeRecon() {
		$reconFile = new \SplFileInfo($this->__websitesDirs[$this->currentWebsite] . '/' . $this->reconXmlFileName);
		$recon = new SimpleXMLElement(file_get_contents($reconFile->getPathname()));
		$recon->children('v8msg', true)->Header->MessageNo = $this->getReceivedNum();
		$recon->children('v8msg', true)->Header->ReceivedNo = $this->getReceivedNum();
		$recon->asXml($reconFile->getPathname());
	}

	protected function backupImportFile() {
        $importFile = $this->getImportFile();
		$backupPath = $importFile->getPath() . '/backup';

		$this->getIo()->mkdir($backupPath);
		$this->getIo()->mv($importFile->getPathname(), $backupPath . '/' . $importFile->getBasename());
	}

	protected function hideProductsHaveNoPhoto() {
		$connectionRead = Mage::getSingleton('core/resource')->getConnection('core_read');
		$connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');

		$attribute = $connectionRead->query("SELECT `attribute_id` FROM `eav_attribute` WHERE `attribute_code` = 'status'")->fetch();

		$sql = "
			-- here you set every one as DISABLED (id 2)
			UPDATE catalog_product_entity_int SET value = 2
			-- here you are change just the attribute STATUS
			WHERE attribute_id = {$attribute['attribute_id']}
				-- here you are looking for the products that match your criteria
				AND entity_id IN (
					-- your original search
					SELECT catalog_product_entity.entity_id
			FROM catalog_product_entity_media_gallery
			RIGHT OUTER JOIN catalog_product_entity ON catalog_product_entity.entity_id = catalog_product_entity_media_gallery.entity_id
			WHERE catalog_product_entity_media_gallery.value IS NULL)";

		$connectionWrite->query($sql);
	}

	protected function changeColorPosition() {
		$connectionRead = Mage::getSingleton('core/resource')->getConnection('core_read');
		$connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');

		$attribute = $connectionRead->query("SELECT `attribute_id` FROM `eav_attribute` WHERE `attribute_code` = 'color'")->fetch();
		$sql = "UPDATE `catalog_product_super_attribute` SET `position` = 2 WHERE `attribute_id` = {$attribute['attribute_id']}";
		$connectionWrite->query($sql);
	}

}