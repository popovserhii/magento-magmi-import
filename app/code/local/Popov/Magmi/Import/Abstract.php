<?php
/**
 * Magmi importer
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 06.12.13 18:27
 */

abstract class Popov_Magmi_Import_Abstract {

    const RUN_MODE_REAL = 'real';
    const RUN_MODE_DEBUG = 'debug';

	/**
	 * @var SplFileInfo
	 */
	protected $importFile;

	protected $logFile = 'magmi-import.log';

    /**
     * @var Popov_Magmi_Helper_SpecialChar
     */
	protected $specialHelper;

	/**
	 * Csv delimiter
	 *
	 * @var string
	 */
	protected $delimiter = ';';

	/**
	 * @var Varien_Io_File
	 */
	protected $io;

	protected $cmdFlags = array(
		'profile' => 'default',
		'mode' => 'update',
		'CSV:filename' => '',
	);

	/**
	 * Available indexes
	 *
	 * @var array
	 */
	protected $indexes = array(
		'lite' => array(
			'catalog_product_attribute',	// Product Attributes
			'catalog_product_price', 		// Product Prices
			'catalog_product_flat', 		// Product Flat Data
			'catalog_category_flat', 		// Category Flat Data
			'catalogsearch_fulltext', 		// Catalog Search Index
			'cataloginventory_stock', 		// Stock status
		),
		'long' => array(
			'catalog_url', 					// Catalog Url Rewrites
			'catalog_category_product', 	// Category Products
		)
	);

	/**
	 * Switch for backup database
	 *
	 * @var bool
	 */
	protected $backupDb = false;

	protected $currentWebsite = null;

	protected $tasks = [];

    protected $runMode = self::RUN_MODE_REAL;

    /**
     * Config related to import (class) type
     *
     * @var array
     */
    protected $config = [];

    /**
     * General import config which applies to to all imports
     *
     * @var array
     */
    protected $generalConfig = [];

    /** @var array */
    protected $currentConfig = [];

    /** @var Mage_Cms_Model_Template_Filter */
    protected $filter;

    protected $variables = [];

    public function setRunMode($mode)
    {
        $this->runMode = $mode;
    }

    public function isRealMode()
    {
        return $this->runMode === self::RUN_MODE_REAL;
    }

    public function isDebugMode()
    {
        return $this->runMode === self::RUN_MODE_DEBUG;
    }

    public function setGeneralConfig($config)
    {
        $this->generalConfig = $config;

        return $this;
    }

    public function getGeneralConfig()
    {
        return $this->generalConfig;
    }

    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getCurrentConfig()
    {
        return $this->currentConfig;
    }

    protected function getVariable($name)
    {
        if (isset($this->variables[$name])) {
            return $this->variables;
        }

        return false;
    }

    protected function setVariable($name, $value)
    {
        $this->variables[$name] = $value;

        if (is_array($value)/* || is_object($value)*/) {
            $value = [$name => new Varien_Object($value)];
        }
        //array('data' => new Varien_Object($data));
        $this->getFilter()->setVariables([$name => $value]);

        return $this;
    }

    protected function addVariable($name, $value)
    {
        if (isset($this->variables[$name])) {
            Mage::throwException(sprintf('Variable %s already isset. You cannot add one variable twice.'));
        }

        return $this->setVariable($name, $value);
    }

    protected function filterVariables($variables)
    {
        foreach ($variables as $name => $variable) {
            if (!is_integer($name)) {
                $this->setVariable($name, $variable);
            }
        }

        return $this;
    }

    /**
     * @return Mage_Cms_Model_Template_Filter $filter
     */
    public function getFilter()
    {
        if (!$this->filter) {
            $this->filter = Mage::getModel('cms/template_filter');
        }

        return $this->filter;
    }

    public function filter($context)
    {
        return $this->getFilter()->filter($context);
    }

    public function preImport()
    {
        $this->runJobs('pre');
    }

    public function getAbsolutePath($path)
    {
        $config = $this->getCurrentConfig();
        if ('unix' === $this->getSystemOs() && ('/' === $path)) {
            $absolutePath = $path;
        } elseif ('win' === $this->getSystemOs() && (':' === $config['source_path'][1])) {
            $absolutePath = $path;
        } else {
            $absolutePath = Mage::getBaseDir() . '/' . $path;
        }

        return $absolutePath;
    }

	public function postImport()
    {
        $this->runJobs('post');
    }

