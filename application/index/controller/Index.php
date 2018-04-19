<?php
// +----------------------------------------------------------------------
// | layerIM + Workerman + ThinkPHP5 即时通讯
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------
namespace app\index\controller;

use think\Controller;


class Index extends Controller
{



	public function _initialize()
	{
		if(empty(cookie('uid')) ){
			$this->redirect( url('login/index'), 302 );
		}
	}
    public function index()
    {
    	$mine = db('chatuser')->where('id', cookie('uid'))->find();
    	$this->assign([
    			'uinfo' => $mine
    	]);
        return $this->fetch();
    }
    
    //获取列表
    public function getList()
    {
    	//查询自己的信息
    	$mine = db('chatuser')->where('id', cookie('uid'))->find();
    	$other = db('chatuser')->select();

        //查询当前用户的所处的群组
        $groupArr = [];
        $groups = db('groupdetail')->field('groupid')->where('userid', cookie('uid'))->group('groupid')->select();
        if( !empty( $groups ) ){
            foreach( $groups as $key=>$vo ){
                $ret = db('chatgroup')->where('id', $vo['groupid'])->find();
                if( !empty( $ret ) ){
                    $groupArr[] = $ret;
                }
            }
        }
        unset( $ret, $groups );

        $online = 0;
        $group = [];  //记录分组信息
        $userGroup = config('user_group');
        $list = [];  //群组成员信息
        $i = 0;
        $j = 0;

        foreach( $userGroup as $key=>$vo ){
            $group[$i] = [
                'groupname' => $vo,
                'id' => $key,
                'online' => 0,
                'list' => []
            ];
            $i++;
        }
        unset( $userGroup );

        foreach( $group as $key=>$vo ){

            foreach( $other as $k=>$v ) {

                if ($vo['id'] == $v['groupid']) {

                    $list[$j]['username'] = $v['username'];
                    $list[$j]['id'] = $v['id'];
                    $list[$j]['avatar'] = $v['avatar'];
                    $list[$j]['sign'] = $v['sign'];

                    if ('online' == $v['status']) {
                        $online++;
                    }

                    $group[$key]['online'] = $online;
                    $group[$key]['list'] = $list;

                    $j++;
                }
            }
            $j = 0;
            $online = 0;
            unset($list);
        }
       //print_r($group);die;
        unset( $other );		
    			
        $return = [
       		'code' => 0,
       		'msg'=> '',
       		'data' => [
       			'mine' => [
	       				'username' => $mine['username'],
	       				'id' => $mine['id'],
	       				'status' => 'online',
       					'sign' => $mine['sign'],
       					'avatar' => $mine['avatar']	
       			],
       			'friend' => $group,
				'group' => $groupArr
       		],
        ];

    	return json( $return );

    }
    
    //获取组员信息
    public function getMembers()
    {
    	$id = input('param.id');
    	
    	//群主信息
    	$owner = db('chatgroup')->field('owner_name,owner_id,owner_avatar,owner_sign')->where('id = ' . $id)->find();
    	//群成员信息
    	$list = db('groupdetail')->field('userid id,username,useravatar avatar,usersign sign')
    	->where('groupid = ' . $id)->select();
    	
    	$return = [
    			'code' => 0,
    			'msg' => '',
    			'data' => [
    				'owner' => [
    						'username' => $owner['owner_name'],
    						'id' => $owner['owner_id'],
    						'owner_id' => $owner['owner_avatar'],
    						'sign' => $owner['owner_sign']
    				],
    				'list' => $list	
    			]
    	];
    	
    	return json( $return );
    }


    public static function onMessage($client_id, $message)
    {

        /*监听事件，需要把客户端发来的json转为数组*/
        $data = json_decode($message, true);
        switch ($data['type']) {

            //当有用户上线时
            case 'reg':
                //绑定uid 用于数据分发
                Gateway::bindUid($client_id, $data['content']['uid']);
                self::$user[$data['content']['uid']] = $client_id;
                self::$uuid[$data['content']['uid']] = $data['content']['uid'];

                //给当前客户端 发送当前在线人数，以及当前在线人的资料
                $reg_data['uuser'] = self::$uuid;
                $reg_data['num'] = count(self::$user);
                $reg_data['type'] = "reguser";
                Gateway::sendToClient($client_id, json_encode($reg_data));

                //将当前在线用户数量，和新上线用户的资料发给所有人 但把排除自己，否则会出现重复好友
                $all_data['type'] = "addList";
                $all_data['content'] = $data['content'];
                $all_data['content']['type'] = 'friend';
                $all_data['content']['groupid'] = 2;
                $all_data['num'] = count(self::$user);
                Gateway::sendToAll(json_encode($all_data), '', $client_id);
                break;


            case 'chatMessage':
                //处理聊天事件
                $msg['username'] = $data['content']['mine']['username'];
                $msg['avatar'] = $data['content']['mine']['avatar'];
                $msg['id'] = $data['content']['mine']['id'];
                $msg['content'] = $data['content']['mine']['content'];
                $msg['type'] = $data['content']['to']['type'];
                $chatMessage['type'] = 'getMessage';
                $chatMessage['content'] = $msg;

                //处理单聊
                if ($data['content']['to']['type'] == 'friend') {

                    if (isset(self::$uuid[$data['content']['to']['id']])) {
                        Gateway::sendToUid(self::$uuid[$data['content']['to']['id']], json_encode($chatMessage));
                    } else {
                        //处理离线消息
                        $noonline['type'] = 'noonline';
                        Gateway::sendToClient($client_id, json_encode($noonline));
                    }
                } else {
                    //处理群聊
                    $chatMessage['content']['id'] = $data['content']['to']['id'];
                    Gateway::sendToAll(json_encode($chatMessage), '', $client_id);
                }
                break;
        }


    }
    
    
}
