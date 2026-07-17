<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Hello PHPSpreadsheet!');
echo "Spreadsheet created successfully!";

//$username = "Rce66miWcVEnzfjLbSKp";
    //$password = "npe9cWNlJOz0GZhkWr5Bz5xVpuJPfHF5tOPLMs2B";
    //$channel_id = "10427";