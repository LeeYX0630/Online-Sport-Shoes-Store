<?php
// includes/material_configs.php

// 基础价格
if (!defined('BASE_CUSTOM_PRICE')) {
    define('BASE_CUSTOM_PRICE', 829.00);
}

// 材质加价映射表 (Key 为纹理路径)
$MATERIAL_SURCHARGES = [
    '../includes/models/textures/leather_normal.jpg' => 50.00,
    '../includes/models/textures/jersey_melange_normal.jpg' => 25.00,
    '../includes/models/textures/corrugated_iron_normal.jpg' => 40.00,
    '../includes/models/textures/crepe_satin_normal.jpg' => 0.00,
    '../includes/models/textures/fabric_pattern_07_normal.jpg' => 15.00,
    '../includes/models/textures/concrete_tile_facade_normal.jpg' => 85.00
];

/**
 * 辅助函数：根据设计 JSON 计算总价
 */
function calculateCustomDesignPrice($design_json, $base_price = BASE_CUSTOM_PRICE) {
    global $MATERIAL_SURCHARGES;
    $design = json_decode($design_json, true);
    $surcharge = 0;
    
    if (isset($design['Outupper']['texture'])) {
        $tex = $design['Outupper']['texture'];
        if (isset($MATERIAL_SURCHARGES[$tex])) {
            $surcharge = $MATERIAL_SURCHARGES[$tex];
        }
    }
    return $base_price + $surcharge;
}
?>