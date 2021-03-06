<?php

namespace app\index\controller;

use think\Controller;
use think\Request;
use think\Response;
use think\Loader;
use think\Db;
use think\Config;

use app\index\model\Setting;
use app\index\model\SettingPromotion;
use app\index\model\UserJump;


class Common extends Controller
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $comconfig = Config::get('comconfig');
        $request = Request::instance();
        $data = ['c' => 0, 'm'=> '', 'd' => []];

        $app_code = 'neihan_1';
        $version = $request->get('version');
        if(empty($version)) {
            $version = '10000';
        }
        foreach ($comconfig['domain_settings'] as $key => $value) {
            if(strrpos($request->domain(), $key) !== false) {
                $app_code = $value;
                break;
            }
        }

        $settings = New Setting;
        $result = $settings->where('app_code', $app_code)
            ->where('version', $version)
            ->limit(1)
            ->order('id', 'desc')
            ->select();

        if(!empty($result)) {
            $data['d'] = [
                'version' => $result[0]->version,
                'online' => $result[0]->online,
                'auth' => $result[0]->auth,
                'share' => $result[0]->share,
                'touch' => $result[0]->touch,
                'replay_share' => $result[0]->replay_share,
                'share_interval' => $result[0]->share_interval
            ];
        } else {
            $data['d'] = [
                'version' => $version,
                'online' => 0,
                'auth' => 0,
                'share' => 0,
                'touch' => 0,
                'replay_share' => 0,
                'share_interval' => 0
            ];
        }

        return Response::create($data, 'json')->code(200);
    }

    public function promotion()
    {
        try {
            $data = ['c' => 0, 'm'=> '', 'd' => []];

            $settings = SettingPromotion::get(1);
            $data['d'] = [
                'ticket' => floatval($settings->ticket),
                'golden_ticket' => floatval($settings->golden_ticket),
                'commission_lv1' => floatval($settings->commission_lv1),
                'commission_lv2' => floatval($settings->commission_lv2),
                'commission_lv3' => floatval($settings->commission_lv3)
            ];

        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        return Response::create($data, 'json')->code(200);
    }

    public function jump()
    {
        try {
            $data = ['c' => 0, 'm'=> '', 'd' => []];
            $request = Request::instance();
            $user_id = $request->param('user_id');

            if(empty($user_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }
            $j = new UserJump;
            $j->data([
                'user_id' => $user_id
            ]);
            $j->save();

        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        return Response::create($data, 'json')->code(200);
    }

}
