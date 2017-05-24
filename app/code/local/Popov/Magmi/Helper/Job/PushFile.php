<?php

/**
 * Push files to ftp server
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.03.2017 18:16
 */
class Popov_Magmi_Helper_Job_PushFile extends Mage_Core_Helper_Abstract
{
    /** @var Varien_Io_Ftp */
    protected $ftp;

    /** @var Popov_Magmi_Import_Product */
    protected $importer;

    protected $importDir = 'import';

    public function run($importer)
    {
        $this->importer = $importer;
        /** @var Popov_Magmi_Import_Product $productImport */
        $this->pushFiles();

        // close ftp for escape timeout limit for next iteration
        $this->getFtpClient()->close();
        $this->ftp = null;
    }

    public function pushFiles()
    {
        $this->pushReconFile();
        $this->moveImportFile();
    }

    public function getImporter()
    {
        return $this->importer;
    }

    public function getFtpClient()
    {
        if (!$this->ftp) {
            $config = (array) Mage::getConfig()->getNode('global/resources/ftp/agere_magmi_import/connection');

            $this->ftp = new Varien_Io_Ftp();
            $this->ftp->open($config);
        }

        return $this->ftp;
    }

    public function pushReconFile()
    {
        $importer = $this->getImporter();
        $ftp = $this->getFtpClient();

        $code = $importer->getCurrentWebsiteCode();
        $filePath = '/' . $this->importDir . '/' . $code . '/' . $importer->getReconXmlFileName();
        $fileLocal = fopen(Mage::getBaseDir('var') . $filePath, 'r');

        ($isWrote = $ftp->write($filePath,  $fileLocal))
            ? $importer->log(sprintf('Reconciliation file %s successfully wrote', $filePath), Zend_Log::INFO)
            : $importer->log(sprintf('Cannot copy content to remote reconciliation file %s', $filePath), Zend_Log::WARN);
    }

    public function moveImportFile()
    {
        $importer = $this->getImporter();
        $ftp = $this->getFtpClient();
        $code = $importer->getCurrentWebsiteCode();

        $isCd = $ftp->cd($path = '/' . $this->importDir . '/' . $code);
        $list = $ftp->ls();
        foreach ($list as $file) {
            $info = new SplFileInfo($file['id']);
            if ('csv' === $info->getExtension()) {
                $filePath = $file['id'];
                $backupPath = $info->getPath() . '/backup/' . $info->getFilename();
                ($isReserved = $ftp->mv($filePath, $backupPath))
                    ? $importer->log(sprintf('File %s successfully moved to backup', $filePath), Zend_Log::INFO)
                    : $importer->log(sprintf('Cannot backup remote file %s', $filePath), Zend_Log::WARN);
            }
        }
    }

    public function __destruct()
    {
        $this->getFtpClient()->close();
    }
}