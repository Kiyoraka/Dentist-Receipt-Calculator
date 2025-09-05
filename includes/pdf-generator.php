<?php
/**
 * PDF Generator Helper
 * Provides multiple PDF generation options for the dental system
 * 
 * Supports:
 * - Browser print-to-PDF (immediate solution)
 * - TCPDF integration (when available)
 * - DOMPDF integration (when available)
 * - MPDF integration (when available)
 */

class PDFGenerator {
    
    private $html_content;
    private $filename;
    private $paper_size = 'A4';
    private $orientation = 'portrait';
    
    public function __construct($html_content, $filename = null) {
        $this->html_content = $html_content;
        $this->filename = $filename ?: 'dental_report_' . date('Y-m-d_H-i-s') . '.pdf';
    }
    
    public function setPaperSize($size) {
        $this->paper_size = $size;
        return $this;
    }
    
    public function setOrientation($orientation) {
        $this->orientation = $orientation;
        return $this;
    }
    
    /**
     * Generate PDF using the best available method
     */
    public function generate($method = 'auto') {
        switch ($method) {
            case 'tcpdf':
                return $this->generateWithTCPDF();
            case 'dompdf':
                return $this->generateWithDOMPDF();
            case 'mpdf':
                return $this->generateWithMPDF();
            case 'browser':
                return $this->generateWithBrowser();
            case 'auto':
            default:
                return $this->generateAuto();
        }
    }
    
    /**
     * Auto-detect and use the best available PDF library
     */
    private function generateAuto() {
        // Check for TCPDF
        if (class_exists('TCPDF')) {
            return $this->generateWithTCPDF();
        }
        
        // Check for DOMPDF
        if (class_exists('Dompdf\Dompdf')) {
            return $this->generateWithDOMPDF();
        }
        
        // Check for MPDF
        if (class_exists('Mpdf\Mpdf')) {
            return $this->generateWithMPDF();
        }
        
        // Fallback to browser method
        return $this->generateWithBrowser();
    }
    
    /**
     * Generate PDF using TCPDF (if available)
     */
    private function generateWithTCPDF() {
        if (!class_exists('TCPDF')) {
            throw new Exception('TCPDF not available. Install with: composer require tecnickcom/tcpdf');
        }
        
        $pdf = new TCPDF($this->orientation, PDF_UNIT, $this->paper_size, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Dental Practice Management System');
        $pdf->SetAuthor('Dental Practice');
        $pdf->SetTitle('Dental Receipts Report');
        $pdf->SetSubject('Financial Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Add page
        $pdf->AddPage();
        
        // Write HTML
        $pdf->writeHTML($this->html_content, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output($this->filename, 'D');
        return true;
    }
    
    /**
     * Generate PDF using DOMPDF (if available)
     */
    private function generateWithDOMPDF() {
        if (!class_exists('Dompdf\Dompdf')) {
            throw new Exception('DOMPDF not available. Install with: composer require dompdf/dompdf');
        }
        
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($this->html_content);
        $dompdf->setPaper($this->paper_size, $this->orientation);
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream($this->filename, array("Attachment" => true));
        return true;
    }
    
    /**
     * Generate PDF using MPDF (if available)
     */
    private function generateWithMPDF() {
        if (!class_exists('Mpdf\Mpdf')) {
            throw new Exception('MPDF not available. Install with: composer require mpdf/mpdf');
        }
        
        $config = [
            'format' => $this->paper_size,
            'orientation' => $this->orientation === 'landscape' ? 'L' : 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ];
        
        $mpdf = new \Mpdf\Mpdf($config);
        $mpdf->WriteHTML($this->html_content);
        
        // Output PDF
        $mpdf->Output($this->filename, 'D');
        return true;
    }
    
    /**
     * Generate PDF using browser print capability
     */
    private function generateWithBrowser() {
        // Set headers for browser download
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $this->filename) . '"');
        
        // Add print-optimized CSS
        $print_css = "
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
                .page-break { page-break-before: always; }
            }
            @page {
                size: {$this->paper_size};
                margin: 20mm;
            }
        </style>
        <script>
            window.onload = function() {
                // Auto-print when page loads
                setTimeout(function() {
                    window.print();
                }, 1000);
            }
        </script>
        ";
        
        // Inject print CSS into HTML
        $html_with_print = str_replace('</head>', $print_css . '</head>', $this->html_content);
        
        echo $html_with_print;
        return true;
    }
    
    /**
     * Static method for quick PDF generation
     */
    public static function generatePDF($html_content, $filename = null, $options = []) {
        $generator = new self($html_content, $filename);
        
        if (isset($options['paper_size'])) {
            $generator->setPaperSize($options['paper_size']);
        }
        
        if (isset($options['orientation'])) {
            $generator->setOrientation($options['orientation']);
        }
        
        $method = $options['method'] ?? 'auto';
        return $generator->generate($method);
    }
    
    /**
     * Get available PDF generation methods
     */
    public static function getAvailableMethods() {
        $methods = ['browser'];
        
        if (class_exists('TCPDF')) {
            $methods[] = 'tcpdf';
        }
        
        if (class_exists('Dompdf\Dompdf')) {
            $methods[] = 'dompdf';
        }
        
        if (class_exists('Mpdf\Mpdf')) {
            $methods[] = 'mpdf';
        }
        
        return $methods;
    }
}

/**
 * Quick helper function for PDF generation
 */
function generate_pdf($html, $filename = null, $options = []) {
    return PDFGenerator::generatePDF($html, $filename, $options);
}