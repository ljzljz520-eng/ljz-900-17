<?php
use think\facade\Route;
return [
    // 开启路由完全匹配。
    // ThinkPHP 默认关闭（false），路由正则无结尾锚点，导致先注册的
    // POST /api/users 会前缀匹配并吞掉 POST /api/users/:id/toggle-active、
    // POST /api/users/:id/reset-token（被错误路由到 create，报“缺少 name”），
    // GET /api/users 也会遮蔽 GET /api/users/:id。
    'route_complete_match' => true,
];