	final public function run()
    {
        foreach ($this->getConfig() as $name => $config) {
            $this->currentConfig = $config;

		    //foreach ($this->websites as $name => $attrs) {
			//$this->currentWebsite = $name;
            $this->log(sprintf('Import %s: run prepare jobs for config: ' . $name, $this->getImportCode()));
            try {
				if ($this->preImport()) {
					#$this->backupDb();

                    $this->log(sprintf('Import %s: run import for config: ' . $name, $this->getImportCode()));
                    $this->import();

                    $this->log(sprintf('Import %s: run post jobs for config: ' . $name, $this->getImportCode()));
                    $this->postImport();
                }
            } catch (Exception $e) {
			    $this->log($e->getMessage(), Zend_Log::CRIT);
				Mage::logException($e);
			}
		}
	}

	public function runJobs($alter)
    {
        $jobsConfig = $this->getAlterTasks($alter);
		
        foreach ($jobsConfig as $jobConfig) {
            list($selector, $method) = explode('::', current($jobConfig));
            $factoryName = key($jobConfig);
            if ('model' === $factoryName) {
                $task = Mage::getModel($selector);
            } elseif ('singleton' === $factoryName) {
                $task = Mage::getSingleton($selector);
            } elseif ('helper' === $factoryName) {
                $task = Mage::helper($selector);
            } else {
                Mage::throwException(sprintf('Magento factory with key "%s" not found.', $factoryName));
            }
            $task->{$method}($this);
        }
    }

    public function getAlterTasks($alter)
    {
        if (!$this->tasks) {
            $typeNode = $this->getImportCode() . '_import';
            $tasks = (array) Mage::getConfig()->getNode('popov_magmi/jobs/' . $typeNode);
            foreach ($tasks as $name => $task) {
                if (!$task) {
                    continue;
                }
                $this->tasks[(string) $task->alter][] = [
                    $task->run->children()->getName() => (string) $task->run->children()
                ];
            }
        }

        return isset($this->tasks[$alter]) ? $this->tasks[$alter] : [];
    }

    public function getImportCode()
    {
        static $code;

        $parts = explode('_', get_class($this));
        return $code ?: $code = strtolower(array_pop($parts));
    }

	public function getWebsiteCodes()
    {
        return $this->websites;
    }

    public function getCurrentWebsiteCode()
    {
	    return $this->currentWebsite;
    }

    public function getIndexedWebsiteCodes()
    {
        static $indexes = [];

        if (!$indexes) {
            $i = 0;
            foreach ($this->websites as $name => $attrs) {
                $indexes[$i++] = $name;
            }
        }

        return $indexes;
    }

    public function getLastWebsiteIndex()
    {
        static $last;

        if (!$last ) {
            $last = count($this->getWebsiteCodes()) - 1;
        }

        return $last;
    }

    public function isCurrentWebsiteLast()
    {
        $indexes = $this->getIndexedWebsiteCodes();
        $last = $this->getLastWebsiteIndex();

        $isLast = false;
        if ($this->getCurrentWebsiteCode() === $indexes[$last]) {
            $isLast = true;
        }

        return $isLast;
    }

	public function getIo() {
		if (!$this->io) {
			$this->io = new Varien_Io_File();
		}
		return $this->io;
	}

	/**
	 * Get PHP interpreter path
	 *
	 * Replace filename to "php"
	 *
	 * @return string
	 */
	protected function getInterpreter() {
		//$exec = exec('whereis php');
		//$parts = explode(' /', $exec);
		//$binnary = isset($parts[1]) ? $parts[1] : 'php';
		$binnary = 'php';

		return $binnary;
	}

	public function getSpecialHelper()
    {
        if (!$this->specialHelper) {
            $this->specialHelper = Mage::helper('popov_magmi/specialChar');
        }

        return $this->specialHelper;
    }

	public function specialCharsDecode($name) {
        return $this->getSpecialHelper()->decode($name);
	}

	public function specialCharsEncode($name) {
        return $this->getSpecialHelper()->encode($name);
	}

	/**
	 * Returns 'win' for a Windows server, 'unix' for others
	 *
	 * @return string
	 */
	protected function getSystemOs() {
		$osString = php_uname('s');
		if (strpos(strtoupper($osString), 'WIN') !== false) {
			return 'win';
		} else {
			return 'unix';
		}
	}

	public function unsetCmdFlag($flag) {
		unset($this->cmdFlags[$flag]);

		return $this;
	}

	public function setCmdFlag($flag, $value) {
		$this->cmdFlags[$flag] = $value;

		return $this;
	}

	public function setCmdFlags(array $flags) {
		$this->cmdFlags = $flags;

		return $this;
	}

	public function getCmdArgs() {
		$flags = array();
		foreach ($this->cmdFlags as $flag => $value) {
			$flags[] = sprintf('-%s="%s"', $flag, $value);
		}

		return implode(' ', $flags);
	}

