<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../config/db.php';

try {
    $pickup_lat = $_POST['pickup_lat'] ?? null;
    $pickup_lng = $_POST['pickup_lng'] ?? null;
    $pickup_name = $_POST['pickup_name'] ?? '';
    $drop_lat = $_POST['drop_lat'] ?? null;
    $drop_lng = $_POST['drop_lng'] ?? null;
    $drop_name = $_POST['drop_name'] ?? '';

    if (!$pickup_lat || !$pickup_lng || !$drop_lat || !$drop_lng) {
        throw new Exception("Pickup and drop coordinates are required.");
    }

    $distance_km = round(sqrt(pow($pickup_lat - $drop_lat,2)+pow($pickup_lng - $drop_lng,2))*111,2);
    $eta_minutes = ceil(($distance_km/20)*60);
    $fare = round(15 + ($distance_km*10),2);

    $vehicle_info = [
        'vehicle_id'=>101,
        'vehicle_type'=>'e-Scooter',
        'vehicle_number'=>'VNIT-ER001',
        'driver_id'=>501,
        'driver_name'=>'Rahul Sharma'
    ];

    $wallet_balance = 500;
    $sufficient_balance = ($wallet_balance >= $fare);

    echo json_encode([
        'status'=>'success',
        'estimate'=>[
            'distance_km'=>$distance_km,
            'eta_minutes'=>$eta_minutes,
            'fare'=>$fare,
            'vehicle_info'=>$vehicle_info,
            'wallet_balance'=>$wallet_balance,
            'sufficient_balance'=>$sufficient_balance
        ],
        'pickup'=>['lat'=>(float)$pickup_lat,'lng'=>(float)$pickup_lng,'name'=>$pickup_name],
        'drop'=>['lat'=>(float)$drop_lat,'lng'=>(float)$drop_lng,'name'=>$drop_name]
    ], JSON_NUMERIC_CHECK);

} catch(Exception $e){
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}

$conn->close();
?>
