<?php
require_once '../includes/db_connection.php';
header('Content-Type: application/json');

$shape = isset($_GET['shape']) ? trim($_GET['shape']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : ''; // Recommended UK size from AI

// Build WHERE clause. Keep it simple and escape values directly.
$whereClauses = ["Pro_Status = 'Available'"];
if ($filter === 'wide' || $filter === 'wide-toebox') {
    $kw = $conn->real_escape_string('%wide%');
    $whereClauses[] = "(Pro_Description LIKE '{$kw}' OR Pro_Name LIKE '{$kw}')";
} elseif ($filter === 'narrow') {
    $kw = $conn->real_escape_string('%narrow%');
    $whereClauses[] = "(Pro_Description LIKE '{$kw}' OR Pro_Name LIKE '{$kw}')";
} elseif ($filter === 'stable') {
    $kw = $conn->real_escape_string('%stabil%');
    $whereClauses[] = "(Pro_Description LIKE '{$kw}' OR Pro_Name LIKE '{$kw}')";
}

$whereSql = implode(' AND ', $whereClauses);

// If size is provided, join with product_stock and filter by stock availability for that size
if (!empty($size)) {
    $escapedSize = $conn->real_escape_string($size);
    $sql = "SELECT DISTINCT p.Pro_Id, p.Pro_Name, p.Pro_Price, p.Pro_Image 
            FROM product p 
            INNER JOIN product_stock ps ON p.Pro_Id = ps.Pro_Id 
            WHERE {$whereSql} AND ps.Pro_Size = '{$escapedSize}' AND ps.Quantity > 0 
            ORDER BY p.Pro_Added_Date DESC 
            LIMIT 12";
} else {
    $sql = "SELECT Pro_Id, Pro_Name, Pro_Price, Pro_Image FROM product WHERE $whereSql ORDER BY Pro_Added_Date DESC LIMIT 12";
}

$res = $conn->query($sql);

function resolveImagePath($rawPath) {
    $rawPath = trim($rawPath);
    if (empty($rawPath)) {
        return "../images/placeholder.png";
    }

    if (stripos($rawPath, 'http') === 0 || strpos($rawPath, '../') === 0 || strpos($rawPath, '/') === 0) {
        return $rawPath;
    }

    $pathInfo = pathinfo($rawPath);
    $filename = $pathInfo['filename'] ?? '';
    if (empty($filename)) {
        return "../images/placeholder.png";
    }

    $clean_name = preg_replace('/_\d+$/', '', $filename);
    $files = glob("../uploads/{$clean_name}*.*");
    if (!empty($files)) {
        return $files[0];
    }

    if (strpos($rawPath, 'uploads/') === 0) {
        return "../{$rawPath}";
    }

    return "../uploads/{$rawPath}";
}

$out = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'pro_id' => intval($row['Pro_Id']),
            'name' => $row['Pro_Name'],
            'price' => $row['Pro_Price'],
            'image' => resolveImagePath($row['Pro_Image'])
        ];
    }
}

// Fallback: if none found, return latest products with that size
if (empty($out) && !empty($size)) {
    $escapedSize = $conn->real_escape_string($size);
    $fallback = $conn->query("SELECT DISTINCT p.Pro_Id, p.Pro_Name, p.Pro_Price, p.Pro_Image 
                              FROM product p 
                              INNER JOIN product_stock ps ON p.Pro_Id = ps.Pro_Id 
                              WHERE p.Pro_Status = 'Available' AND ps.Pro_Size = '{$escapedSize}' AND ps.Quantity > 0 
                              ORDER BY p.Pro_Added_Date DESC LIMIT 12");
    if ($fallback) {
        while ($r = $fallback->fetch_assoc()) {
            $out[] = [
                'pro_id' => intval($r['Pro_Id']),
                'name' => $r['Pro_Name'],
                'price' => $r['Pro_Price'],
                'image' => resolveImagePath($r['Pro_Image'])
            ];
        }
    }
}
// Second fallback: if still none, just get latest 12 available products
if (empty($out)) {
    $fallback2 = $conn->query("SELECT Pro_Id, Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Status = 'Available' ORDER BY Pro_Added_Date DESC LIMIT 12");
    if ($fallback2) {
        while ($r = $fallback2->fetch_assoc()) {
            $out[] = [
                'pro_id' => intval($r['Pro_Id']),
                'name' => $r['Pro_Name'],
                'price' => $r['Pro_Price'],
                'image' => resolveImagePath($r['Pro_Image'])
            ];
        }
    }
}

echo json_encode($out);
exit;

?>
