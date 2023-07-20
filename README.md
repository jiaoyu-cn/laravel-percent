# laravel-percent

基于laravel的百分点智能媒体校对

# laravel-percent

基于laravel的网易易盾内容安全

[![image](https://img.shields.io/github/stars/jiaoyu-cn/laravel-percent)](https://github.com/jiaoyu-cn/laravel-percent/stargazers)
[![image](https://img.shields.io/github/forks/jiaoyu-cn/laravel-percent)](https://github.com/jiaoyu-cn/laravel-percent/network/members)
[![image](https://img.shields.io/github/issues/jiaoyu-cn/laravel-percent)](https://github.com/jiaoyu-cn/laravel-percent/issues)

## 安装

```shell
composer require githen/laravel-percent:~v1.0.0

# 迁移配置文件
php artisan vendor:publish --provider="Githen\LaravelPercent\Providers\PercentServiceProvider"
```

## 配置文件说明

在config/logging.php中添加yidun日志配置项

```php
'percent' => [
    'driver' => 'daily',
    'path' => storage_path('logs/percent/percent.log'),
    'level' => 'debug',
    'days' => 7,
    'permission' => 0770,
],
```        

生成`yidun.php`上传配置文件

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
    'name' => '',
    'password' => '',
    'log_channel' => 'percent',//写入日志频道，空不写入
];
```