<?php

namespace Githen\LaravelPercent\Providers;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Storage;

/**
 * 自动注册为服务
 */
class PercentServiceProvider extends LaravelServiceProvider
{
    /**
     * 启动服务
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('percent.php')]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('jiaoyu.percent', function ($app) {
            return $this;
        });
    }

    /** 生成token信息
     * @param $config
     * @return string
     */
    private function getAuthorization($refresh = false, $subKey = 0)
    {
        if (!$config = config('percent')) {
            return $this->message('2000', "配置文件不存在");
        }
        // 子账号KEY
        if (!empty($subKey)) {
            $subAccountConfig = config('percent.sub_account.' . $subKey);
            if (!empty($subAccountConfig)) {
                $config = $subAccountConfig;
            }
        }
        // 配置校验
        foreach (['name', 'password', 'disk', 'auth_file'] as $key) {
            if (empty($config[$key])) {
                return $this->message('2000', "配置信息【" . $key . "】不能为空");
            }
        }
        $disk = $config['disk'];
        $authFile = $config['auth_file'];
        if (!$refresh && Storage::disk($disk)->has($authFile)) {
            $obj = Storage::disk($disk)->get($authFile);
            if (!empty($obj)) {
                $obj = json_decode($obj, true);
                if (!empty($obj['expire_time']) && $obj['expire_time'] - time() > 300) {
                    return $this->message('0000', "登录成功", ['token' => $obj['token']]);
                }
            }
        }
        $loginInfo = $this->login($config['name'], $config['password'], $subKey);
        if ($loginInfo['code'] == '0000') {
            $obj = [
                'token' => $loginInfo['data']['token'],
                'expire_time' => strtotime('+8 hour')
            ];
            if (Storage::disk($disk)->has($authFile)) {
                Storage::disk($disk)->put($authFile, json_encode($obj));
            } else {
                Storage::disk($disk)->put($authFile, json_encode($obj), 'public');
            }
            return $this->message('0000', "登录成功", ['token' => $obj['token']]);
        }
        return $this->message($loginInfo['code'], $loginInfo['message']);
    }

    /** 登录接口
     * @param $name
     * @param $password
     * @return array|mixed
     */
    public function login($name, $password, $subKey = 0)
    {
        $uri = "/single/login";

        $resp = $this->httpPost($uri, ['name' => $name, 'password' => $password], $subKey);

        return $this->message($resp['code'] != 200 ? '2000' : '0000', $resp['message'], $resp['data'] ?? []);
    }

    /** 校对合并接口
     * @param $method
     * @param $text
     * @return array|mixed
     */
    public function batchProofreadAll($method, $text, $subKey = 0)
    {
        $uri = "/batch/proofread/all";
        if ($method == 'all') {
            $method = 'basic_word,forbidden_word,sensitive_word,sacked_official,digit,punctuation,quotation,political_proofreader,specific_keyword';
        }
        $resp = $this->httpPost($uri, ['method' => $method, 'text' => $text], $subKey);
        if ($resp['code'] == 200) {
            $code = '0000';//检测完毕，通过
        } else if ($resp['code'] == 201) {
            $code = '2001';//校验完毕，发现错误
        } else {
            $code = '2000';//检测报错
        }
        return $this->message($code, $resp['message'], $resp['data'] ?? []);

    }

    /**
     * @param $uri
     * @param $params
     * @return array|mixed
     */
    public function httpPost($uri, $params = [], $subKey = 0)
    {
        $baseURL = 'http://freewriting.percent.cn';
        $options = ['headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
        ],
            'json' => $params,
        ];
        $isLogin = $uri != "/single/login";
        if ($isLogin) {
            $authorization = $this->getAuthorization(false, $subKey);
            if ($authorization['code'] != '0000') {
                return $this->message($authorization['code'], $authorization['message']);
            }
            $options['headers']['authorization'] = $authorization['data']['token'];
        }
        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        $httpClient = new GuzzleHttpClient([
            'timeout' => 30,
            'verify' => false,
            'handler' => $handlerStack,
        ]);
        try {
            $uri = $baseURL . $uri;
            $response = $httpClient->request('POST', $uri, $options);
            $content = json_decode($response->getBody()->getContents(), true);
            if ($isLogin && $content['code'] == 401) {
                // 401 token过期重试
                $authorization = $this->getAuthorization(true, $subKey);
                if ($authorization['code'] != '0000') {
                    return $this->message($authorization['code'], $authorization['message']);
                }
                $options['headers']['authorization'] = $authorization['data']['token'];
                $response = $httpClient->request('POST',
                    $uri,
                    $options);
                $content = json_decode($response->getBody()->getContents(), true);
            }
            return $content;
        } catch (\Exception $e) {
            return $this->message($e->getCode(), $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * 最大重试次数
     */
    const MAX_RETRIES = 3;

    /**
     * 返回一个匿名函数, 匿名函数若返回false 表示不重试，反之则表示继续重试
     * @return \Closure
     */
    private function retryDecider()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
            // 超过最大重试次数，不再重试
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }

            // 请求失败，继续重试
            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // 如果请求有响应，但是状态码不等于200，继续重试
                if ($response->getStatusCode() != 200) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * 返回一个匿名函数，该匿名函数返回下次重试的时间（毫秒）
     * @return \Closure
     */
    private function retryDelay()
    {
        return function ($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    /**
     * 封装消息
     * @param string $code
     * @param string $message
     * @param array $data
     * @return array
     */
    private function message($code, $message, $data = [])
    {
        return ['code' => $code, 'message' => $message, 'data' => $data];
    }

}
