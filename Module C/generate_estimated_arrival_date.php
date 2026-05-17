<?php
// generate_estimated_arrival_date.php

// 获取当前时间的小时数 (24小时制，0 到 23)
// 前提是你在 db_connection.php 已经设置了时区为 Asia/Kuala_Lumpur
$current_hour = (int)date('H');

// 1. 根据当前时间，决定基础的运送天数
if ($current_hour < 15) {
    // 下午 3 点 (15:00) 之前：西马 2 天，东马 4 天
    $days_west = 2;
    $days_east = 4;
} else {
    // 下午 3 点之后 (包含 3点)：西马 3 天，东马 5 天
    $days_west = 3;
    $days_east = 5;
}

// 预设为西马的天数
$delivery_days = $days_west; 

// 2. 去数据库查询该订单的州属
// ⚠️ 注意：如果你的州属字段不是 Shipping_State，请在这里修改
$state_query = "SELECT Shipping_State FROM `order` WHERE Order_Id = '$order_id'";
$state_result = mysqli_query($conn, $state_query);

if ($state_result && mysqli_num_rows($state_result) > 0) {
    $row = mysqli_fetch_assoc($state_result);
    // 把拿到的州属名字变成小写，方便比对
    $state_name = strtolower($row['Shipping_State'] ?? '');

    // 3. 判断是否为东马 (Sabah, Sarawak, Labuan)
    if (strpos($state_name, 'sabah') !== false || 
        strpos($state_name, 'sarawak') !== false || 
        strpos($state_name, 'labuan') !== false) {
        
        // 如果是东马，就换成东马的天数
        $delivery_days = $days_east; 
    }
}

// 4. 开始计算预计时间
$current_timestamp = time();
$estimated_timestamp = strtotime("+$delivery_days days", $current_timestamp);

// 5. 智能跳过星期日 (0 表示星期日)
// 快递公司礼拜天通常不派件
if (date('w', $estimated_timestamp) == 0) {
    // 如果预计到达的那天刚好是星期日，自动顺延一天到星期一
    $estimated_timestamp = strtotime("+1 day", $estimated_timestamp);
}

// 6. 最终生成 Y-m-d 格式的日期，交给外面的代码存入数据库
$estimated_arrival_date = date('Y-m-d', $estimated_timestamp);
?>