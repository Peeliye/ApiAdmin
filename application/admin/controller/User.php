<?php
/**
 *
 * @since   2018-02-06
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\admin\controller;


use app\model\ApiAuthGroupAccess;
use app\model\ApiUser;
use app\model\ApiUserData;
use app\util\ReturnCode;
use app\util\Tools;

class User extends Base {

    /**
     * 获取用户列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function index() {

        $limit = $this->request->get('size', config('apiAdmin.ADMIN_LIST_DEFAULT'));
        $start = $limit * ($this->request->get('page', 1) - 1);
        $type = $this->request->get('type', '');
        $keywords = $this->request->get('keywords', '');
        $status = $this->request->get('status', '');

        $where = [];
        if ($status === '1' || $status === '0') {
            $where['status'] = $status;
        }
        if ($type) {
            switch ($type) {
                case 1:
                    $where['username'] = ['like', "%{$keywords}%"];
                    break;
                case 2:
                    $where['nickname'] = ['like', "%{$keywords}%"];
                    break;
            }
        }

        $listModel = (new ApiUser())->where($where)->order('regTime', 'DESC');
        $listInfo = $listModel->limit($start, $limit)->select();
        $count = $listModel->count();

        $listInfo = $this->buildArrFromObj($listInfo);
        $idArr = array_column($listInfo, 'id');

        $userData = ApiUserData::all(function($query) use ($idArr) {
            $query->whereIn('uid', $idArr);
        });
        $userData = $this->buildArrFromObj($userData);
        $userData = $this->buildArrByNewKey($userData, 'uid');

        $userGroup = ApiAuthGroupAccess::all(function($query) use ($idArr) {
            $query->whereIn('uid', $idArr);
        });
        $userGroup = $this->buildArrFromObj($userGroup);
        $userGroup = $this->buildArrByNewKey($userGroup, 'uid');

        foreach ($listInfo as $key => $value) {
            if (isset($userData[$value['id']])) {
                $listInfo[$key]['lastLoginIp'] = long2ip($userData[$value['id']]['lastLoginIp']);
                $listInfo[$key]['loginTimes'] = $userData[$value['id']]['loginTimes'];
                $listInfo[$key]['lastLoginTime'] = date('Y-m-d H:i:s', $userData[$value['id']]['lastLoginTime']);
            }
            $listInfo[$key]['regIp'] = long2ip($listInfo[$key]['regIp']);
            if (isset($userGroup[$value['id']])) {
                $listInfo[$key]['groupId'] = explode(',', $userGroup[$value['id']]['groupId']);
            } else {
                $listInfo[$key]['groupId'] = [];
            }
        }

        return $this->buildSuccess([
            'list'  => $listInfo,
            'count' => $count
        ]);
    }

    /**
     * 新增用户
     * @return array
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function add() {
        $groups = '';
        $postData = $this->request->post();
        $postData['regIp'] = request()->ip(1);
        $postData['regTime'] = time();
        $postData['password'] = Tools::userMd5($postData['password']);
        if ($postData['groupId']) {
            $groups = implode(',', $postData['groupId']);
        }
        unset($postData['groupId']);
        $res = ApiUser::create($postData);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR, '操作失败');
        } else {
            ApiAuthGroupAccess::create([
                'uid'     => $res->id,
                'groupId' => $groups
            ]);

            return $this->buildSuccess([]);
        }
    }

    /**
     * 用户状态编辑
     * @return array
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function changeStatus() {
        $id = $this->request->get('id');
        $status = $this->request->get('status');
        $res = ApiUser::update([
            'id'     => $id,
            'status' => $status
        ]);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR, '操作失败');
        } else {
            return $this->buildSuccess([]);
        }
    }

    /**
     * 编辑用户
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     * @return array
     */
    public function edit() {
        $groups = '';
        $postData = $this->request->post();
        $postData['regIp'] = request()->ip(1);
        $postData['regTime'] = time();
        if ($postData['password'] === 'ApiAdmin') {
            unset($postData['password']);
        } else {
            $postData['password'] = Tools::userMd5($postData['password']);
        }
        if ($postData['groupId']) {
            $groups = implode(',', $postData['groupId']);
        }
        unset($postData['groupId']);
        $res = ApiUser::update($postData);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR, '操作失败');
        } else {
            ApiAuthGroupAccess::update([
                'groupId' => $groups
            ], [
                'uid' => $res->id,
            ]);

            return $this->buildSuccess([]);
        }
    }

    /**
     * 删除用户
     * @return array
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function del() {
        $id = $this->request->get('id');
        if (!$id) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }
        ApiUser::destroy($id);
        ApiAuthGroupAccess::destroy(['uid' => $id]);

        return $this->buildSuccess([]);

    }

}
