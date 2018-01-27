<?php

/**
 * Push files to ftp server
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 13.03.2017 18:16
 */
class Popov_Magmi_Helper_Job_FileNotFound extends Mage_Core_Helper_Abstract
{
    /** @var Popov_Magmi_Helper_Mail */
    protected $mailClient;

    /** @var Popov_Magmi_Import_Product */
    protected $importer;

    protected $collected = [];

    public function run($importer)
    {
        $this->importer = $importer;
        /** @var Popov_Magmi_Import_Product $productImport */
        //$this->collect();
        $this->sendNotification();
    }

    public function getImporter()
    {
        return $this->importer;
    }

    public function getMailClient()
    {
        if (!$this->mailClient) {
            $this->mailClient = Mage::helper('popov_magmi/mail');
        }

        return $this->mailClient;
    }

    /**
     * Collect stores for which send notification
     */
    public function collect()
    {
        $importer = $this->getImporter();

        if ($exist = $importer->getImportFile()->isFile()) {
            $this->collected[] = $importer->getCurrentWebsiteCode();
        }
    }

    public function getCollected()
    {
        return $this->collected;
    }

    public function sendNotification()
    {
        $importer = $this->getImporter();
        /*if ($importer->isCurrentStoreLast() && count($this->getCollected())) {
            $mailClient = $this->getMailClient();
            $mailClient->sendNotification($this->getCollected());
        }*/

        if (!($exist = $importer->getImportFile()->isFile())) {
            $website = Mage::app()->getWebsite($importer->getCurrentWebsiteCode());
            $mailClient = $this->getMailClient();
            $mailClient->sendNotification($website);

            $importer->log(
                sprintf('Import file not found for website %s. Notification message has been sent', $website->getCode()),
                Zend_Log::WARN
            );
        }
    }
}