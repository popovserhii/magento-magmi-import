<?php
/**
 * Enter description here...
 *
 * @category Popov
 * @package Popov_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 07.12.13 17:08
 */

class Popov_Magmi_Import_Image extends Popov_Magmi_Import_Abstract {

	/**
	 * Number of items in iteration
	 *
	 * @var int
	 */
	const ITEMS_IN_ITERATION = 100;

	/**
	 * @var \SplFileInfo
	 */
	protected $imagesDir = null;

	/**
	 * @var \SplFileInfo
	 */
	protected $importDir = null;

	/**
	 * @var Varien_Io_File
	 */
	protected $imageCsv;

	protected $fieldsNames = array(
		'store',
		'visibility',
		'status',

		'sku',
		'image',
		'small_image',
		'thumbnail',
		'media_gallery'
	);

	protected $attributeSets = [
		'Default' => [
			'colorAttr' => 'color',
		],
		'Oodji' => [
			'colorAttr' => 'color_code'
		],
	];


	public function __construct() {
		$this->imagesDir = new \SplFileInfo(Mage::getBaseDir('media') . '/import/images');
		$this->importFile = new \SplFileInfo(Mage::getBaseDir('var') . '/import/import-image.csv');
	}

	public function preImport() {
		//Zend_Debug::dump($this->prepareImageFileImport());
		if (($exist = $this->prepareImageFileImport()) === true) {
			$this->setCmdFlag('profile', 'image-import');
			$this->setCmdFlag('CSV:filename', $this->importFile->getPathname());
		}

		return $exist;
	}

	public function postImport() {
		$this->fix404Error();
		$this->backupImages();
		//$this->reindex('lite');
		$this->clearCache();
	}

