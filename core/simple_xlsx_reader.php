<?php
/**
 * Simple XLSX Reader - قراءة ملفات Excel بدون مكتبات خارجية
 */
class SimpleXLSXReader {
    private $file;
    private $shared_strings = [];
    
    public function __construct($file) {
        $this->file = $file;
    }
    
    public function read() {
        if (!file_exists($this->file)) {
            return ['headers' => [], 'data' => []];
        }
        
        // XLSX files are ZIP archives
        $zip = new ZipArchive();
        if ($zip->open($this->file) !== true) {
            return ['headers' => [], 'data' => []];
        }
        
        // Read shared strings
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $this->parseSharedStrings($sharedStringsXml);
        }
        
        // Read sheet data
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            // Try alternate path
            $sheetXml = $zip->getFromName('xl/worksheets/sheet.xml');
        }
        
        $zip->close();
        
        if (!$sheetXml) {
            return ['headers' => [], 'data' => []];
        }
        
        return $this->parseSheet($sheetXml);
    }
    
    private function parseSharedStrings($xml) {
        $sst = simplexml_load_string($xml);
        if ($sst) {
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $this->shared_strings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $this->shared_strings[] = $text;
                }
            }
        }
    }
    
    private function parseSheet($xml) {
        $sheet = simplexml_load_string($xml);
        if (!$sheet) {
            return ['headers' => [], 'data' => []];
        }
        
        $rows = [];
        $is_first = true;
        $headers = [];
        
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $c) {
                $cellValue = '';
                $attrs = $c->attributes();
                $type = (string)$attrs['t'];
                
                if ($type === 's') {
                    // Shared string
                    $idx = intval((string)$c->v);
                    $cellValue = $this->shared_strings[$idx] ?? '';
                } else {
                    // Direct value
                    $cellValue = (string)$c->v;
                }
                
                $rowData[] = $cellValue;
            }
            
            // Check if row has data
            $has_data = false;
            foreach ($rowData as $cell) {
                if (!empty(trim($cell))) {
                    $has_data = true;
                    break;
                }
            }
            
            if (!$has_data) {
                continue;
            }
            
            if ($is_first) {
                $headers = $rowData;
                $is_first = false;
            } else {
                $rows[] = $rowData;
            }
        }
        
        return ['headers' => $headers, 'data' => $rows];
    }
}
?>
