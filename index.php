<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['s_value'], $data['humidity'], $data['temperature'], $data['sunlight'])) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

// Database connection
$host = 'localhost';
$dbname = "irigacio_automata";
$username = "user";
$password = "1813";

$conn = mysqli_connect($host, $username, $password, $dbname);
if (mysqli_connect_errno()) {
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Connection failed: " . mysqli_connect_error()]));
}

// Initialize variables
$plant_id = null;
$sensor_id = null;

// 1. Get sensor_id from sensor_value
$sql = "SELECT sensor_id FROM `sensors` WHERE sensor_value = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $data['s_value']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $sensor_id = $row['sensor_id'];
} else {
    http_response_code(404);
    die(json_encode(["status" => "error", "message" => "Sensor not found"]));
}
mysqli_stmt_close($stmt);

// 2. Get plant_id from sensor_id
$sql = "SELECT pid FROM `plant_sensor_groups` WHERE sid = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sensor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $plant_id = $row['pid'];
} else {
    http_response_code(404);
    die(json_encode(["status" => "error", "message" => "Plant not found for this sensor"]));
}
mysqli_stmt_close($stmt);

// 3. Insert sensor event
$sql = "INSERT INTO sensor_events (plant_id, reported_humidity, reported_temperature, reported_sunlight, time_of_report) VALUES (?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Prepare failed: " . mysqli_error($conn)]));
}

mysqli_stmt_bind_param($stmt, "iddd", 
    $plant_id, 
    $data['humidity'], 
    $data['temperature'],
    $data['sunlight']
);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Execute failed: " . mysqli_error($conn)]));
}
mysqli_stmt_close($stmt);

// 4. Get min and max humidity for the plant
$sql = "SELECT min_humidity, max_humidity FROM `plants` WHERE plant_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $plant_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        "min_humidity" => $row['min_humidity'],
        "max_humidity" => $row['max_humidity']
    ]);
} else {
    http_response_code(404);
    die(json_encode(["status" => "error", "message" => "Humidity range not found for plant"]));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
