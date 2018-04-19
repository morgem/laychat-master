<?php
namespace app\index\controller;

use think\Controller;

class User extends Controller
{
/*    public function index()
    {
        return json(['err_code' => 0, 'data' =>[
        ["nickname"=>"Alex Black","location"=>"London","avatar"=>"1","header"=>"A"]],'err_msg' => 'success']);
    }*/

    public function getList()
    {
        //查询自己的信息
        $mine = db('chatuser')->where('id', 1)->find();
        $other = db('chatuser')->select();

        //查询当前用户的所处的群组
        $groupArr = [];
        $groups = db('groupdetail')->field('groupid')->where('userid', 1)->group('groupid')->select();
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

    public function message(){
        return json(['err_code' => 0, 'data' =>[
            ["text"=>"Alex Black","from"=>"sent"]], 'err_msg' => 'success']);
    }
}