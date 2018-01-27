<?php

/**
 * Enter description here...
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 28.05.2017 22:31
 */
class Popov_Magmi_Test_Fake_CsvFake
{
    protected $data = [];

    public function getData()
    {
        return $this->data;
    }

    public function streamWriteCsv($row)
    {
        $this->data[] = $row;
    }

    public function streamClose()
    {
        return true;
    }
}