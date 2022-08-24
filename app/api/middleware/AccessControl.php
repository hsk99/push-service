<?php

namespace app\api\middleware;

use think\facade\Db;

/**
 * 访问控制
 *
 * @author HSK
 * @date 2022-08-12 16:07:44
 */
class AccessControl implements \Webman\MiddlewareInterface
{
    public function process(\Webman\Http\Request $request, callable $next): \Webman\Http\Response
    {
        // 连接认证，跳出校验
        if (strtolower(request()->controller) === "app\\" . request()->app . "\\controller\\connect") {
            return $next($request);
        }

        $access_key = request()->header('x-hsk99-key', request()->input('access_key'));
        $signature  = request()->header('x-hsk99-signature', request()->input('signature'));

        if (empty($access_key) || empty($signature)) {
            return api([], 401, '401 Not authenticated');
        }

        // 校验access_key
        $projectInfo = Db::name('project')->where('access_key', $access_key)->find();
        if (empty($projectInfo)) {
            return api([], 400, 'illegal request');
        }

        // 校验签名
        $expected_signature = hash_hmac('sha256', json_encode(request()->all(), 320), $projectInfo['secret_key'], false);
        if ($expected_signature !== $signature) {
            return api([], 401, '401 Not authenticated');
        }

        // 校验应用状态
        if (2 === $projectInfo['status']) {
            return api([], 400, 'app disabled');
        }

        $request->projectInfo = $projectInfo;

        return $next($request);
    }
}
