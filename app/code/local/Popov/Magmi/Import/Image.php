<?php
/**
 * Import any media structure
 *
 * @category Popov
 * @package Popov_Magmi
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
	protected $imagesSource = null;

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

	/**
     * Collected attributes (directories named as attribute labels)
     *
     * @var array
     */
	protected $collectedAttrs = [];

    protected $level = 0;

    protected $pathNested = [];

    protected $processedConfig = [];

    protected $variables = [];

	public function __construct()
    {
		$this->importFile = new \SplFileInfo(Mage::getBaseDir('var') . '/import/import-image.csv');
	}

    /**
     * Has nested directories current config
     *
     * @return bool
     */
    protected function hasNested()
    {
        $config = $this->getProcessedConfig();

        return isset($config['scan']) && ($config['type'] == 'dir');
    }

    protected function isDir()
    {
        $config = $this->getProcessedConfig();

        return ($config['type'] == 'dir');
    }

    protected function isFile()
    {
        $config = $this->getProcessedConfig();

        return ($config['type'] == 'file');
    }

    protected function isChild()
    {
        return ($this->level > 0);
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

    protected function getProcessedConfig()
    {
        return $this->processedConfig;
    }

    public function getImagesSource()
    {
        if (!$this->imagesSource) {
            $config = $this->getCurrentConfig();
            if ('unix' === $this->getSystemOs() && ('/' === $config['source_path'][0])) {
                $sourcePath =  $config['source_path'];
            } elseif ('win' === $this->getSystemOs() && (':' === $config['source_path'][1])) {
                $sourcePath =  $config['source_path'];
            } else {
                $sourcePath = Mage::getBaseDir() . '/' . $config['source_path'];
            }
            $this->imagesSource = new \SplFileInfo($sourcePath);
        }

        return $this->imagesSource;
    }

    protected function getDefaultValues($withKeys = false)
    {
        static $default = [];

        if (!$withKeys && isset($default['without'])) {
            return $default['without'];
        } elseif ($withKeys && isset($default['with'])) {
            return $default['with'];
        }

        $default['with'] = [
            'store' => 'admin',
            'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'status' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
        ];
        $default['without'] = array_values($default['with']);

        if ($withKeys) {
            return $default['with'];
        }

        return $default['without'];
    }

    protected function getVariable($name)
    {
        if (isset($this->variables[$name])) {
            return $this->variables;
        }

        return false;
    }

    protected function addVariable($name, $value)
    {
        $this->variables[$name] = $value;

        return $this;
    }

    protected function filterVariables($variables)
    {
        foreach ($variables as $name => $variable) {
            if (!is_integer($name)) {
                $this->variables[$name] = $variable;
            }
        }

        return $this;
    }

	public function preImport()
    {
        parent::preImport();

        if (($exist = $this->prepareImageFileImport()) === true) {
            $this->setCmdFlag('profile', 'image-import');
            $this->setCmdFlag('CSV:filename', $this->importFile->getPathname());
        }

        return $exist;
	}

	protected function prepareImageFileImport()
    {
        $this->level = 0;
        $this->collectedAttrs = [];

        $this->processedConfig = $config = $this->getCurrentConfig();

        $imagesSource = $this->getImagesSource();
		if (!$imagesSource->isDir()) {
			return false;
		}

		$imagesDir = $imagesSource->getPathname() . '/';
		$grepMode = $this->isDir() ? Varien_Io_File::GREP_DIRS : Varien_Io_File::GREP_FILES;
		$io = $this->getIo();
		$io->cd($imagesDir);
		$files = $io->ls($grepMode);

		$step = self::ITERATION_LIMIT;
		$count = count($files);
        for ($i = 0; $i < $count; $i += $step) {
            $filesSlice = array_slice($files, $i, $step);

            $fileIds = $this->prepareFileIds($filesSlice);

            $adminStoreId = Mage_Core_Model_App::ADMIN_STORE_ID;
            $currentStoreId = Mage::app()->getStore()->getId();

            Mage::app()->setCurrentStore($adminStoreId);
            $products = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToFilter($config['name']['to_attribute'], ['in' => $fileIds]);

            foreach ($products as $product) {
                if ($this->isDir()) {
                    $fileId = $this->specialCharsEncode($product->getData($config['name']['to_attribute']));
                    $this->pathNested[$this->level] = $fileId;
                }

                if ($this->hasNested()) {
                    $simpleProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);
                    $this->processChildren($config['scan'], $simpleProducts/*, $product*/);
                    $this->processedConfig = $config;
                }

                $this->writeCsv($product->getData('sku')/*, $defaultValues*/);
            }
            Mage::app()->setCurrentStore($currentStoreId);
        }
        return $count ? $this->getImageCsv()->streamClose() : false;
    }


	protected function processChildren($config, $products/*, $parentProduct*/)
    {
        $this->level++;
        $this->processedConfig = $config;
        foreach ($products as $product) {
            if (!isset($config['name']['to_attribute'])) {
                continue;
            }

            $attr = $product->getResource()->getAttribute($config['name']['to_attribute']);
            $attr->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);
            if (!$attr->usesSource()) {
                continue;
            }

            $attrLabel = $product->getAttributeText($config['name']['to_attribute']);
            $collectedAttrs = $this->getCollectedAttrs();
            if (in_array($attrLabel, $collectedAttrs[end($this->pathNested)])) {
                $this->pathNested[$this->level] = $attrLabel;
                $relativePath = implode('/', $this->pathNested);
                $this->writeCsv($product->getData('sku')/*, $defaultValues*/);

                if ($this->hasNested()) {
                    $path = $this->imagesSource->getPathname() . '/' . $relativePath;
                    $io = $this->getIo();
                    $io->cd($path);
                    $files = $io->ls(Varien_Io_File::GREP_DIRS);
                    $fileIds = $this->prepareFileIds($files);

                    $subProducts = Mage::getResourceModel('catalog/product_collection')
                        ->addAttributeToFilter($config['scan']['name']['to_attribute'], ['in' => $fileIds]);

                    $this->processChildren($config['scan'], $subProducts);
                }
                array_pop($this->pathNested);
            }
        }

        $this->level--;

    }

	protected function writeCsv($sku)
    {
        $config = $this->getProcessedConfig();
        $defaultValues = $this->getDefaultValues();

        if (isset($config['options']['values'])) {
            $defaultValues = array_values(array_merge($this->getDefaultValues(true), $config['options']['values']));
        }

        $images = $this->prepareImages($sku);
        $galleryImages = ($images['media_gallery'])
            ? '+' . implode(';+', $images['media_gallery']) // not exclude image
            : '';

        $generatedValues = [
            $sku,
            '+' . $images['image'],
            '+' . $images['small_image'],
            '+' . $images['thumbnail'],
            $galleryImages
        ];
        $values = array_merge($defaultValues, $generatedValues);

		return $this->getImageCsv()->streamWriteCsv($values, $this->delimiter);
	}

    protected function prepareFileIds($files)
    {
        $config = $this->getProcessedConfig();
        $io = $this->getIo();

        $fileIds = [];
        foreach ($files as $file) {
            $fileName = $file['text'];
            if (isset($config['name']['pattern']) && $config['name']['pattern']) {
                preg_match('/' . $config['name']['pattern'] . '/', $file['text'], $matched);
                $fileName = $matched[$config['name']['to_attribute']];
                $this->filterVariables($matched);
            }

            $fileId = $this->specialCharsReplace(pathinfo($fileName, PATHINFO_FILENAME));
            $fileIds[] = $fileId;
            $this->addVariable($config['name']['to_attribute'], $fileId);

            if ($this->hasNested()) {
                $io->cd($file['id']);
                $subDirs = $io->ls(Varien_Io_File::GREP_DIRS);
                foreach ($subDirs as $d) {
                    $this->collectAttrs($fileName, $d['text']);
                }
            }
        }

        return array_values(array_unique($fileIds));
    }

    protected function getPath()
    {
        return $this->pathNested
            ? $this->imagesSource->getPathname() . '/' . implode('/', $this->pathNested) . '/'
            : $this->imagesSource->getPathname() . '/';
    }

	protected function parseRelativePath($path)
    {
        $sourcePath = $this->imagesSource->getPathname();
        $relativePath = mb_substr($path, mb_strlen($sourcePath)); // with leading slash

        if (is_dir($path)) {
            $relativePath .= '/';
        }

		return $relativePath;
	}

	protected function prepareImages($sku)
    {
        $path = $this->getPath();
        $config = $this->getProcessedConfig();
        $skuEncoded = $this->specialCharsEncode($sku);

        $imageKeys = ['image', 'small_image', 'thumbnail'];
        $images = [];
        $images['media_gallery'] = $this->prepareGallery($sku);
        $images['image'] = $images['media_gallery'] ? $images['media_gallery'][0] : '';
        foreach ($imageKeys as $key) {
            if (isset($config['images'][$key])) {
                $configValue = $config['images'][$key];
                $globPath = $path . str_replace('%sku%', $skuEncoded, $configValue);
                $globImages = glob($globPath);
                if (!$globImages) {
                    $globImages[0] = $images['image'];
                }
                $images[$key] = $this->parseRelativePath($globImages[0]);
            } else {
                $images[$key] = $images['image'];
            }
        }

		return $images;
	}

    /**
     * @param $sku
     * @return array
     */
	protected function prepareGallery($sku)
    {
        $path = $this->getPath();
        $config = $this->getProcessedConfig();
        $skuEncoded = $this->specialCharsEncode($sku);

        if (!isset($config['images']['media_gallery'])) {
            return [];
        }

        // @todo Use global variable replacement
        $globPath = $path . str_replace('%sku%', $skuEncoded, $config['images']['media_gallery']);
        $galleryImages = glob($globPath);

        $gallery = [];
        foreach ($galleryImages as $file) {
			$gallery[] = $this->parseRelativePath($file);
		}

		return $gallery;
	}

	public function setImageCsv($imageCsv)
    {
        $this->imageCsv = $imageCsv;

        return $this;
    }

	protected function getImageCsv()
    {
		if (!$this->imageCsv) {
			$imageCsv = new Varien_Io_File();
			$imageCsv->open(array('path' => $this->importFile->getPath()));
			$imageCsv->streamOpen($this->importFile->getFilename());
			$imageCsv->streamWriteCsv($this->fieldsNames, $this->delimiter);

			$this->imageCsv = $imageCsv;
		}
		return $this->imageCsv;
	}
}