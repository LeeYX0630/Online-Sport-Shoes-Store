<?php
require_once('includes\stripe\vendor\autoload.php'); // 或者指向你手动下载的 init.php

// 填入你刚才拿到的 Secret key
\Stripe\Stripe::setApiKey('sk_test_51TNG2VIIq5XEWq61djZL5tJes8WuO9zCeX2uDJbHFHp9tLdDRv1VkyfrrmfLGjZnBCgI2wdTJ1MZDqHk5IxaJqEZ00zbqXzUa5');

try {
    // 尝试获取你的账户信息
    $account = \Stripe\Account::retrieve();
    echo "成功！你的 Stripe 账户 ID 是: " . $account->id;
    echo "<br>当前结算货币为: " . $account->default_currency;
} catch (Exception $e) {
    echo "失败: " . $e->getMessage();
}
?>