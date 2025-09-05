<?php
// Doctor Payments Export - What doctors should be paid
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    // Query to get doctor payment data
    $query = "
        SELECT 
            r.invoice_date,
            r.invoice_number,
            p.name as patient_name,
            r.doctor_fee,
            r.payment_method,
            GROUP_CONCAT(ds.service_name SEPARATOR ', ') as service_types
        FROM receipts r
        JOIN patients p ON r.patient_id = p.id
        LEFT JOIN receipt_services rs ON r.id = rs.receipt_id
        LEFT JOIN dental_services ds ON rs.service_id = ds.id
        WHERE r.doctor_fee > 0
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
            'doctor_fee' => number_format($row['doctor_fee'], 2, '.', ''),
            'payment_method' => $row['payment_method']
        ];
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch doctor payment data']);
}
?>