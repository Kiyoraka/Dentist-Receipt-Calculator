<?php
/**
 * PDF Export Handler with Multiple PDF Library Support
 * Generates PDF from the export-all.php content using the best available method
 */

require_once '../config/database.php';
require_once '../includes/pdf-generator.php';

try {
    // Check if this is a PDF generation request
    if (isset($_GET['generate']) && $_GET['generate'] == '1') {
        
        // Capture the HTML content from export-all.php
        ob_start();
        $_GET['pdf'] = '1'; // Tell export-all.php we want PDF version
        include 'export-all.php';
        $html_content = ob_get_clean();
        
        // Generate filename
        $filename = 'dental_receipts_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // PDF generation options
        $options = [
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'method' => $_GET['method'] ?? 'auto' // Allow method override via URL
        ];
        
        // Generate and download PDF
        $pdf_generator = new PDFGenerator($html_content, $filename);
        $pdf_generator->setPaperSize($options['paper_size'])
                     ->setOrientation($options['orientation'])
                     ->generate($options['method']);
                     
        // If we reach here, PDF generation was successful
        exit;
        
    } else {
        // Show PDF generation options page
        $available_methods = PDFGenerator::getAvailableMethods();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>PDF Export Options - Dental Practice</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2563eb; padding-bottom: 20px; }
                .option-card { border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0; }
                .option-card:hover { border-color: #2563eb; }
                .btn { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
                .btn:hover { background: #1d4ed8; }
                .method-list { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🦷 PDF Export Options</h1>
                <p>Choose your preferred PDF generation method</p>
            </div>
            
            <div class="option-card">
                <h3>🚀 Auto-Detect (Recommended)</h3>
                <p>Automatically selects the best available PDF generation method on your system.</p>
                <a href="export-pdf.php?generate=1&method=auto" class="btn">Generate PDF (Auto)</a>
            </div>
            
            <div class="option-card">
                <h3>🖨️ Browser Print-to-PDF</h3>
                <p>Opens a print-friendly version that you can save as PDF using your browser's print function.</p>
                <a href="export-pdf.php?generate=1&method=browser" class="btn">Open Print Version</a>
            </div>
            
            <?php if (in_array('tcpdf', $available_methods)): ?>
            <div class="option-card">
                <h3>📄 TCPDF (Server-side)</h3>
                <p>Professional PDF generation using TCPDF library. Best quality and formatting.</p>
                <a href="export-pdf.php?generate=1&method=tcpdf" class="btn">Generate with TCPDF</a>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('dompdf', $available_methods)): ?>
            <div class="option-card">
                <h3>📄 DOMPDF (Server-side)</h3>
                <p>HTML-to-PDF conversion using DOMPDF library. Good CSS support.</p>
                <a href="export-pdf.php?generate=1&method=dompdf" class="btn">Generate with DOMPDF</a>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('mpdf', $available_methods)): ?>
            <div class="option-card">
                <h3>📄 MPDF (Server-side)</h3>
                <p>Lightweight PDF generation using MPDF library. Fast processing.</p>
                <a href="export-pdf.php?generate=1&method=mpdf" class="btn">Generate with MPDF</a>
            </div>
            <?php endif; ?>
            
            <div class="method-list">
                <h4>Available Methods on This System:</h4>
                <ul>
                    <?php foreach ($available_methods as $method): ?>
                        <li><?php echo ucfirst($method); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="financial.php" class="btn" style="background: #6b7280;">← Back to Financial</a>
                <a href="export-all.php" class="btn" style="background: #10b981;">View HTML Report</a>
            </div>
        </body>
        </html>
        <?php
    }
    
} catch (Exception $e) {
    echo "
    <html>
    <head><title>PDF Export Error</title></head>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px;'>
        <div style='background: #fee; border: 2px solid #fcc; border-radius: 8px; padding: 20px; text-align: center;'>
            <h2 style='color: #c00;'>PDF Export Error</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please try the browser print method instead.</p>
            <a href='export-all.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View HTML Report</a>
        </div>
    </body>
    </html>
    ";
}
?>