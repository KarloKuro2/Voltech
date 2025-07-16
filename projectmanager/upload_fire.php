<?php
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) die("Connection failed: " . $con->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "webp");
    $input_name = 'file_photo';
    $upload_dir = "../uploads/project_files/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['name']) {
        $name = basename($_FILES[$input_name]['name']);
        $imageFileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($imageFileType, $extensions_arr)) {
            $filename = "fire_{$project_id}_" . time() . '.' . $imageFileType;
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_file)) {
                $sql = "UPDATE projects SET file_photo_fire = '$filename' WHERE project_id = '$project_id'";
                if ($con->query($sql)) {
                    header('Location: projects.php?upload_success=1');
                    exit();
                } else {
                    header('Location: projects.php?upload_error=' . urlencode('DB Error: ' . $con->error));
                    exit();
                }
            } else {
                echo "Move failed";
            }
        } else {
            echo "Invalid file type";
        }
    } else {
        echo "No file uploaded";
    }
} else {
    echo "Invalid request";
} 