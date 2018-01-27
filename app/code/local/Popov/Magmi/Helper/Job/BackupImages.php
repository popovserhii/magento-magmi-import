<?php

/**
 * Push files to ftp server
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 13.03.2017 18:16
 */
class Popov_Magmi_Helper_Job_BackupImages extends Mage_Core_Helper_Abstract
{
    /** @var Popov_Magmi_Import_Image */
    protected $importer;

    public function run($importer)
    {
        $this->importer = $importer;
		
        if ($this->isEnable()) {
            $this->backupImages();
        }
    }

    protected function backupImages()
    {
        $importer = $this->getImporter();
        $imageSource = $importer->getImagesSource();

        $backupDir = 'backup';
        $io = $importer->getIo();
        $io->cd($importer->getImagesSource()->getPath());
        $io->mkdir('backup');
        $src = $imageSource->getPathname();
        $dst = $imageSource->getPath() . '/' . $backupDir;
        $importer->rmove($src, $dst);
        $io->rmdir($src, true);
        $io->mkdir($src);
    }

    public function getImporter()
    {
        return $this->importer;
    }

    protected function isEnable()
    {
        $config = $this->getImporter()->getCurrentConfig();

        return isset($config['options']['backup_images']) && (int) $config['options']['backup_images'];
    }
}