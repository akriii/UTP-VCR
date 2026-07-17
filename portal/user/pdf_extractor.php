<?php
require '../../vendor/autoload.php';

class BankStatementExtractor {
    private $parser;
    
    public function __construct() {
        $this->parser = new \Smalot\PdfParser\Parser();
    }
    
    public function extractBankInfo($pdfPath) {
        try {
            // Parse the PDF
            $pdf = $this->parser->parseFile($pdfPath);
            $text = $pdf->getText();
            
            // Extract information using patterns
            $accountNumber = $this->extractAccountNumber($text);
            $bankName = $this->extractBankName($text);
            $statementDate = $this->extractStatementDate($text);
            
            return [
                'success' => true,
                'account_number' => $accountNumber,
                'bank_name' => $bankName,
                'statement_date' => $statementDate,
                'raw_text' => $text // For debugging
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to parse PDF: ' . $e->getMessage()
            ];
        }
    }
    
   private function extractAccountNumber($text)
    {
        // 1. Pre-process: Normalize text to make matching easier
        // Replace multiple spaces/newlines with single space
        $cleanText = preg_replace('/\s+/', ' ', $text);
        
        // 2. Define high-confidence patterns (Label + Number)
        // Matches: "Account No: 123456", "Acc No 1234-5678", "A/C No. 123"
        $labeledPatterns = [
            '/Account\s*(?:No|Number|ID|Code)?\s*[:\.]?\s*([0-9\-\s]{8,25})/i',
            '/Acc\s*(?:No|Number)?\s*[:\.]?\s*([0-9\-\s]{8,25})/i',
            '/A\/C\s*(?:No|Number)?\s*[:\.]?\s*([0-9\-\s]{8,25})/i',
            '/Savings\s*[:\.]?\s*([0-9\-\s]{8,25})/i',
            '/Current\s*[:\.]?\s*([0-9\-\s]{8,25})/i',
        ];

        foreach ($labeledPatterns as $pattern) {
            if (preg_match_all($pattern, $cleanText, $matches)) {
                // We might find multiple matches (e.g. header and footer). 
                // Check each match.
                foreach ($matches[1] as $match) {
                    $cleanedNumber = $this->validateAndCleanAccountNumber($match);
                    if ($cleanedNumber) {
                        return $cleanedNumber;
                    }
                }
            }
        }

        // 3. Fallback: If no labels found, look for standalone Malaysian Bank formats
        // This is riskier, so we are stricter with validation
        
        // Maybank usually 12 digits, CIMB 10/14, Public 10, RHB 14, HLB 11
        // We look for groupings of digits that might have spaces/dashes
        $fallbackPattern = '/\b(\d{2,4}[\s\-]?\d{3,6}[\s\-]?\d{3,6})\b/';
        
        if (preg_match_all($fallbackPattern, $cleanText, $matches)) {
            foreach ($matches[1] as $match) {
                $cleanedNumber = $this->validateAndCleanAccountNumber($match);
                if ($cleanedNumber) {
                    return $cleanedNumber;
                }
            }
        }

        return null;
    }
    private function validateAndCleanAccountNumber($rawString)
    {
        // Remove everything except numbers
        $number = preg_replace('/[^0-9]/', '', $rawString);

        // FILTER 1: Length Check
        // Most Malaysian bank accounts are between 10 and 14 digits. 
        // We allow 9-17 to be safe (some older formats or credit card numbers on statement).
        $length = strlen($number);
        if ($length < 9 || $length > 17) {
            return null;
        }

        // FILTER 2: Hotline Exclusion
        // Many statements define "Call 1-300-88-6688". We must NOT catch this.
        $hotlinePrefixes = ['1300', '1800', '03']; // 03 is common landline area code
        foreach ($hotlinePrefixes as $prefix) {
            if (strpos($number, $prefix) === 0) {
                return null;
            }
        }

        // FILTER 3: Year Exclusion
        // If it looks like a year (2024, 2025) or date sequence (20251020), ignore it.
        // Simple check: if it starts with 202 and is short, ignore.
        if (strlen($number) == 4 && (strpos($number, '202') === 0 || strpos($number, '199') === 0)) {
            return null;
        }

        return $number;
    }

    
   private function extractBankName($text) {
    // Don't even try if we don't have good text
    if (strlen($text) < 100) {
        error_log("Text too short for reliable bank extraction");
        return null;
    }
    
    // Convert text to uppercase for consistent matching
    $text = strtoupper($text);
    
    // Only match when we see clear bank-related context
    $bankPatterns = [
        'Maybank' => [
            '/MAYBANK/i',
            '/MAYBANK2U/i', 
            '/MBBEMYKL/', // SWIFT code
            '/BANK\s+MAYBANK/i'
        ],
        'CIMB' => [
            '/\bCIMB\s+BANK/i',
            '/CIMB\s+CLICKS/i',
            '/CIBBMYKL/', // SWIFT code
            '/BANK\s+CIMB/i'
        ],
        'Public Bank' => [
            '/PUBLIC\s+BANK/i',
            '/PBBEMYKL/', // SWIFT code
            '/PUBLICBANK/i'
        ],
        'RHB Bank' => [
            '/\bRHB\s+BANK/i',
            '/RHBBMYKL/', // SWIFT code
            '/BANK\s+RHB/i'
        ],
        'Hong Leong Bank' => [
            '/HONG\s+LEONG\s+BANK/i',
            '/HLBBMYKL/', // SWIFT code
            '/HONGLEONG/i'
        ]
    ];
    
    foreach ($bankPatterns as $bankName => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                error_log("Reliable bank match: $bankName with pattern: $pattern");
                return $bankName;
            }
        }
    }
    
    error_log("No reliable bank name found - too ambiguous");
    return null;
}
    
    private function extractStatementDate($text) {
    // First, let's debug what text we're working with
    error_log("=== PDF TEXT FOR DATE EXTRACTION ===");
    error_log(substr($text, 0, 1000)); // Log first 1000 chars to see what we're working with
    
    // Simple and flexible patterns - look for dates in common formats
    $patterns = [
        // Look for dates after common phrases (more flexible)
        '/statement date[^\d]{0,20}(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
        '/date[^\d]{0,20}(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
        '/as at[^\d]{0,20}(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
        
        // Just look for any date in the format dd/mm/yy or dd/mm/yyyy
        '/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/',
        
        // Look for dates near the word "statement"
        '/statement[^\d]{0,30}(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $dateString = trim($matches[1]);
            error_log("Found date string: " . $dateString);
            
            // Try to parse the date
            $parsedDate = $this->parseDateString($dateString);
            if ($parsedDate) {
                return $parsedDate;
            }
        }
    }
    
    error_log("No date found in PDF text");
    return null;
}

private function parseDateString($dateString) {
    // Remove any extra spaces
    $dateString = trim($dateString);
    
    // Try different date formats
    $formats = [
        'd/m/y',  // 30/06/25 → 2025-06-30
        'd/m/Y',  // 30/06/2025 → 2025-06-30
        'd-m-y',  // 30-06-25 → 2025-06-30
        'd-m-Y',  // 30-06-2025 → 2025-06-30
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date) {
            $year = $date->format('Y');
            $current_year = date('Y');
            
            // Basic validation - should be reasonable year
            if ($year >= 2020 && $year <= ($current_year + 5)) {
                error_log("Successfully parsed date: " . $date->format('Y-m-d'));
                return $date->format('Y-m-d');
            } else {
                error_log("Unreasonable year detected: " . $year);
            }
        }
    }
    
    error_log("Failed to parse date string: " . $dateString);
    return null;
}
        
    
    }

?>