	public function import() {
	    $config = $this->getGeneralConfig();
		//$magmiCli = sprintf('%s/magmi-web/cli/magmi.cli.php', Mage::getBaseDir('base'));
		$magmiCli = $this->getAbsolutePath($config['magmi_cli_pathname']);
		$arguments = $this->getCmdArgs();

		switch ($this->getSystemOs()) {
			case 'unix' :
				$cmd = escapeshellcmd(sprintf('%s %s %s', $this->getInterpreter(), $magmiCli, $arguments));
				break;
			case 'win' :
				$cmd = escapeshellcmd(sprintf('"%s" "%s" %s', $this->getInterpreter(), $magmiCli, $arguments));
				break;
		}

		$runStatus = $this->isRealMode() ? system($cmd) : $this->log($cmd);

		return $runStatus;
	}

    /**
	 * Fix: Home Page – 404 Not Found after Magmi import end etc.
	 */
	protected function fix404Error() {
		//$connectionRead = Mage::getSingleton('core/resource')->getConnection('core_read');
		$connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');

		//$attribute = $connectionRead->query("SELECT `core_url_rewrite`.* FROM `core_url_rewrite` WHERE (`request_path` IN ('/', ''))")->fetch();
		//if ($attribute['url_rewrite_id']) {
		//	$connectionWrite->query("DELETE FROM `core_url_rewrite` WHERE `core_url_rewrite`.`url_rewrite_id` = '{$attribute['url_rewrite_id']}'");
		//}
		$connectionWrite->query("DELETE FROM `core_url_rewrite` WHERE (`core_url_rewrite`.`request_path` IN ('/', ''))");
	}

    public function log($message, $level = Zend_Log::INFO)
    {
        Mage::log($message, $level, $this->logFile);
    }

    /**
	 * Recursively move files from one directory to another
	 *
	 * @param String $src - Source of files being moved
	 * @param String $dest - Destination of files being moved
	 */
	public function rmove($src, $dest){
		// If source is not a directory then stop processing
		if(!is_dir($src)) return false;

		// If the destination directory does not exist create it
		if(!is_dir($dest)) {
			if(!mkdir($dest)) {
				// If the destination directory could not be created stop processing
				return false;
			}
		}

		// Open the source directory to read in files
		$i = new DirectoryIterator($src);
		foreach($i as $f) {
			if($f->isFile()) {
				rename($f->getRealPath(), "$dest/" . $f->getFilename());
			} else if(!$f->isDot() && $f->isDir()) {
				$this->rmove($f->getRealPath(), "$dest/$f");
				unlink($f->getRealPath());
			}
		}
		unlink($src);
	}

	public function backupDb() {
		if ($this->backupDb) {
			$options = Mage::getConfig()->getNode("global/resources/default_setup/connection");
			$backupPath = Mage::getBaseDir('var') . '/' . 'backups';
			$this->getIo()->mkdir($backupPath);

			switch ($this->getSystemOs()) {
				case 'unix' :
					$cmd = sprintf('mysqldump -h%s -u%s -p%s %s | gzip -9 > %s/%s-%s.sql.gz', $options->host, $options->username, $options->password, $options->dbname, $backupPath, $options->dbname, date('YmdHi'));
					break;
				case 'win' :
					$cmd = sprintf('mysqldump -h%s -u%s -p%s %s > %s/%s-%s.sql', $options->host, $options->username, $options->password, $options->dbname, $backupPath, $options->dbname, date('YmdHi'));
					break;
			}

			$output = $this->exec($cmd);

			$this->checkIsOutputError($output);
		}
	}

	public function exec($cmd) {
		$out = shell_exec("{$cmd} 2> output");
		$output = $out ? $out : join('', file('output'));

		return $output;
	}

	protected function checkIsOutputError($output, $throw = true) {
		$isError = false;

		if (strlen($error = trim($output)) && $throw) {
			Mage::throwException($error);
		} elseif ($error) {
			$isError = true;
		}

		return $isError;
	}

	public function reindex($type = '') {
		$indexes = array();
		if (!$type) {
			$indexes = array_merge($this->indexes['lite'], $this->indexes['long']);
		} elseif (isset($this->indexes[$type])) {
			$indexes = $this->indexes[$type];
		} else {
			Mage::throwException(sprintf('Indexes type %s not found', $type));
		}
		$indexer = sprintf('%s %s/shell/indexer.php --reindex ', $this->getInterpreter(), Mage::getBaseDir());

		$cmds = array();
		foreach ($indexes as $index) {
			$cmds[] = $indexer . $index;
		}

		$cmd = implode(' && ', $cmds);
		$this->exec($cmd);
	}

	protected function clearCache() {
		$cmdMage = sprintf('rm -Rf %s/var/cache/', Mage::getBaseDir());
		//$cmdVarnish = 'varnishadm "ban.url ."'; //@FIXME: clear full cache
		$this->exec($cmdMage);
		//$this->exec($cmdVarnish);
	}

	public function __destruct() {
		$this->getIo()->streamClose();
	}
}