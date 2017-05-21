<?php

/**
 * Enter description here...
 *
 * @category Agere
 * @package Agere_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.03.2017 18:16
 */
class Agere_Magmi_Helper_Job_PullFile extends Mage_Core_Helper_Abstract
{
    /** @var Varien_Io_Ftp */
    protected $ftp;

    /** @var Agere_Magmi_Import_Product */
    protected $importer;

    protected $importDir = 'import';

    public function run($importer)
    {
        $this->importer = $importer;
        /** @var Agere_Magmi_Import_Product $productImport */

        $this->checkDir();
        $this->pullFiles();

        // close ftp for escape timeout limit for next iteration
        $this->getFtpClient()->close();
        $this->ftp = null;
    }

    public function getImporter()
    {
        return $this->importer;
    }

    public function ftpConnect()
    {
        $config = (array) Mage::getConfig()->getNode('global/resources/ftp/agere_magmi_import/connection');

        $this->ftp = new Varien_Io_Ftp();
        $this->ftp->open($config);
    }

    public function getFtpClient()
    {
        if (!$this->ftp) {
            $this->ftpConnect();
        }

        return $this->ftp;
    }

    public function pullFiles()
    {
        $importer = $this->getImporter();
        $ftp = $this->getFtpClient();
        $code = $importer->getCurrentWebsiteCode();

        $isCd = $ftp->cd($path = '/' . $this->importDir . '/' . $code);
        $list = $ftp->ls();

		$importer->log(sprintf('Scanning %s path... Files have been found: %s', $path, json_encode($list)), Zend_Log::DEBUG);
        foreach ($list as $file) {
            $info = new SplFileInfo($file['text']);
            if ('csv' === $info->getExtension()) {
                $filePath = $file['id'];
                $fileLocal = fopen(Mage::getBaseDir('var') . $filePath, 'w+');
                ($isCopied = $ftp->read($filePath,  $fileLocal))
                    ? $importer->log(sprintf('File %s successfully copied from remote server', $filePath), Zend_Log::INFO)
                    : $importer->log(sprintf('Cannot copy content of remote file %s', $filePath), Zend_Log::WARN);

                fclose($fileLocal);
            }
        }
    }

    public function checkDir()
    {
        $importer = $this->getImporter();

        $code = $importer->getCurrentWebsiteCode();
        // check remote directories structure
        $path = '/' . $this->importDir;
        $ftp = $this->getFtpClient();
        if (!$ftp->cd($path)) {
            $ftp->mkdir($path);
            $this->createWebsiteDirs($ftp, $path, $code);
        } elseif (!$ftp->cd($path . '/' . $code)) {
            $this->createWebsiteDirs($ftp, $path, $code);
        }
		
        // check here directories structure
        $io = $importer->getIo();
        if (!$io->fileExists($path = Mage::getBaseDir('var') . '/' . $this->importDir, false)) {
            $io->mkdir($path);
            $this->createWebsiteDirs($io, $path, $code);
        } elseif (!$io->fileExists($path . '/' . $code, false)) {
            $this->createWebsiteDirs($io, $path, $code);
        }
    }

    protected function createWebsiteDirs($io, $rootPath, $websiteCode)
    {
        $io->mkdir($rootPath . '/' . $websiteCode);
        $io->mkdir($rootPath . '/' . $websiteCode . '/backup');
    }

   /*public function __destruct()
   {
       $this->getFtpClient()->close();
   }*/
}