<?php

namespace Plugin\axytos_payment\helpers;

class CSVHelper
{
    public function parseCsv(string $filePath, string $delimiter = ';', string $enclosure = '"', string $escape = '\\'): array
    {
        $data = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape);
            if ($header === false || empty($header)) {
                fclose($handle);
                return [];
            }

            // Clean and normalize header names
            $header = array_map(function($field) {
                return trim(strtolower($field));
            }, $header);

            while (($row = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape)) !== false) {
                if (!empty($row)) {
                    $rowData = [];
                    foreach ($header as $index => $fieldName) {
                        $rowData[$fieldName] = isset($row[$index]) ? trim($row[$index]) : '';
                    }
                    $data[] = $rowData;
                }
            }
            fclose($handle);
        }
        return $data;
    }
}