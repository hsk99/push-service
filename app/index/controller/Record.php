<?php

namespace app\index\controller;

use app\common\model\Record as RecordModel;
use app\common\model\Project as ProjectModel;

/**
 * 推送记录
 *
 * @author HSK
 * @date 2022-08-15 16:04:20
 */
class Record
{
    /**
     * 列表
     *
     * @author HSK
     * @date 2022-08-15 16:04:20
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function index(\support\Request $request)
    {
        if (request()->isAjax()) {
            $page       = (int)request()->input('page', 1);
            $limit      = (int)request()->input('limit', 10);
            $start_date = request()->input('start_date', date('Y-m-d H:i:s', time() - 86400 * 7));
            $end_date   = request()->input('end_date', date('Y-m-d H:i:s'));

            $startDate = date('Y-m-d H:i:s', strtotime($start_date));
            $endDate   = date('Y-m-d H:i:s', strtotime($end_date));

            $where[] = ['r.create_time', 'between', [$startDate, $endDate]];

            // 按应用ID查找
            if ($project = request()->input("project")) {
                $where[] = ["r.project", "=", $project];
            }
            // 按订阅渠道查找
            if ($channel = request()->input("channel")) {
                $where[] = ["r.channel", "like", "%" . $channel . "%"];
            }
            // 按订阅事件查找
            if ($event = request()->input("event")) {
                $where[] = ["r.event", "like", "%" . $event . "%"];
            }

            $list = RecordModel::alias('r')
                ->join('project p', 'p.id=r.project', 'LEFT')
                ->where($where)
                ->field('r.id, r.channel, r.event, r.subscription, r.create_time, p.name')
                ->order('r.id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page'      => $page,
                ]);

            return api($list);
        }

        return view('record/index', [
            'project_list' => ProjectModel::column('name', 'id')
        ]);
    }

    /**
     * 推送数据
     *
     * @author HSK
     * @date 2022-08-16 11:36:38
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function info(\support\Request $request)
    {
        $data = RecordModel::where('id', request()->input('id'))->value('data');
        if ($data) {
            $data = is_array(json_decode($data, true)) ? json_encode(json_decode($data, true), 448) : $data;
            $data = str_replace(['\r\n', '\n'], "\n", $data);
            $data = htmlspecialchars($data);
        }

        return view('record/info', ['data' => $data]);
    }
}
