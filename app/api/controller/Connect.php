<?php

namespace app\api\controller;

use think\facade\Db;
use support\Log;

class Connect
{
    /**
     * 认证
     *
     * @author HSK
     * @date 2022-08-12 15:16:34
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function auth(\support\Request $request)
    {
        try {
            $access_key = request()->input('access_key');
            $socket_id  = request()->input('socket_id');
            $channel    = request()->input('channel_name');
            $user_id    = request()->input('user_id');
            $user_info  = request()->input('user_info');

            if (empty($access_key) || empty($socket_id) || empty($channel)) {
                // 记录日志
                Log::channel('push')->info('Client authentication request failed', [
                    'request_param'  => request()->all(),
                    'response_param' => [],
                ]);
                return response('Forbidden', 403);
            }

            // 查询应用信息
            $secret_key = Db::name('project')->where('access_key', $access_key)->value('secret_key');
            if (empty($secret_key)) {
                // 记录日志
                Log::channel('push')->info('Client authentication request failed', [
                    'request_param'  => request()->all(),
                    'response_param' => [],
                ]);
                return response('Forbidden', 403);
            }

            // 组装数据
            $channel_data = null;
            if (!empty($user_id)) {
                $user_data = ['user_id' => $user_id];
                if ($user_info) {
                    $user_data['user_info'] = $user_info;
                }
                $channel_data = json_encode($user_data);
            }

            // 生成认证签名
            $signature = hash_hmac('sha256', 'hsk99:' . $socket_id . ':' . $channel . ':' . $channel_data, $secret_key, false);

            $data = [
                'auth'         => $access_key . ':' . $signature,
                'channel_data' => $channel_data,
            ];
            if (empty($channel_data)) {
                unset($data['channel_data']);
            }

            // 记录日志
            Log::channel('push')->info('Client authentication request succeeded', [
                'request_param'  => request()->all(),
                'response_param' => $data,
            ]);

            return json($data);
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);

            // 记录日志
            Log::channel('push')->info('Client authentication request failed', [
                'request_param'  => request()->all(),
                'response_param' => [],
            ]);

            return response('Forbidden', 403);
        }
    }
}
