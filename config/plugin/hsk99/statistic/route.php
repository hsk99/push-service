<?php

use Webman\Route;
use Webman\Http\Response;
use Hsk99\WebmanStatistic\Statistic;

$oldFallback = Route::getFallback();
Route::fallback(function ($request) use (&$oldFallback) {
    $startTime = microtime(true);
    $project   = config('plugin.hsk99.statistic.app.project');
    $ip        = $request->getRealIp($safe_mode = true);
    $transfer  = $request->path();

    if ($oldFallback instanceof \Closure) {
        $response = $oldFallback($request);
    } else {
        $response = new Response(404, [], \file_get_contents(public_path() . '/404.html'));
    }

    $finishTime = microtime(true);
    $costTime   = $finishTime - $startTime;

    switch (true) {
        case method_exists($response, 'exception') && $exception = $response->exception():
            \Hsk99\WebmanStatistic\Statistic::exception($exception);
            $body = (string)$exception;
            break;
        case 'application/json' === strtolower($response->getHeader('Content-Type')):
            $body = json_decode($response->rawBody(), true);
            break;
        default:
            $body = 'Non Json data';
            break;
    }

    $code    = $response->getStatusCode();
    $success = $code < 400;
    $details = [
        'time'            => date('Y-m-d H:i:s.', (int)$startTime) . substr($startTime, 11),   // 请求时间（包含毫秒时间）
        'run_time'        => $costTime,                                                        // 运行时长
        'ip'              => $request->getRealIp($safe_mode = true) ?? '',                     // 请求客户端IP
        'url'             => $request->fullUrl() ?? '',                                        // 请求URL
        'method'          => $request->method() ?? '',                                         // 请求方法
        'request_param'   => $request->all() ?? [],                                            // 请求参数
        'request_header'  => $request->header() ?? [],                                         // 请求头
        'cookie'          => $request->cookie() ?? [],                                         // 请求cookie
        'session'         => $request->session()->all() ?? [],                                 // 请求session
        'response_code'   => $response->getStatusCode() ?? '',                                 // 响应码
        'response_header' => $response->getHeaders() ?? [],                                    // 响应头
        'response_body'   => $body,                                                            // 响应数据
    ];

    Statistic::$transfer .= json_encode([
        'time'     => date('Y-m-d H:i:s.', (int)$startTime) . substr($startTime, 11),
        'project'  => $project,
        'ip'       => $ip,
        'transfer' => $transfer,
        'costTime' => $costTime,
        'success'  => $success ? 1 : 0,
        'code'     => $code,
        'details'  => json_encode($details, 320),
    ], 320) . "\n";

    if (strlen(\Hsk99\WebmanStatistic\Statistic::$transfer) > 1024 * 1024) {
        \Hsk99\WebmanStatistic\Statistic::report();
    }

    return $response;
});
