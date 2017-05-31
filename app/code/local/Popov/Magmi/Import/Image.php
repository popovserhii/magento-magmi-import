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
	const ITERATION_LIMIT = 100;

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

	protected $websites = array(
        'admin' => []
    );

	/**
     * Collected attributes (directories named as attribute labels)
     *
     * @var array
     */
	protected $collectedAttrs = [];

	protected $attributeSets = [
		'Default' => [
			'colorAttr' => 'color',
		],
		'Oodji' => [
			'colorAttr' => 'color_code'
		],
	];


	public function __construct() {
		//$this->imagesDir = new \SplFileInfo(Mage::getBaseDir('media') . '/import/images');
		$this->importFile = new \SplFileInfo(Mage::getBaseDir('var') . '/import/import-image.csv');
	}

    /**
     * Has nested directories current config
     *
     * @return bool
     */
    protected function hasNested()
    {
        $config = $this->getCurrentConfig();

        return isset($config['scan']) && ($config['scan']['type'] == 'dir');
    }

    protected function collectAttrs($dirId, $subDirId)
    {
        $this->collectedAttrs[$dirId][] = $subDirId;

        return $this;
    }

    protected function getCollectedAttrs()
    {
        return $this->collectedAttrs;
    }

	public function preImport()
    {
        parent::preImport();


        /*'scan' => [
            'product_type' => 'configurable', // simple
            // If type "dir" than images put in this directory
            // if type "file" than images should look in current directory
            'type' => 'dir', // 'type' => 'file'
            'strategy' => 'simple', // 'strategy' => 'pattern'
            'name_to_attribute' => 'sku', // filename to attribute name
            'images' => '%sku%/*.jpg',
            'scan' => []
        ];*/

        //foreach ($this->getConfig() as $config) {
        //    $this->currentConfig = $config['scan'];
            if (($exist = $this->prepareImageFileImport($this->getCurrentConfig())) === true) {
                $this->setCmdFlag('profile', 'image-import');
                $this->setCmdFlag('CSV:filename', $this->importFile->getPathname());
            }
        //}

        return $exist;
	}

	/*public function postImport() {
		#$this->fix404Error();
		#$this->backupImages();
		//$this->reindex('lite');
		#$this->clearCache();
	}*/

	protected function prepareImageFileImport()
    {
        $config = $this->getCurrentConfig();

        $this->imagesDir = new \SplFileInfo(Mage::getBaseDir() . '/' . $config['source_path']);

		if (!$this->imagesDir->isDir()) {
			return false;
		}


		$imagesDir = $this->imagesDir->getPathname() . '/';
		$grepMode = ('dir' == $config['type'])
            ? Varien_Io_File::GREP_DIRS
            : Varien_Io_File::GREP_FILES;
		$io = $this->getIo();
		$io->cd($imagesDir);
		$files = $io->ls($grepMode);
        $defaultValues = $simpleDefaultValues = [
            'admin',
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
        ];
        $simpleDefaultValues[1] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE; // set not visible for simple products

		$step = self::ITERATION_LIMIT;
		$count = count($files);
		for ($i = 0; $i < $count; $i += $step) {
			$filesSlice = array_slice($files, $i, $step);
			$color = array();
			$fileIds = array();
			$fileNames = [];
			foreach ($filesSlice as $file) {
                $fileId = $this->specialCharsReplace($file['text']);
                $fileNames[] = $fileId;
                $fileIds[] = $this->specialCharsReplace(pathinfo($file['text'], PATHINFO_FILENAME));

				if ($this->hasNested()) {
                    $io->cd($file['id']);
                    $subDirs = $io->ls(Varien_Io_File::GREP_DIRS);
                    foreach ($subDirs as $d) {
                        //$color[$fileId][] = $d['text'];
                        $this->collectAttrs($fileId, $d['text']);
                    }
                }
			}

			$adminStoreId = Mage_Core_Model_App::ADMIN_STORE_ID;
			$currentStoreId = Mage::app()->getStore()->getId();

			Mage::app()->setCurrentStore($adminStoreId);
            $products = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToFilter($config['name_to_attribute'], ['in' => $fileIds]);

			foreach ($products as $product) {
                $skuAsFilename = $this->specialCharsEncode($product->getSku());

                if ($this->hasNested()) {
                    $simpleProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);
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
                            if (in_array($colorLabel, $color[$product->getSku()])) {
                                $relativePath = sprintf('%s/%s/', $skuAsFilename, $colorLabel);
                                $this->writeCsv($simpleProduct->getSku(), $relativePath, $simpleDefaultValues);
                            }
                        }
                    }
                }

				$relativePath = sprintf('%s/', $skuAsFilename);
				//$this->writeCsv($product->getSku(), $relativePath, $defaultValues);
				$this->writeCsv($product->getSku(), $defaultValues);
			}
			Mage::app()->setCurrentStore($currentStoreId);
		}
		
		//Zend_Debug::dump($configurableProducts->getSize()); die(__METHOD__);
		
		return $count ? $this->getImageCsv()->streamClose() : false;
	}


	protected function processFileTypeStructure()
    {

    }

	protected function writeCsv($sku, $defaultValues) {
		$imagesDir = $this->imagesDir->getPathname() . '/';


        $galleryImages = $this->prepareGallery($sku, $imagesDir);
        // @todo Create config glob option/pattern for find main image
        $mainImage = $this->prepareMainImage($sku, $imagesDir);

        // @todo Get images based on config
        if (!$mainImage && !isset($config['images']['image']) && $galleryImages) {
            $mainImage = $galleryImages[0];
        }
        if (!isset($config['images']['small_image'])) {
            $smallImage = $mainImage;
        }
        if (!isset($config['images']['thumbnail'])) {
            $thumbnail = $mainImage;
        }

        $galleryImages = ($galleryImages)
            ? '+' . implode(';+', $galleryImages) // not exclude image
            : '';


		#$realPath = $imagesDir . '..' . $mainImage;
		#if ($realPath && file_exists($realPath)) {
			//$galleryImages = $this->prepareGallery($imagesDir . $relativePath);

			$values = array_merge($defaultValues, array($sku, '+' . $mainImage, '+' . $smallImage, '+' . $thumbnail, $galleryImages));
			$this->getImageCsv()->streamWriteCsv($values, $this->delimiter);
		#}
	}

	public function getColorAttrName($product) {

		#$attributeSetModel = Mage::getModel("eav/entity_attribute_set");
		#$attributeSetModel->load($product->getAttributeSetId());
		#$attributeSetName = $attributeSetModel->getAttributeSetName();

		#if (isset($this->attributeSets[$attributeSetName])) {
		#	return $this->attributeSets[$attributeSetName]['colorAttr'];
		#}
		#return false;

        return 'color';
	}

	protected function getRelativePath($path)
    {
        $sourcePath = $this->imagesDir->getPathname();
        $relativePath = mb_substr($path, mb_strlen($sourcePath)); // with leading slash

        if (is_dir($path)) {
            $relativePath .= '/';
        }

		return $relativePath;
	}

	protected function prepareMainImage($sku, $path) {
	    $config = $this->getCurrentConfig();

	    if (!$config['images']['image']) {
            return '';
        }

        $skuEncoded = $this->specialCharsEncode($sku);
        $globPath = $path . str_replace('%sku%', $skuEncoded, $config['images']['image']);

        #$io = $this->getIo();
        #$io->cd($path);
        #$galleryImages = $io->ls(Varien_Io_File::GREP_FILES);
        #$relativePath = $this->getRelativePath($path);

        $images = glob($globPath);
        if (!$images) {
            return '';
        }

        #$imagesDir = $this->imagesDir->getPathname();
        #$image = str_replace($imagesDir, '', $images[0]);



		$mainImage = $this->getRelativePath($images[0]);

		return $mainImage;
	}

    /**
     * @param $path
     * @return array
     */
	protected function prepareGallery($sku, $path)
    {
        $config = $this->getCurrentConfig();

        if (!isset($config['images']['media_gallery'])) {
            return [];
        }

        // @todo Use global variable replacement
        $globPath = $path . str_replace('%sku%', $sku, $config['images']['media_gallery']);

		#$io = $this->getIo();
		#$io->cd($path);
		#$galleryImages = $io->ls(Varien_Io_File::GREP_FILES);
		#$relativePath = $this->getRelativePath($path);

        $galleryImages = glob($globPath);

        $imagesDir = $this->imagesDir->getPathname();

        $gallery = array();
		foreach ($galleryImages as $file) {
			//$gallery[] = $relativePath . $file['text'];
			$gallery[] = str_replace($imagesDir, '', $file['text']);
		}

		//return '+' . implode(';+', $gallery); // not exclude image
		return $gallery; // not exclude image
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

	public function setImageCsv($imageCsv)
    {
        $this->imageCsv = $imageCsv;

        return $this;
    }

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