<?php

namespace Plugin\axytos_payment\helpers;

class CSVHelper
{
    /**
     * Validates file type and extension before parsing
     */
    private function validateFile(string $filePath, ?string $originalFilename = null): void
    {
        // Check if file exists and is readable first
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File does not exist: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("File is not readable: {$filePath}");
        }

        // Check MIME type first (more reliable for temp files)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        $validMimeTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mimeType, $validMimeTypes)) {
            throw new \InvalidArgumentException("Invalid file type. Expected CSV file, got MIME type: {$mimeType}");
        }

        // Check file extension using original filename if provided, otherwise temp file
        $filenameToCheck = $originalFilename ?? $filePath;
        $extension = strtolower(pathinfo($filenameToCheck, PATHINFO_EXTENSION));
        if (!empty($extension) && $extension !== 'csv') {
            throw new \InvalidArgumentException("Invalid file extension. Expected .csv, got .{$extension}");
        }
    }

    /**
     * Validates CSV structure without full parsing
     * Checks file type, header format, and required fields
     */
    public function validateCsvStructure(string $filePath, string $delimiter = ';', string $enclosure = '"', string $escape = '\\', ?string $originalFilename = null): bool
    {
        // Validate file before processing
        $this->validateFile($filePath, $originalFilename);

        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape);
            if ($header === false || empty($header)) {
                fclose($handle);
                throw new \InvalidArgumentException("CSV file is empty or has invalid header");
            }

            // Check if required fields exist in header (case-insensitive)
            $normalizedHeader = array_map('strtolower', array_map('trim', $header));
            $requiredFields = ['rechnungsnummer', 'externe bestellnummer'];

            foreach ($requiredFields as $required) {
                if (!in_array($required, $normalizedHeader)) {
                    fclose($handle);
                    throw new \InvalidArgumentException("Missing required field in CSV header: '{$required}'. Expected fields: " . implode(', ', $requiredFields));
                }
            }

            fclose($handle);
            return true;
        } else {
            throw new \InvalidArgumentException("Unable to open CSV file: {$filePath}");
        }
    }

    public function parseCsv(string $filePath, string $delimiter = ';', string $enclosure = '"', string $escape = '\\', ?string $originalFilename = null): array
    {
        // Validate file before processing
        $this->validateFile($filePath, $originalFilename);
        $data = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape);
            if ($header === false || empty($header)) {
                fclose($handle);
                throw new \InvalidArgumentException("CSV file is empty or has invalid header");
            }

            $expectedFieldCount = count($header);

            // Clean and normalize header names
            $header = array_map(function($field) {
                return trim(strtolower($field));
            }, $header);

            $rowCount = 0;
            while (($row = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape)) !== false) {
                $rowCount++;
                if (!empty($row)) {
                    // Validate field count matches header
                    if (count($row) !== $expectedFieldCount) {
                        fclose($handle);
                        throw new \InvalidArgumentException(
                            "CSV dialect mismatch at row {$rowCount}: expected {$expectedFieldCount} fields, got " . count($row) .
                            ". Check delimiter (expected: '{$delimiter}') and enclosure (expected: '{$enclosure}') settings."
                        );
                    }

                    $rowData = [];
                    foreach ($header as $index => $fieldName) {
                        $rowData[$fieldName] = isset($row[$index]) ? trim($row[$index]) : '';
                    }
                    $data[] = $rowData;
                }
            }
            fclose($handle);
        } else {
            throw new \InvalidArgumentException("Unable to open CSV file: {$filePath}");
        }
        return $data;
    }
}