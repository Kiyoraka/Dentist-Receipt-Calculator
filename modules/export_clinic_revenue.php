<?php
// Clinic Revenue Export - What the clinic earns (material fees, other charges, payment fees)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    // Query to get clinic revenue data
    $query = "
        SELECT 
            r.invoice_date,
            r.invoice_number,
            p.name as patient_name,
            r.services_total as material_fee,
            r.other_charges,
            r.payment_fee_amount,
            r.payment_method,
            GROUP_CONCAT(ds.service_name SEPARATOR ', ') as service_types
        FROM receipts r
        JOIN patients p ON r.patient_id = p.id
        LEFT JOIN receipt_services rs ON r.id = rs.receipt_id
        LEFT JOIN dental_services ds ON rs.service_id = ds.id
        GROUP BY r.id
        ORDER BY r.invoice_date DESC, r.created_at DESC
    ";
    
    $result = $conn->query($query);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'invoice_date' => $row['invoice_date'],
            'invoice_number' => $row['invoice_number'],
            'patient_name' => $row['patient_name'],
            'service_types' => $row['service_types'] ?: 'No services recorded',
            'material_fee' => number_format($row['material_fee'], 2, '.', ''),
            'other_charges' => number_format($row['other_charges'], 2, '.', ''),
            'payment_fee_amount' => number_format($row['payment_fee_amount'], 2, '.', ''),
            'payment_method' => $row['payment_method']
        ];
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch clinic revenue data']);
}
?>