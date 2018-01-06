<?php

namespace app\index\controller;

use think\Controller;
use think\Request;
use think\Response;
use think\Loader;
use think\Db;
use think\Config;
use think\Cache;

use app\index\controller\Base;


use app\index\model\User;
use app\index\model\UserMp;
use app\index\model\UserShare;
use app\index\model\UserLog;
use app\index\model\Video as Video_Model;
use app\index\model\VideoPromotion;
use app\index\model\Comment;
use app\index\model\VideoDisplayLog;
use app\index\model\UserStore;
use app\index\model\Setting;
use app\index\model\Ads;


use app\index\model\UserPromotion;
use app\index\model\UserPromotionBalance;
use app\index\model\UserPromotionGrid;
use app\index\model\UserPromotionTicket;
use app\index\model\SettingPromotion;


class Video extends Base
{

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        try {
            $request = Request::instance();
            $p = $request->has('p', 'get') ? $request->get('p/d') : 1;
            $n = $request->has('n', 'get') ? $request->get('n/d') : 5;
            $n = 5;
            $user_id = $request->get('user_id');
            $order = $request->has('order', 'get') ? $request->get('order') : 'comment';
            $category = $request->has('category', 'get') ? $request->get('category'): '';

            if(!empty($category)){
                $category = explode(',', $category);
            }

            if(empty($category) && $this->app_code != 'neihan_1') {
                # $category = [1112, 1113, 1114];
                $category = [65];
            }

            $data = array('c' => 0, 'm' => '', 'd' => array());

            if(empty($user_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            User::where('id', $user_id)->update(['is_displayed' => 1]);

            // $settings = Setting::get(1);
            $app_code = 'neihan_1';
            $comconfig = Config::get('comconfig');

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
            $result = Setting::where('app_code', $app_code)
                ->where('version', $version)
                ->limit(1)
                ->order('id', 'desc')
                ->select();
            $settings = $result[0];

            if($settings['online'] == 1) {
                $vids = [];
                $video_model = new Video_Model;
                $video_awsome = $video_model->get_videos($user_id, $category, $vids, 1, "display_click_ratio");

                if(!empty($video_awsome)) {
                    $vids[] = $video_awsome[0]['video_id'];
                }
                $video_hot = $video_model->get_videos($user_id, $category, $vids, 1, "display_share_ratio");

                if(!empty($video_hot)) {
                    $vids[] = $video_hot[0]['video_id'];
                }
                $last_num = $n - count($video_awsome) - count($video_hot);
                $video_normal = $video_model->get_videos($user_id, $category, $vids, 3, "c_display_count", 0, 50);

                if($this->app_code == 'neihan_1') {
                    $video_jump = $video_model->get_jump_videos($user_id, [65], [], 1, "play_count", 0, 0);
                } else {
                    $video_jump = [];
                }

                $video_douyin = [];

                $video_funny = $video_model->get_videos($user_id, [65], [], 1, "play_count", 0, 0);

                $data['d'] = array_merge($video_awsome, $video_hot, $video_normal, $video_jump, $video_douyin, $video_funny);
            } else {
                $video_model = new Video_Model;
                $data['d'] = $video_model->get_videos_waitting($user_id, $p, $n);
            }

            $vids = [];
            foreach ($data['d'] as $k => &$val) {
                $vids[] = $val['video_id'];
                $ad = [
                    'image' => '',
                    'appId' => '',
                    'path' => '',
                    'extraData' => ''
                ];

                if($k == 0) {
                    if($settings['ad_show'] == 1) {
                        $ads = Ads::where('status', 1)->order('id', 'desc')->find();

                        if($ads && $ads->image) {
                            $ad['image'] = $ads->image;
                            $ad['appId'] = $ads->appid;
                            $ad['path'] = $ads->path;
                            $ad['extraData'] = $ads->extract_data;
                        }
                        
                    } elseif($settings['ad_show'] == 2) {
                        $ad_show = rand(1, 100);
                        if($settings['ad_show_ratio'] >= $ad_show) {
                            $ads = Ads::where('status', 1)->order('id', 'desc')->find();

                            if($ads && $ads->image) {
                                $ad['image'] = $ads->image;
                                $ad['appId'] = $ads->appid;
                                $ad['path'] = $ads->path;
                                $ad['extraData'] = $ads->extract_data;
                            }
                        }
                    }
                }
                $val['ad'] = $ad;

                /*
                if($this->app_code == 'neihan_1') {
                    $val['jump'] = 1;
                }
                */
            }

            # 更新视频展示数
            if(!empty($vids)) {
                $curr_time = time();
                $display_sql = "INSERT INTO `videos_display_logs` (`user_id` , `video_id` , `create_time` , `update_time`) VALUES ";
                $display_logs = [];
                foreach ($vids as $vid) {
                    $display_logs[] = "({$user_id} , '{$vid}' , {$curr_time} , {$curr_time})";
                }
                $display_sql = $display_sql . join(',', $display_logs);
                Db::execute($display_sql);

                Video_Model::where('group_id', 'in', $vids)->setInc('c_display_count');
            }            
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        
        return Response::create($data, 'json')->code(200);
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function hot()
    {
        try {
            $request = Request::instance();
            $p = $request->has('p', 'get') ? $request->get('p/d') : 1;
            $n = $request->has('n', 'get') ? $request->get('n/d') : 5;
            $n = 5;
            $user_id = $request->get('user_id');
            $order = $request->has('order', 'get') ? $request->get('order') : 'comment';
            $category = $request->has('category', 'get') ? $request->get('category'): '';

            if(!empty($category)){
                $category = explode(',', $category);
            } else {
                $category = [];
            }

            if(empty($category) && $this->app_code != 'neihan_1') {
                # $category = [1112, 1113];
                $category = [65];
            }

            $data = array('c' => 0, 'm' => '', 'd' => array());

            if(empty($user_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $video_model = new Video_Model;
            $video_awsome = $video_model->get_videos($user_id, $category, [], 4, "display_click_ratio", 0, 0);

            $data['d'] = $video_awsome;
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        
        return Response::create($data, 'json')->code(200);
    }

    public function store_list()
    {
        try {
            $p = Request::instance()->has('p', 'get') ? Request::instance()->get('p/d') : 1;
            $n = Request::instance()->has('n', 'get') ? Request::instance()->get('n/d') : 10;
            $user_id = Request::instance()->get('user_id');

            $data = array('c' => 0, 'm' => '', 'd' => array());
            if(empty($user_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $records = UserStore::where('user_id', $user_id)
                ->page("{$p}, {$n}")
                ->order('id desc')
                ->select();

            foreach ($records as $key => $value) {
                $record = $value->video;
                $data['d'][] = [
                    'video_id' => strval($record['group_id']),
                    'content' => $record['content'],
                    'online_time' => date('Y-m-d H:i:s', $record['online_time']),
                    'category_name' => $record['category_name'],
                    'url' => timestamp_url($record['vurl']),
                    'cover_image' => str_replace('.webp', '', $record['cover_image']),
                    'user_name' => $record['user_name'],
                    'user_avatar' => $record['user_avatar'],
                    'play_count' => $record['play_count']+$record['c_play_count'],
                    'digg_count' => $record['digg_count']+$record['c_digg_count'],
                    'bury_count' => $record['bury_count']+$record['c_bury_count'],
                    'share_count' => $record['share_count']+$record['c_share_count'],
                    'comment_count' => $record['comment_count']+$record['c_comment_count'],
                    'is_digg' => 0,
                    'level' => $record['level'],
                    'comments' => []
                ];
            }
            
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        
        return Response::create($data, 'json')->code(200);
    }

    public function detail()
    {
        try {
            $video_id = Request::instance()->get('video_id');
            $user_id = Request::instance()->get('user_id');

            $data = array('c' => 0, 'm' => '', 'd' => array());
            
            if(empty($video_id) || empty($user_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $record = Video_Model::get(['item_id' => $video_id]);
            $record->c_display_count += 1;
            $record->save();


            $displaylog = New VideoDisplayLog;
            $displaylog->data([
                'user_id' => $user_id,
                'video_id' => $video_id,
            ]);
            $displaylog->save();


            $info = array(
                'video_id' => strval($record['group_id']),
                'content' => $record['content'],
                'online_time' => date('Y-m-d H:i:s', $record['online_time']),
                'category_name' => $record['category_name'],
                'url' => $this->timestamp_url($record['vurl']),
                'cover_image' => str_replace('.webp', '', $record['cover_image']),
                'user_name' => $record['user_name'],
                'user_avatar' => $record['user_avatar'],
                'play_count' => $record['play_count']+$record['c_play_count'],
                'digg_count' => $record['digg_count']+$record['c_digg_count'],
                'bury_count' => $record['bury_count']+$record['c_bury_count'],
                'share_count' => $record['share_count']+$record['c_share_count'],
                'is_digg' => 0,
                'comment_count' => $record['comment_count']+$record['c_comment_count'],
                'comments' => array(),
                'jump' => 0
            );

            if($record['category_id'] == 1111) {
                $info['jump'] = 1;
            }

            $is_digg = Db::table('users_logs')
                                    ->where('user_id', $user_id)
                                    ->where('video_id', $video_id)
                                    ->where('type', 6)
                                    ->count();
            if($is_digg) {
                $info['is_digg'] = 1;
            }

            $top_comments = Db::table('comments_v3')->where('group_id', $record['group_id'])
                                                    ->limit(5)
                                                    ->order('id', 'desc')
                                                    ->select();
            foreach ($top_comments as $val) {
                if(empty($val['content'])) {
                    continue;
                }
                $is_digg = Db::table('users_logs')
                                    ->where('user_id', $user_id)
                                    ->where('video_id', $val['id'])
                                    ->where('type', 7)
                                    ->count();
                
                $info['comments'][] = array(
                    'comment_id' => $val['id'],
                    'user_name' => $val['user_name'],
                    'user_avatar' => $val['user_avatar'],
                    'content' => $val['content'],
                    'create_time' => $val['create_time'],
                    'digg_count' => $val['digg_count'],
                    'comment_count' => $val['comment_count'],
                    'is_digg' => $is_digg ? 1 : 0
                );

            }
            if(empty($info['comments']))  {
                $info['comments'][] = array(
                    'comment_id' => 1,
                    'user_name' => $record['user_name'],
                    'user_avatar' => $record['user_avatar'],
                    'content' => $record['content'],
                    'create_time' => $record['online_time'],
                    'digg_count' => $record['digg_count'],
                    'comment_count' => $record['comment_count']
                );
            }
            $data['d'] = $info;
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        
        return Response::create($data, 'json')->code(200);
    }

    public function count()
    {
        try {
            $user_id = Request::instance()->param('user_id');
            $video_id = Request::instance()->param('video_id');
            $type = Request::instance()->param('type');

            $data = ['c' => 0, 'm'=> '', 'd' => []];

            if(empty($user_id) || empty($video_id) || empty($type)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $user = User::get($user_id);
            if(empty($user)) {
                $data['c'] = -1024;
                $data['m'] = 'User Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            $video = Video_Model::get(['item_id' => $video_id]);
            if(empty($video)) {
                $data['c'] = -1024;
                $data['m'] = 'Video Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            if($type == 'digg') {
                $video->c_digg_count += 1;
                if($user_id == 10) {
                    $video->online = 1;
                }
            } elseif($type == 'bury') {
                $video->c_bury_count += 1;
            } elseif($type == 'play') {
                $video->c_play_count += 1;
                User::where('id', $user_id)->update(['is_played' => 1]);
            } elseif($type == 'play_end') {
                $video->c_play_end_count += 1;
                User::where('id', $user_id)->update(['is_played_end' => 1]);
            } elseif($type == 'replay') {
                $video->c_replay_count += 1;
            }
            $video->save();

            $com_config = Config::get('comconfig');
            $user_log = New UserLog;
            $user_log->data([
                'user_id' => $user_id,
                'video_id' => $video_id,
                'type' => $com_config['log_type'][$type]
            ]);
            $user_log->save();
            if($type == 'play_end') {
                if($user->promotion < 3) {
                    if($user->create_time >= date('Y-m-d').' 00:00:00') {
                        $this->_add_parent_user_point($user_id, '203');
                    } else {
                        $this->_add_parent_user_point($user_id, '303');
                    }
                } else {
                    $this->_add_user_point($user_id, '103');
                }
            }
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }

        return Response::create($data, 'json')->code(200);
    }

    public function share()
    {
        try {
            $user_id = Request::instance()->param('user_id');
            $video_id = Request::instance()->param('video_id');
            $page = Request::instance()->param('page');
            $wechat_gid = Request::instance()->param('gid');

            $data = ['c' => 0, 'm'=> '', 'd' => []];

            if(empty($user_id) || empty($video_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $user = User::get($user_id);
            if(empty($user)) {
                $data['c'] = -1024;
                $data['m'] = 'User Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            User::where('id', $user_id)->update(['is_shared' => 1]);

            $video = Video_Model::get(['item_id' => $video_id]);
            if(empty($video)) {
                $data['c'] = -1024;
                $data['m'] = 'Video Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            $video->c_share_count += 1;
            if($video->level < 4 and $video->c_display_count >= 100 and $video->c_share_count >= 10) {
                $video->level += 4;
            }
            $video->save();

            $user_share = new UserShare;
            $user_share->data([
                'user_id'  => $user_id,
                'video_id' => $video_id,
                'code' => $this->app_code,
                'wechat_gid' => strval($wechat_gid)
            ]);
            $user_share->save();

            $com_config = Config::get('comconfig');
            $user_log = new UserLog;
            $user_log->data([
                'user_id' => $user_id,
                'video_id' => $video_id,
                'type' => $com_config['log_type']['share']
            ]);
            $user_log->save();

            $groups = UserShare::where('user_id', $user_id)
                ->where('create_time', '>=', $user->promotion_time)
                ->where('wechat_gid', '<>', '')
                ->count('distinct wechat_gid');
            
            if($groups >= 3 && $user->promotion == 1) {
                $user->promotion = 2;
                $user->save();

                UserPromotion::where('user_id', $user_id)->update(['status' => 1]);

            } elseif($groups >= 3 && $user->promotion == 2 && $user->user_mp_id) {

                # 默认是金牌代理
                $user->promotion = 4;

                # 生成一个公众号二维码
                $mp_qrcode = $this->_generate_qrcode($user->id);
                $user->mp_qrcode = $mp_qrcode[0];
                $user->mp_qrcode_ticket = $mp_qrcode[1];
                $user->save();

                UserMp::where('id', $user->user_mp_id)
                    ->update([
                        'promotion' => 4,
                        'promotion_qrcode' => $mp_qrcode[0],
                        'qrcode_ticket' => $mp_qrcode[1]
                ]);

                UserPromotion::where('user_id', $user_id)->update(['status' => 2, 'type' => 2]);

                # 如果你是一个代理, 那就不能做别人的代理了
                $exists = UserPromotionGrid::where('user_id', $user_id)->count();
                if($exists) {
                    return Response::create($data, 'xml')->code(200)->options(['root_node'=> 'xml']);
                }

                $psettings = SettingPromotion::get(1);

                # 加代理
                $user_promo = UserPromotion::where('user_id', $user_id)->find();

                $user_promo->status = 2;
                $user_promo->save();
                # 加钱
                if($user_promo->parent_user_id) {
                    UserPromotionBalance::where('user_id', $user_promo->parent_user_id)
                        ->update([
                            'commission'  => ['exp', "commission+{$psettings->commission_lv1}"],
                            'commission_avail' => ['exp', "commission_avail+{$psettings->commission_lv1}"],
                        ]);

                    # user_id是谁的一级代理
                    $user_promo_grid = New UserPromotionGrid;
                    $user_promo_grid->data([
                        'parent_user_id' => $user_promo->parent_user_id,
                        'user_id' => $user_promo->user_id,
                        'level' => 1
                    ]);
                    $user_promo_grid->save();


                    if($user_promo->parent_user_id == 0) {
                        return Response::create($data, 'xml')->code(200)->options(['root_node'=> 'xml']);
                    }

                    # 找出parent_user_id是谁的一级代理, 把user_id加成为二级代理
                    $p1_promo = UserPromotionGrid::where('user_id', $user_promo->parent_user_id)
                        ->where('level', 1)->find();
                    if(!empty($p1_promo)) {
                        if(!empty($p1_promo->parent_user_id)) {
                            $user_promo_grid = New UserPromotionGrid;
                            $user_promo_grid->data([
                                'parent_user_id' => $p1_promo->parent_user_id,
                                'user_id' => $user_promo->user_id,
                                'level' => 2
                            ]);
                            $user_promo_grid->save();

                            # 加钱
                            UserPromotionBalance::where('user_id', $p1_promo->parent_user_id)
                                ->update([
                                    'commission'  => ['exp', "commission+{$psettings->commission_lv2}"],
                                    'commission_avail' => ['exp', "commission_avail+{$psettings->commission_lv2}"],
                                ]); 
                        }

                        $p2_promo = UserPromotionGrid::where('user_id', $p1_promo->parent_user_id)->where('level', 1)->find();
                        if(!empty($p2_promo)) {
                            $user_promo_grid = New UserPromotionGrid;
                            $user_promo_grid->data([
                                'parent_user_id' => $p2_promo->parent_user_id,
                                'user_id' => $user_promo->user_id,
                                'level' => 3
                            ]);
                            $user_promo_grid->save();

                            # 加钱
                            UserPromotionBalance::where('user_id', $p2_promo->parent_user_id)
                                ->update([
                                    'commission'  => ['exp', "commission+{$psettings->commission_lv3}"],
                                    'commission_avail' => ['exp', "commission_avail+{$psettings->commission_lv3}"],
                                ]);
                        }
                        
                    }
                }
            }

            $data['d'] = [
                'id' => $user_share->id,
                'num' => $groups
            ];

            if(!empty($wechat_gid)) {
                if($user->promotion >= 3) {
                    $this->_add_user_point($user_id, '104');
                }
            } else {
                if($user->promotion >= 3) {
                    $this->_add_user_point($user_id, '105');
                }
            }
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];   
        }
        
        return Response::create($data, 'json')->code(200);
    }

    public function store()
    {
        try {
            $user_id = Request::instance()->post('user_id');
            $video_id = Request::instance()->post('video_id');

            $data = ['c' => 0, 'm'=> '', 'd' => []];

            if(empty($user_id) || empty($video_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $user = User::get($user_id);
            if(empty($user)) {
                $data['c'] = -1024;
                $data['m'] = 'User Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            $video = Video_Model::get(['item_id' => $video_id]);
            if(empty($video)) {
                $data['c'] = -1024;
                $data['m'] = 'Video Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            $user_store = new UserStore;
            $exists = $user_store->where('user_id', $user_id)
                ->where('video_id', $video_id)
                ->count();
            if(!$exists) {
                $user_store->data([
                    'user_id'  => $user_id,
                    'video_id' => $video_id
                ]);
                $user_store->save();

                $com_config = Config::get('comconfig');
                $user_log = new UserLog;
                $user_log->data([
                    'user_id' => $user_id,
                    'video_id' => $video_id,
                    'type' => $com_config['log_type']['store']
                ]);
                $user_log->save();
            }

            $data['d'] = [];
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];   
        }
        
        return Response::create($data, 'json')->code(200);
    }

    public function comment()
    {
        try {
            $user_id = Request::instance()->param('user_id');
            $video_id = Request::instance()->param('video_id');
            $content = Request::instance()->param('content');

            $data = ['c' => 0, 'm'=> '', 'd' => []];

            if(empty($user_id) || empty($video_id) || empty($content)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $user = User::get($user_id);
            if(empty($user)) {
                $data['c'] = -1024;
                $data['m'] = 'User Not Exists';
                return Response::create($data, 'json')->code(200);   
            }
            $user->is_commented = 1;
            $user->save();

            $video = Video_Model::get(['item_id' => $video_id]);
            if(empty($video)) {
                $data['c'] = -1024;
                $data['m'] = 'Video Not Exists';
                return Response::create($data, 'json')->code(200);   
            }

            $comment = new Comment;
            $comment->data([
                'user_id'  => $user_id,
                'user_name' => $user->user_name,
                'user_avatar' => $user->user_avatar,
                'group_id' => $video->group_id,
                'item_id' => $video->item_id,
                'content' => $content,
                'digg_count' => 0,
                'comment_count' =>0
            ]);
            $comment->save();

            $video->c_comment_count += 1;
            $video->save();

            $com_config = Config::get('comconfig');
            $user_log = new UserLog;
            $user_log->data([
                'user_id' => $user_id,
                'video_id' => $video_id,
                'type' => $com_config['log_type']['comment']
            ]);
            $user_log->save();

        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];  
        }

        return Response::create($data, 'json')->code(200);
    }


    public function promotion()
    {
        try {
            $request = Request::instance();

            $user_id = $request->get('user_id');

            $data = array('c' => 0, 'm' => '', 'd' => array());

            if(empty($user_id)) {
                $data['c'] = -1024;
                $data['m'] = 'Arg Missing';
                return Response::create($data, 'json')->code(200);
            }

            $videos = VideoPromotion::all(['status'=>1]);
            foreach ($videos as $key => $record) {
                $data['d'][] = [
                    'video_id' => strval($record->video->group_id),
                    'content' => $record->video->content,
                    'online_time' => date('Y-m-d H:i:s', $record->video->online_time),
                    'category_name' => $record->video->category_name,
                    'url' => timestamp_url($record->video->vurl),
                    'cover_image' => str_replace('.webp', '', $record->video->cover_image),
                    'user_name' => $record->video->user_name,
                    'user_avatar' => $record->video->user_avatar,
                    'play_count' => $record->video->play_count+$record->video->c_play_count,
                    'digg_count' => $record->video->digg_count+$record->video->c_digg_count,
                    'bury_count' => $record->video->bury_count+$record->video->c_bury_count,
                    'share_count' => $record->video->share_count+$record->video->c_share_count,
                    'comment_count' => $record->video->comment_count+$record->video->c_comment_count,
                    'is_digg' => 0,
                    'level' => $record->video->level,
                    'comments' => []
                ];
            }
        } catch (Exception $e) {
            $data = ['c' => -1024, 'm'=> $e->getMessage(), 'd' => []];
        }
        
        return Response::create($data, 'json')->code(200);
    }
}
