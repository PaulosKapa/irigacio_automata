<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Database connection
    $host = 'localhost';
    $dbname = "irigacio_automata";
    $username = "user";
    $password = "1813";

    // Create connection
    $conn = mysqli_connect($host, $username, $password, $dbname);

    // Check connection
    if (mysqli_connect_errno()) {
        die("Connection failed: " . mysqli_connect_error());
    }
    //to do: check if the sensor already exists; If it does ask the user if this is a mistake or if they want to move the sensor in another plant
     // 1. First insert the new sensor
    $sensor_sql = "INSERT INTO sensors (sensor_value) VALUES (?)";
    $stmt = mysqli_prepare($conn, $sensor_sql);
    mysqli_stmt_bind_param($stmt, "s", $_POST['sensor']); // Assuming you added sensor_name input
    mysqli_stmt_execute($stmt);
    $sensor_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Insert into plants table
    $sql = "INSERT INTO plants (name, min_humidity, max_humidity) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "sdd", $_POST['name'], $_POST['min'], $_POST['max']);

    if (!mysqli_stmt_execute($stmt)) {
        die("Execute failed: " . mysqli_error($conn));
    }

    $plant_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Get sensor ID 
    $sql = "SELECT sensor_id FROM `sensors` WHERE sensor_value = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_POST['sensor']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $sensor_id = $row['sensor_id'];
    } else {
        http_response_code(404);
        die(json_encode(["status" => "error", "message" => "Sensor not found"]));
    }
    mysqli_stmt_close($stmt);
    // Insert into plant_sensor_groups
    $sql = "INSERT INTO plant_sensor_groups (pid, sid) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "ii", $plant_id, $sensor_id);

    if (!mysqli_stmt_execute($stmt)) {
        die("Execute failed: " . mysqli_error($conn));
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    echo "Record saved successfully! Plant ID: $plant_id";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Plant | Smart Garden</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Your original CSS remains unchanged */
        :root {
            --primary: #4CAF50;
            --primary-dark: #388E3C;
            --background: #f5f7fa;
            --card: #ffffff;
            --text: #333333;
            --border: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background);
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-weight: 500;
            font-size: 24px;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 20px;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .plant-icon {
            text-align: center;
            margin-bottom: 20px;
            font-size: 48px;
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 20px auto;
            }
            
            .header {
                padding: 20px;
            }
            
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Add New Plant</h1>
        </div>
        
        <div class="form-container">
            <div class="plant-icon">
                🌱
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="name">Plant Name</label>
                    <input type="text" id="name" placeholder="e.g., Basil, Tomato, Rose" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="min">Minimum Ideal Humidity (%)</label>
                    <input type="number" id="min" placeholder="40" name="min" min="0" max="100" required>
                </div>
                
                <div class="form-group">
                    <label for="max">Maximum Ideal Humidity (%)</label>
                    <input type="number" id="max" placeholder="80" name="max" min="0" max="100" required>
                </div>
                
                <div class="form-group">
                    <label for="sensor">Associated Sensor ID</label>
                    <input type="number" id="sensor" placeholder="Enter number of the sensor" name="sensor" min="1" required>
                
                <button type="submit" class="btn">Add Plant</button>
            </form>
        </div>
    </div>
</body>
</html>