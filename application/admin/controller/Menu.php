<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2017 河源市卓锐科技有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\common\builder\ZBuilder;
use app\admin\model\Module as ModuleModel;
use app\admin\model\Menu as MenuModel;
use think\Cache;

/**
 * 节点管理
 * @package app\admin\controller
 */
class Menu extends Admin
{
    /**
     * 节点首页
     * @param string $group 分组
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function index($group = 'admin')
    {
        // 保存模块排序
        if ($this->request->isPost()) {
            $modules = $this->request->post('sort/a');
            if ($modules) {
                foreach ($modules as $key => $module) {
                    $data[] = [
                        'id'   => $module,
                        'sort' => $key + 1
                    ];
                }
                $MenuModel = new MenuModel();
                if (false !== $MenuModel->saveAll($data)) {
                    return $this->success('保存成功');
                } else {
                    return $this->error('保存失败');
                }
            }
        }

        cookie('__forward__', $_SERVER['REQUEST_URI']);
        // 配置分组信息
        $list_group = MenuModel::getGroup();
        foreach ($list_group as $key => $value) {
            $tab_list[$key]['title'] = $value;
            $tab_list[$key]['url']  = url('index', ['group' => $key]);
        }

        // 模块排序
        if ($group == 'module-sort') {
            $map['status'] = 1;
            $map['pid']    = 0;
            $modules = MenuModel::where($map)->order('sort,id')->column('icon,title', 'id');
            $this->assign('modules', $modules);
        } else {
            // 获取节点数据
            $data_list = MenuModel::getMenusByGroup($group);

            $max_level = $this->request->get('max', 0);

            $this->assign('menus', $this->getNestMenu($data_list, $max_level));
        }

        $this->assign('tab_nav', ['tab_list' => $tab_list, 'curr_tab' => $group]);
        $this->assign('page_title', '节点管理');
        return $this->fetch();
    }

    /**
     * 新增节点
     * @param string $module 所属模块
     * @param string $pid 所属节点id
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function add($module = 'admin', $pid = '')
    {
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            // 验证
            $result = $this->validate($data, 'Menu');
            // 验证失败 输出错误信息
            if(true !== $result) return $this->error($result);

            // 顶部节点url检查
            if ($data['pid'] == 0 && $data['url_value'] == '' && $data['url_type'] == 'module') {
                return $this->error('顶级节点的节点链接不能为空');
            }

            if ($menu = MenuModel::create($data)) {
                Cache::clear();
                // 记录行为
                $details = '所属模块('.$data['module'].'),所属节点ID('.$data['pid'].'),节点标题('.$data['title'].'),节点链接('.$data['url_value'].')';
                action_log('menu_add', 'admin_menu', $menu['id'], UID, $details);
                return $this->success('新增成功', cookie('__forward__'));
            } else {
                return $this->error('新增失败');
            }
        }

        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('新增节点')
            ->addLinkage('module', '所属模块', '', ModuleModel::getModule(), $module, url('ajax/getModuleMenus'), 'pid')
            ->addFormItems([
                ['select', 'pid', '所属节点', '所属上级节点', MenuModel::getMenuTree(0, '', $module), $pid],
                ['text', 'title', '节点标题'],
                ['radio', 'url_type', '链接类型', '', ['module' => '模块链接', 'link' => '普通链接'], 'module']
            ])
            ->addFormItem(
                'text',
                'url_value',
                '节点链接',
                "可留空，如果是模块链接，请填写<code>模块/控制器/操作</code>，如：<code>admin/menu/add</code>。如果是普通链接，则直接填写url地址，如：<code>http://www.dolphinphp.com</code>"
            )
            ->addRadio('url_target', '打开方式', '', ['_self' => '当前窗口', '_blank' => '新窗口'], '_self')
            ->addIcon('icon', '图标', '导航图标')
            ->addRadio('online_hide', '网站上线后隐藏', '关闭开发模式后，则隐藏该菜单节点', ['否', '是'], 0)
            ->addText('sort', '排序', '', 100)
            ->fetch();
    }

    /**
     * 编辑节点
     * @param int $id 节点ID
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function edit($id = 0)
    {
        if ($id === 0) return $this->error('缺少参数');

        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();

            // 验证
            $result = $this->validate($data, 'Menu');
            // 验证失败 输出错误信息
            if(true !== $result) return $this->error($result);

            // 顶部节点url检查
            if ($data['pid'] == 0 && $data['url_value'] == '' && $data['url_type'] == 'module') {
                return $this->error('顶级节点的节点链接不能为空');
            }

            // 验证是否更改所属模块，如果是，则该节点的所有子孙节点的模块都要修改
            $map['id'] = $data['id'];
            $map['module'] = $data['module'];
            if (!MenuModel::where($map)->find()) {
                MenuModel::changeModule($data['id'], $data['module']);
            }

            if (MenuModel::update($data)) {
                Cache::clear();
                // 记录行为
                $details = '节点ID('.$id.')';
                action_log('menu_edit', 'admin_menu', $id, UID, $details);
                return $this->success('编辑成功', cookie('__forward__'));
            } else {
                return $this->error('编辑失败');
            }
        }

        // 获取数据
        $info = MenuModel::get($id);

        // 使用ZBuilder快速创建表单
        return ZBuilder::make('form')
            ->setPageTitle('编辑节点')
            ->addFormItem('hidden', 'id')
            ->addLinkage('module', '所属模块', '', ModuleModel::getModule(), '', url('ajax/getModuleMenus'), 'pid')
            ->addFormItem('select', 'pid', '所属节点', '所属上级节点', MenuModel::getMenuTree(0, '', $info['module']))
            ->addFormItem('text', 'title', '节点标题')
            ->addFormItem('radio', 'url_type', '链接类型', '', ['module' => '模块链接', 'link' => '普通链接'], 'module')
            ->addFormItem(
                'text',
                'url_value',
                '节点链接',
                "可留空，如果是模块链接，请填写<code>模块/控制器/操作</code>，如：<code>admin/menu/add</code>。如果是普通链接，则直接填写url地址，如：<code>http://www.dolphinphp.com</code>"
            )
            ->addRadio('url_target', '打开方式', '', ['_self' => '当前窗口', '_blank' => '新窗口'], '_self')
            ->addIcon('icon', '图标', '导航图标')
            ->addRadio('online_hide', '网站上线后隐藏', '关闭开发模式后，则隐藏该菜单节点', ['否', '是'])
            ->addText('sort', '排序', '', 100)
            ->setFormData($info)
            ->fetch();
    }

    /**
     * 删除节点
     * @param array $record 行为日志内容
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function delete($record = [])
    {
        $id = $this->request->param('id');
        $menu = MenuModel::where('id', $id)->find();

        if ($menu['system_menu'] == '1')  return $this->error('系统节点，禁止删除');

        // 获取该节点的所有后辈节点id
        $menu_childs = MenuModel::getChildsId($id);

        // 要删除的所有节点id
        $all_ids = array_merge([(int)$id], $menu_childs);

        // 删除节点
        if (MenuModel::destroy($all_ids)) {
            Cache::clear();
            // 记录行为
            $details = '节点ID('.$id.'),节点标题('.$menu['title'].'),节点链接('.$menu['url_value'].')';
            action_log('menu_delete', 'admin_menu', $id, UID, $details);
            return $this->success('删除成功');
        } else {
            return $this->error('删除失败');
        }
    }

    /**
     * 保存节点排序
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function save()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!empty($data)) {
                $menus = $this->parseMenu($data['menus']);
                foreach ($menus as $menu) {
                    if ($menu['pid'] == 0) {
                        continue;
                    }
                    MenuModel::update($menu);
                }
                Cache::clear();
                return $this->success('保存成功');
            } else {
                return $this->error('没有需要保存的节点');
            }
        }
        return $this->error('非法请求');
    }

    /**
     * 递归解析节点
     * @param array $menus 节点数据
     * @param int $pid 上级节点id
     * @author 蔡伟明 <314013107@qq.com>
     * @return array 解析成可以写入数据库的格式
     */
    private function parseMenu($menus = [], $pid = 0)
    {
        $sort   = 1;
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id'   => (int)$menu['id'],
                'pid'  => (int)$pid,
                'sort' => $sort,
            ];
            if (isset($menu['children'])) {
                $result = array_merge($result, $this->parseMenu($menu['children'], $menu['id']));
            }
            $sort ++;
        }
        return $result;
    }

    /**
     * 获取嵌套式节点
     * @param array $lists 原始节点数组
     * @param int $pid 父级id
     * @param int $max_level 最多返回多少层，0为不限制
     * @param int $curr_level 当前层数
     * @author 蔡伟明 <314013107@qq.com>
     * @return string
     */
    private function getNestMenu($lists = [], $max_level = 0, $pid = 0, $curr_level = 1)
    {
        $result = '';
        foreach ($lists as $key => $value) {
            if ($value['pid'] == $pid) {
                $disable  = $value['status'] == 0 ? 'dd-disable' : '';

                // 组合节点
                $result .= '<li class="dd-item dd3-item '.$disable.'" data-id="'.$value['id'].'">';
                $result .= '<div class="dd-handle dd3-handle">拖拽</div><div class="dd3-content"><i class="'.$value['icon'].'"></i> '.$value['title'];
                if ($value['url_value'] != '') {
                    $result .= '<span class="link"><i class="fa fa-link"></i> '.$value['url_value'].'</span>';
                }
                $result .= '<div class="action">';
                $result .= '<a href="'.url('add', ['module' => $value['module'], 'pid' => $value['id']]).'" data-toggle="tooltip" data-original-title="新增子节点"><i class="list-icon fa fa-plus fa-fw"></i></a><a href="'.url('edit', ['id' => $value['id']]).'" data-toggle="tooltip" data-original-title="编辑"><i class="list-icon fa fa-pencil fa-fw"></i></a>';
                if ($value['status'] == 0) {
                    // 启用
                    $result .= '<a href="javascript:void(0);" data-ids="'.$value['id'].'" class="enable" data-toggle="tooltip" data-original-title="启用"><i class="list-icon fa fa-check-circle-o fa-fw"></i></a>';
                } else {
                    // 禁用
                    $result .= '<a href="javascript:void(0);" data-ids="'.$value['id'].'" class="disable" data-toggle="tooltip" data-original-title="禁用"><i class="list-icon fa fa-ban fa-fw"></i></a>';
                }
                $result .= '<a href="'.url('delete', ['id' => $value['id'], 'table' => 'admin_menu']).'" data-toggle="tooltip" data-original-title="删除" class="ajax-get confirm"><i class="list-icon fa fa-times fa-fw"></i></a></div>';
                $result .= '</div>';

                if ($max_level == 0 || $curr_level != $max_level) {
                    unset($lists[$key]);
                    // 下级节点
                    $children = $this->getNestMenu($lists, $max_level, $value['id'], $curr_level + 1);
                    if ($children != '') {
                        $result .= '<ol class="dd-list">'.$children.'</ol>';
                    }
                }

                $result .= '</li>';
            }
        }
        return $result;
    }

    /**
     * 启用节点
     * @param array $record 行为日志
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function enable($record = [])
    {
        $id      = input('param.ids');
        $menu    = MenuModel::where('id', $id)->find();
        $details = '节点ID('.$id.'),节点标题('.$menu['title'].'),节点链接('.$menu['url_value'].')';
        return $this->setStatus('enable', ['menu_enable', 'admin_menu', $id, UID, $details]);
    }

    /**
     * 禁用节点
     * @param array $record 行为日志
     * @author 蔡伟明 <314013107@qq.com>
     * @return mixed
     */
    public function disable($record = [])
    {
        $id      = input('param.ids');
        $menu    = MenuModel::where('id', $id)->find();
        $details = '节点ID('.$id.'),节点标题('.$menu['title'].'),节点链接('.$menu['url_value'].')';
        return $this->setStatus('disable', ['menu_disable', 'admin_menu', $id, UID, $details]);
    }
}
