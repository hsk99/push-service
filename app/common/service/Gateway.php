<?php

namespace app\common\service;

use GatewayWorker\Lib\Gateway as GatewayClient;
use support\Redis;
use support\Log;

class Gateway
{
    /**
     * 订阅发布
     *
     * @author HSK
     * @date 2022-08-18 16:56:24
     *
     * @param array $channel_data
     * @param array $project_info
     *
     * @return integer
     */
    public static function publish(array $channel_data, array $project_info): int
    {
        try {
            GatewayClient::$registerAddress = str_replace(['0.0.0.0', 'text://'], ['127.0.0.1', ''], config('plugin.webman.gateway-worker.process.register.listen'));

            $channel           = $channel_data['channel'];
            $event             = $channel_data['event'];
            $data              = $channel_data['data'] ?? '{}';
            $exclude_socket_id = $channel_data['exclude_socket_id'] ?? null;

            // 组装数据
            $group   = 'project-' . $project_info['id'] . '.' . $channel;
            $message = json_encode([
                'channel' => $channel,
                'event'   => $event,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);

            // 执行推送
            GatewayClient::sendToGroup($group, $message, $exclude_socket_id);

            // 记录日志
            Log::channel('push')->debug('Server push', [
                'project' => [
                    'project'    => 'project-' . $project_info['id'],
                    'access_key' => $project_info['access_key'],
                    'secret_key' => $project_info['secret_key'],
                ],
                'message' => $message,
            ]);

            // 在线订阅数
            $subscription_count = (int)Redis::hGet('PushService:online:project-' . $project_info['id'] . ':subscription_count', $channel);
            if (!is_array($exclude_socket_id)) {
                $exclude_socket_id = array($exclude_socket_id);
            }
            $exclude_socket_id = array_filter($exclude_socket_id);
            $subscription_count = $subscription_count - count($exclude_socket_id);
            $subscription_count = $subscription_count < 0 ? 0 : $subscription_count;

            try {
                // 记录推送详细信息
                Redis::lPush('RecordCache', json_encode([
                    'day'          => date('Ymd', time()),
                    'project'      => $project_info['id'],
                    'channel'      => $channel,
                    'event'        => $event,
                    'data'         => $data,
                    'subscription' => $subscription_count,
                    'create_time'  => get_date(),
                ], 320));
            } catch (\Throwable $th) {
                \Hsk99\WebmanException\RunException::report($th);
            }

            return $subscription_count;
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return 0;
        }
    }

    /**
     * 断开连接
     *
     * @author HSK
     * @date 2022-08-19 09:54:35
     *
     * @param string|null $channel
     * @param array $project_info
     *
     * @return boolean
     */
    public static function closed(string $channel = null, array $project_info): bool
    {
        try {
            GatewayClient::$registerAddress = str_replace(['0.0.0.0', 'text://'], ['127.0.0.1', ''], config('plugin.webman.gateway-worker.process.register.listen'));

            $message = json_encode([
                'event'   => 'pusher:error',
                'data'    => [
                    'code'    => -1,
                    'message' => 'close'
                ]
            ], JSON_UNESCAPED_UNICODE);
            if (isset($channel)) {
                $group = 'project-' . $project_info['id'] . '.' . $channel;
            } else {
                $group = 'project-' . $project_info['id'];
            }

            // 执行推送
            GatewayClient::sendToGroup($group, $message);

            // 记录日志
            Log::channel('push')->debug('Server push', [
                'project' => [
                    'project'    => 'project-' . $project_info['id'],
                    'access_key' => $project_info['access_key'],
                    'secret_key' => $project_info['secret_key'],
                ],
                'message' => $message,
            ]);

            return true;
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return false;
        }
    }

    /**
     * @author HSK
     * @date 2022-08-19 13:54:28
     *
     * @param string $method
     * @param array $parameters
     *
     * @return void
     */
    public static function __callStatic($method, $parameters)
    {
        GatewayClient::$registerAddress = str_replace(['0.0.0.0', 'text://'], ['127.0.0.1', ''], config('plugin.webman.gateway-worker.process.register.listen'));

        return call_user_func_array([GatewayClient::class, $method], $parameters);
    }
}
