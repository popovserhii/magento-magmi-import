<?php
/**
 * Magmi importer
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 06.12.13 18:27
 */

abstract class Popov_Magmi_Import_Abstract {

	/**
	 * @var SplFileInfo
	 */
	protected $importFile;

	protected $logFile = 'magmi-import.log';

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

	/**
	 * Special chars maps for replace
	 *
	 * @var array
	 */
	protected $specialCharsMap = array(
		'&Slash&' => '/',
		'&Backslash&' => '\\',
		'&Asterisk&' => '*',
		'&Pipe&' => '|',
		'&Colon&' => ':',
		'&quot&' => '"',
		'&lt&' => '<',
		'&gt&' => '>',
		'&Questionmark&' => '?',
	);

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

	protected $websites = [
		'base' => [],
		'oodji' => [],
	];

	protected $tasks = [];


	public function preImport()
    {
        $this->runJobs('pre');
    }

	public function postImport()
    {
        $this->runJobs('post');
    }

	final public function run()
    {
		foreach ($this->websites as $name => $attrs) {
			$this->currentWebsite = $name;
			try {
                $this->log(sprintf('Import %s: run prepare jobs for website: ' . $name, $this->getImportCode()));
				if ($this->preImport()) {
					$this->backupDb();

                    $this->log(sprintf('Import %s: run import for website: ' . $name, $this->getImportCode()));
                    $this->import();

                    $this->log(sprintf('Import %s: run post jobs for website: ' . $name, $this->getImportCode()));
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
            $tasks = (array) Mage::getConfig()->getNode('agere_magmi/jobs/' . $typeNode);
            foreach ($tasks as $name => $task) {
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

        return $code ?: $code = strtolower(array_pop(explode('_', get_class($this))));
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
		//Zend_Debug::dump($binnary); die(__METHOD__);

		return $binnary;
	}

	public function specialCharsReplace($name) {
		static $mapped = array();
		if (!isset($mapped['from'])) { // optimize code
			$mapped['from'] = array_keys($this->specialCharsMap);
			$mapped['to'] = array_values($this->specialCharsMap);
		}

		return str_replace($mapped['from'], $mapped['to'], $name);
	}

	public function specialCharsEncode($name) {
		static $mapped = array();
		if (!isset($mapped['from'])) { // optimize code
			$mapped['to'] = array_keys($this->specialCharsMap);
			$mapped['from'] = array_values($this->specialCharsMap);
		}

		return str_replace($mapped['from'], $mapped['to'], $name);
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
		$magmiCli = sprintf('%s/magmi-web/cli/magmi.cli.php', Mage::getBaseDir('base'));
		$arguments = $this->getCmdArgs();

		switch ($this->getSystemOs()) {
			case 'unix' :
				$cmd = escapeshellcmd(sprintf('%s %s %s', $this->getInterpreter(), $magmiCli, $arguments));
				break;
			case 'win' :
				$cmd = escapeshellcmd(sprintf('"%s" "%s" %s', $this->getInterpreter(), $magmiCli, $arguments));
				break;
		}
		//echo $cmd; //die(__METHOD__);
		$runStatus = system($cmd);

		return $runStatus;
	}

	public function log($message, $level = Zend_Log::INFO)
    {
        Mage::log($message, $level, $this->logFile);
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
		//die($cmd);
		//echo "{$cmd} 2> output \n";
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