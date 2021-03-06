<?php
/**
 *  [冻死迎风站]--[饿死不说么吃饭]
 *  =========================================================================@_@=====================================@_@
 * @desc    基于用户组/角色访问权限控制系统
 * @author  leeong <9387524@gmail.com>
 * @version 1.0.0
 */
namespace Think;

class Access{
    //默认配置
    protected $_config = array(
        'AUTH_ON'           => true,                // 认证开关
        'AUTH_GROUP'        => 'auth_group',        // 用户组数据表名
        'AUTH_GROUP_ACCESS' => 'auth_group_access', // 用户-用户组关系表
        'AUTH_RULE'         => 'auth_rule',         // 权限规则表
        'AUTH_USER'         => 'admin'              // 用户信息表
    );

    public function __construct()
    {
        $prefix = C('DB_PREFIX');
        $this->_config['AUTH_GROUP'] = $prefix.$this->_config['AUTH_GROUP'];
        $this->_config['AUTH_RULE'] = $prefix.$this->_config['AUTH_RULE'];
        $this->_config['AUTH_USER'] = $prefix.$this->_config['AUTH_USER'];
        $this->_config['AUTH_GROUP_ACCESS'] = $prefix.$this->_config['AUTH_GROUP_ACCESS'];
        if (C('AUTH_CONFIG')) {
            //可设置配置项 AUTH_CONFIG, 此配置项为数组。
            $this->_config = array_merge($this->_config, C('AUTH_CONFIG'));
        }
    }

    /**
     * @desc    权限验证
     *  =====================================================================@_@=====================================@_@
     * @access  public
     * @param   string    $name       需要验证的权限
     * @param   int       $uid        认证用户ID
     * @return  boolean               通过返回true 否则false
     * @author  leeong <9387524@gmail.com>
     */
    public function check($name, $uid)
    {
        if (!$this->_config['AUTH_ON'])
            return true;
        $authList = $this->getAuthList($uid); //获取用户需要验证的所有有效规则列表
        return in_array($name, $authList);
    }

    /**
     * @desc    获取用户权限列表
     *  =====================================================================@_@=====================================@_@
     * @access  protected
     * @param   int     $uid    认证用户ID
     * @return  array           所有name的array
     * @author  leeong <9387524@gmail.com>
     */
    protected function getAuthList($uid)
    {
        static $_authList = array(); //保存用户验证通过的权限列表
        if (isset($_authList[$uid])) {
            return $_authList[$uid];
        }
        if (isset($_SESSION['_AUTH_LIST_'.$uid])) {
            return $_SESSION['_AUTH_LIST_'.$uid];
        }

        //获取权限ids列表
        $ruleIds = $this->getRules($uid);

        if (empty($ruleIds)) {
            $_authList[$uid] = array();
            return array();
        }
        $map=array(
            'id'=>array('in',$ruleIds),
            'status'=>1,
        );

        //读取用户组所有权限name
        $rules = M()
            ->table($this->_config['AUTH_RULE'])
            ->where($map)
            ->field('name')
            ->select();
        if (empty($rules)) {
            $_authList[$uid] = array();
            return array();
        }
        $_SESSION['_AUTH_LIST_'.$uid] = $_authList[$uid] = $authList = array_map('array_shift', $rules);

        return $authList;
    }

    /**
     * @desc    获取用户的菜单列表
     *  =====================================================================@_@=====================================@_@
     * @access  public
     * @param   string  $uid    用户ID
     * @return  array           菜单二维序列数组
     * @author  leeong <9387524@gmail.com>
     */
    public function getMenu($uid)
    {
        static $_menuList = array(); //保存用户验证通过的菜单列表
        if (isset($_menuList[$uid])) {
            return $_menuList[$uid];
        }
        if (isset($_SESSION['_MENU_LIST_'.$uid])) {
            return $_SESSION['_MENU_LIST_'.$uid];
        }

        //获取权限ids列表
        $menuIds = $this->getRules($uid);

        if (empty($menuIds)) {
            $_menuList[$uid] = array();
            return array();
        }
        $map=array(
            'id'        =>  array('in',$menuIds),
            'is_link'   =>  1,
            'status'    =>  1,
        );

        //读取用户组所有权限name
        $menus = M()
            ->table($this->_config['AUTH_RULE'])
            ->where($map)
            ->field('id,name,title_code,pid,icon')
            ->order('pid,sort')
            ->select();
        if (empty($menus)) {
            $_menuList[$uid] = array();
            return array();
        }
        $menusSort = array_reduce($menus,
            function($v, $w) {
                $v[$w['pid']][] = $w;
                return $v;
            }
        );

        $_SESSION['_MENU_LIST_'.$uid] = $_menuList[$uid] = $menusSort;

        return $menusSort;
    }
    /**
     * @desc    获取用户的用户组
     *  =====================================================================@_@=====================================@_@
     * @access  public
     * @param   string  $uid    用户ID
     * @return  array           用户组二维数组
     * @author  leeong <9387524@gmail.com>
     */
    public function getGroups($uid)
    {
        static $groups = array();
        if (isset($groups[$uid]))
            return $groups[$uid];
        $user_groups = M()
            ->table($this->_config['AUTH_GROUP_ACCESS'] . ' a')
            ->where("a.id='$uid' and g.status='1'")
            ->join($this->_config['AUTH_GROUP']." g on a.group_id=g.id")
            ->field('g.id,title_code,rules')
            ->select();
        $groups[$uid]=$user_groups?:array();
        return $groups[$uid];
    }

    /**
     * @desc    获取用户的权限ids列表
     *  =====================================================================@_@=====================================@_@
     * @access  public
     * @param   string  $uid    用户ID
     * @return  array           规则ID一维数组
     * @author  leeong <9387524@gmail.com>
     */
    public function getRules($uid)
    {
        //读取用户所属用户组
        $groups = $this->getGroups($uid);
        //获取rules的id集合
        $ids = array_reduce($groups,
            function($v, $w) {
                $v = array_merge($v?$v:array(), explode(',', trim($w['rules'], ',')));
                return $v;
            }
        );
        //去重
        return array_unique($ids);
    }

}
