<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../../config/db.php';

try {
    // Simulate student session (replace with real auth later)
    $student = [
        'name'=>'Nikhil Attri',
        'roll_number'=>'CSE101',
        'email'=>'nikhil@vnit.ac.in',
        'department'=>'CSE'
    ];

    echo json_encode(['status'=>'success','student'=>$student]);
} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}

$conn->close();
?>
