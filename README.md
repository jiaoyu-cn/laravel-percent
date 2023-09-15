# laravel-percent

基于laravel的百分点智能媒体校对

[![image](https://img.shields.io/github/stars/jiaoyu-cn/laravel-percent)](https://github.com/jiaoyu-cn/laravel-percent/stargazers)
[![image](https://img.shields.io/github/forks/jiaoyu-cn/laravel-percent)](https://github.com/jiaoyu-cn/laravel-percent/network/members)
[![image](https://img.shields.io/github/issues/jiaoyu-cn/laravel-percent)](https://github.com/jiaoyu-cn/laravel-percent/issues)

## 安装

```shell
composer require githen/laravel-percent:~v2.0.0

# 迁移配置文件
php artisan vendor:publish --provider="Githen\LaravelPercent\Providers\PercentServiceProvider"
```

## 配置文件说明

生成`percent.php`上传配置文件

```php
<?php
return [
    /*
    |--------------------------------------------------------------------------
    | 百分点配置
    |--------------------------------------------------------------------------
    |
    */
    // 登录信息
    'name' => 'admin',
    'password' => '111111',
    'disk' => 'local',
    'auth_file' => 'app/data/percent/admin.txt',// 每个账号的auth_file不能相同
    'sub_account' => [
        10 => [
            'name' => 'admin1',
            'password' => '111111',
            'disk' => 'local',
            'auth_file' => 'app/data/percent/admin1.txt', 
        ],
    ]
    
];
```
