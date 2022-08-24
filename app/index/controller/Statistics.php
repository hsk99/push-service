<?php

namespace app\index\controller;

use think\facade\Db;
use app\common\service\Statistics as BaseStatistics;

class Statistics
{
    /**
     * 统计概览
     *
     * @author HSK
     * @date 2022-08-23 17:09:36
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function overview(\support\Request $request)
    {
        $statisticData = BaseStatistics::totalData();
        $chartList     = BaseStatistics::chartData();

        return view('statistics/overview', [
            'statisticData' => $statisticData,
            'chartList'     => json_encode($chartList, 320),
        ]);
    }

    /**
     * 运行统计
     *
     * @author HSK
     * @date 2022-08-23 17:11:16
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function run(\support\Request $request)
    {
        $date    = request()->input('date', date('Y-m-d', time()));
        $project = request()->input('project', null);

        $project_list = Db::name('project')->column('name', 'id');

        $statisticData = BaseStatistics::totalData($date, $project);
        $chartList     = BaseStatistics::dailyChartData($date, $project);

        return view('statistics/run', [
            'date'          => $date,
            'project'       => $project,
            'project_list'  => $project_list,
            'statisticData' => $statisticData,
            'chartList'     => json_encode($chartList, 320),
        ]);
    }
}
