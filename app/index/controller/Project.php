<?php

namespace app\index\controller;

use app\common\model\Project as ProjectModel;

/**
 * 应用管理
 *
 * @author HSK
 * @date 2022-07-28 11:47:14
 */
class Project
{
    /**
     * 列表
     *
     * @author HSK
     * @date 2022-07-28 11:47:14
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function index(\support\Request $request)
    {
        if (request()->isAjax()) {
            $page  = (int)request()->input('page', 1);
            $limit = (int)request()->input('limit', 10);

            $where = [];

            // 按应用名称查找
            if ($name = request()->input("name")) {
                $where[] = ["name", "like", "%" . $name . "%"];
            }

            $list = ProjectModel::where($where)
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page'      => $page,
                ]);

            return api($list);
        }

        return view('project/index');
    }

    /**
     * 添加
     *
     * @author HSK
     * @date 2022-07-28 11:47:14
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function add(\support\Request $request)
    {
        if (request()->isAjax()) {
            try {
                $fields = ['id', 'name', 'desc', 'access_key', 'secret_key', 'web_hook', 'status', 'create_time', 'update_time'];
                $data   = array_filter(request()->post(), function ($k) use ($fields) {
                    return in_array($k, $fields);
                }, ARRAY_FILTER_USE_KEY);

                $data['access_key'] = md5(uniqid(mt_rand(), true));
                $data['secret_key'] = md5(uniqid(mt_rand(), true));

                if (ProjectModel::create($data)) {
                    return api([], 200, '操作成功');
                } else {
                    return api([], 400, '操作失败');
                }
            } catch (\Throwable $th) {
                \Hsk99\WebmanException\RunException::report($th);
                return api([], 400, '操作失败');
            }
        }

        return view('project/add');
    }

    /**
     * 编辑
     *
     * @author HSK
     * @date 2022-07-28 11:47:14
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function edit(\support\Request $request)
    {
        if (request()->isAjax()) {
            try {
                $fields = ['id', 'name', 'desc', 'access_key', 'secret_key', 'web_hook', 'status', 'create_time', 'update_time'];
                $data   = array_filter(request()->post(), function ($k) use ($fields) {
                    return in_array($k, $fields);
                }, ARRAY_FILTER_USE_KEY);

                if (ProjectModel::update($data, ['id' => request()->input('id')])) {
                    return api([], 200, '操作成功');
                } else {
                    return api([], 400, '操作失败');
                }
            } catch (\Throwable $th) {
                \Hsk99\WebmanException\RunException::report($th);
                return api([], 400, '操作失败');
            }
        }

        return view('project/edit', [
            'model' => ProjectModel::find(request()->input('id')),
        ]);
    }

    /**
     * 修改状态
     *
     * @author HSK
     * @date 2022-07-28 11:47:14
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function status(\support\Request $request)
    {
        try {
            $fields = ['id', 'name', 'desc', 'access_key', 'secret_key', 'web_hook', 'status', 'create_time', 'update_time'];
            $data   = array_filter(request()->post(), function ($k) use ($fields) {
                return in_array($k, $fields);
            }, ARRAY_FILTER_USE_KEY);

            if (ProjectModel::update($data, ['id' => request()->input('id')])) {
                // 应用禁用，推送下线通知
                if (2 === (int)request()->input('status')) {
                    \app\common\service\Gateway::closed(
                        null,
                        ProjectModel::getInfoId(request()->input('id'))
                    );
                }

                return api([], 200, '操作成功');
            } else {
                return api([], 400, '操作失败');
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return api([], 400, '操作失败');
        }
    }

    /**
     * 重置密钥
     *
     * @author HSK
     * @date 2022-08-23 15:11:53
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function reset(\support\Request $request)
    {
        try {
            if (ProjectModel::update(['secret_key' => md5(uniqid(mt_rand(), true))], ['id' => request()->input('id')])) {
                return api([], 200, '操作成功');
            } else {
                return api([], 400, '操作失败');
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return api([], 400, '操作失败');
        }
    }
}