	protected function prepareImageFileImport() {
		if (!$this->imagesDir->isDir()) {
			return false;
		}
		
		$imagesDir = $this->imagesDir->getPathname() . '/';
		$io = $this->getIo();
		$io->cd($imagesDir);
		$dirs = $io->ls(Varien_Io_File::GREP_DIRS);

		$defaultValues = $simpleDefaultValues = array('admin',  4, 1);
		$simpleDefaultValues[1] = 1; // set not visible for simple products

		$step = self::ITEMS_IN_ITERATION;
		$count = count($dirs);
		for ($i = 0; $i < $count; $i += $step) {
			$dirsSlice = array_slice($dirs, $i, $step);
			$color = array();
			$configurableIds = array();

			foreach ($dirsSlice as $dir) {
				$configurableId = $this->specialCharsReplace($dir['text']);
				$configurableIds[] = $configurableId;

				$io->cd($dir['id']);
				$colorDirs = $io->ls(Varien_Io_File::GREP_DIRS);

				foreach ($colorDirs as $d) {
					$color[$configurableId][] = $d['text'];
				}
			}

			$adminStoreId = Mage_Core_Model_App::ADMIN_STORE_ID;
			$currentStoreId = Mage::app()->getStore()->getId();

			Mage::app()->setCurrentStore($adminStoreId);
			$configurableProducts = Mage::getResourceModel('catalog/product_collection')
				->addAttributeToFilter('sku', array('in' => $configurableIds))
			;

			//Zend_Debug::dump($configurableProducts->getSelect()->__toString());
			//Zend_Debug::dump($configurableIds);

			foreach ($configurableProducts as $configurable) {
				$simpleProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $configurable);
				$configurableSkuDir = $this->specialCharsEncode($configurable->getSku());

				foreach ($simpleProducts as $simpleProduct) {
					if ($colorAttrName = $this->getColorAttrName($simpleProduct)) {
						$attr = $simpleProduct->getResource()->getAttribute($colorAttrName);
						$attr->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);
						if (!$attr->usesSource()) {
							continue;
						}
						//$colorLabel = strtolower($attr->getSource()->getOptionText($simpleProduct->getAttributeText('color')));
						$colorLabel = $simpleProduct->getAttributeText($colorAttrName);
						//Zend_Debug::dump($colorLabel);
						//Zend_Debug::dump($color); die(__METHOD__);
						if (in_array($colorLabel, $color[$configurable->getSku()])) {
							$relativePath = sprintf('%s/%s/', $configurableSkuDir, $colorLabel);
							$this->writeCsv($simpleProduct->getSku(), $relativePath, $simpleDefaultValues);
						}
					}
				}

				$relativePath = sprintf('%s/', $configurableSkuDir);
				$this->writeCsv($configurable->getSku(), $relativePath, $defaultValues);
			}
			Mage::app()->setCurrentStore($currentStoreId);
		}
		
		//Zend_Debug::dump($configurableProducts->getSize()); die(__METHOD__);
		
		return $count ? $this->getImageCsv()->streamClose() : false;
	}

	protected function writeCsv($sku, $relativePath, $defaultValues) {
		$imagesDir = $this->imagesDir->getPathname() . '/';
		$mainImage = $this->prepareMainImage($imagesDir . $relativePath);

		$realpath = $imagesDir . '..' . $mainImage;
		//Zend_Debug::dump(file_exists($realpath)); die(__METHOD__);
		if ($realpath && file_exists($realpath)) {
			$galleryImages = $this->prepareGallery($imagesDir . $relativePath);
			//$galleryImages = str_replace(" ", "\\ ", $this->prepareGallery($imagesDir . $relativePath));
			$values = array_merge($defaultValues, array($sku, '+' . $mainImage, '+' . $mainImage, '+' . $mainImage, $galleryImages));
			//Zend_Debug::dump($values);
			$this->getImageCsv()->streamWriteCsv($values, $this->delimiter);
		}
	}

	public function getColorAttrName($product) {
		/*$website = Mage::app()->getWebsite($product->getWebsiteIds()[0]);
		return $this->websites[$website->getCode()]['colorAttr'];*/

		$attributeSetModel = Mage::getModel("eav/entity_attribute_set");
		$attributeSetModel->load($product->getAttributeSetId());
		$attributeSetName = $attributeSetModel->getAttributeSetName();

		if (isset($this->attributeSets[$attributeSetName])) {
			return $this->attributeSets[$attributeSetName]['colorAttr'];
		}

		return false;
	}

	protected function getRelativePath($path) {
		$tmp = explode('import', rtrim($path, '/'));
		$relativePath = (isset($tmp[1]) ? $tmp[1] : '') . '/';

		return $relativePath;
	}

	protected function prepareMainImage($path) {
		$mainImage = $this->getRelativePath($path) . 'main.jpg';

		return $mainImage;
	}

	protected function prepareGallery($path) {
		$io = $this->getIo();
		$io->cd($path);
		$galleryImages = $io->ls(Varien_Io_File::GREP_FILES);
		$relativePath = $this->getRelativePath($path);

		$gallery = array();
		foreach ($galleryImages as $file) {
			$gallery[] = $relativePath . $file['text'];
		}

		return '+' . implode(';+', $gallery); // not exclude image
	}

	/**
	 * Get PHP interpreter path
	 *
	 * Replace filename to "php"
	 *
	 * @return string
	 */
	/*protected function getInterpreter() {
		$phpPath = new \SplFileInfo(PHP_BINARY);

		return $phpPath->getPath() . '/php';
	}*/

	protected function getImageCsv() {
		if (!$this->imageCsv) {
			$imageCsv = new Varien_Io_File();
			$imageCsv->open(array('path' => $this->importFile->getPath()));
			$imageCsv->streamOpen($this->importFile->getFilename());
			$imageCsv->streamWriteCsv($this->fieldsNames, $this->delimiter);

			$this->imageCsv = $imageCsv;
		}
		return $this->imageCsv;
	}

	protected function backupImages() {
		$backupDir = 'backup';
		$io = $this->getIo();
		$io->cd($this->imagesDir->getPath());
		$io->mkdir('backup');
		$src = $this->imagesDir->getPathname();
		$dst = $this->imagesDir->getPath() . '/' . $backupDir;
		$this->rmove($src, $dst);
		$io->rmdir($src, true);
		$io->mkdir($src);
	}

}