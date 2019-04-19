<?php $menuLists = $this->acl->getActiveResource(); ?><?php $this->_macros['findParent'] = function($__p = null) { if (isset($__p[0])) { $menus = $__p[0]; } else { if (isset($__p["menus"])) { $menus = $__p["menus"]; } else {  throw new \Phalcon\Mvc\View\Exception("Macro 'findParent' was called without parameter: menus");  } } if (isset($__p[1])) { $currentItem = $__p[1]; } else { if (isset($__p["currentItem"])) { $currentItem = $__p["currentItem"]; } else {  throw new \Phalcon\Mvc\View\Exception("Macro 'findParent' was called without parameter: currentItem");  } }  ?>
    <?php $lists = []; ?>
    <?php foreach ($menus as $item) { ?>
        <?php if ($item['id'] == $currentItem['parentid']) { ?>
            <?php $a = array_unshift($lists, $item); ?>
            <?php if ($item['parentid'] != 0) { ?>
                <?php $lists = array_merge($this->callMacro('findParent', [$menus, $item]), $lists); ?>
            <?php } ?>
            <?php break; ?>
        <?php } ?>
    <?php } ?>
    <?php return $lists; ?><?php }; $this->_macros['findParent'] = \Closure::bind($this->_macros['findParent'], $this); ?>

<?php 
$currentMenus=[];
function findParentInList($menus, $pid,&$currentMenus){
    foreach($menus as $item){
        if($item['id']==$pid){
            $item['link']=$item['url']!='' && strpos($item['url'],":")===false ? \Xin\Lib\Utils::url($item['url']):$item['url'];
            $currentMenus[]=$item;
            $item['parentid']!=0 &&  findParentInList($menus, $item['parentid'],$currentMenus);
        }
    }
}
$allMenuLists=\Xin\App\Admin\Model\Menu::find()->toArray(); 
foreach($allMenuLists as $item){
    $item['link']=$item['url']!='' && strpos($item['url'],":")===false ? \Xin\Lib\Utils::url($item['url']):$item['url'];
    if(($_GET['menuid'] && $item['id']==$_GET['menuid']) || (strlen($item['url'])>0 && strpos($_SERVER['REQUEST_URI'],$item['link'])!==false)){
        $currentMenus[]=$item;
        findParentInList($allMenuLists,$item['parentid'],$currentMenus);
        break;
    }
}
?>

<?php $menuids = []; ?>
<?php foreach ($currentMenus as $item) { ?>
    <?php $menuids = array_merge($menuids, [$item['id']]); ?>
<?php } ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \Xin\Model\Config::getValByKey('WEB_SITE_TITLE') ?></title>
    <?= $this->tag->stylesheetLink('css/bootstrap.min.css') ?> 
    <?= $this->tag->stylesheetLink('css/font-awesome.css') ?> 
    <?= $this->tag->stylesheetLink('css/animate.css') ?>     
    <?= $this->tag->stylesheetLink('css/plugins/sweetalert/sweetalert.css') ?>
    <?= $this->tag->stylesheetLink('css/style.css') ?>
    <?= $this->tag->stylesheetLink('css/plugins/toastr/toastr.min.css') ?>
    <?= $this->tag->stylesheetLink('css/plugins/awesome-bootstrap-checkbox/awesome-bootstrap-checkbox.css') ?>
    <?= $this->tag->stylesheetLink('css/dc.css') ?>
    <?= $this->tag->stylesheetLink('css/plugins/chosen/bootstrap-chosen.css') ?>
    <script>
    var BASE_URI='';
    </script>    
    
</head>

<body>
    <div id="wrapper">
        <!--左侧导航开始-->
        <nav class="navbar-default navbar-static-side" role="navigation" style="display:none;">
            <div class="sidebar-collapse">
                <ul class="nav metismenu" id="side-menu">            
                    <li class="nav-header">
                        <div class="dropdown profile-element">
                            <h4>控制台</h4>
                        </div>
                        <div class="logo-element">DC</div>
                    </li><?php $this->_macros['buildMenuTreeItem'] = function($__p = null) { if (isset($__p[0])) { $menus = $__p[0]; } else { if (isset($__p["menus"])) { $menus = $__p["menus"]; } else {  throw new \Phalcon\Mvc\View\Exception("Macro 'buildMenuTreeItem' was called without parameter: menus");  } } if (isset($__p[1])) { $depth = $__p[1]; } else { if (isset($__p["depth"])) { $depth = $__p["depth"]; } else {  throw new \Phalcon\Mvc\View\Exception("Macro 'buildMenuTreeItem' was called without parameter: depth");  } } if (isset($__p[2])) { $_menuids = $__p[2]; } else { if (isset($__p["_menuids"])) { $_menuids = $__p["_menuids"]; } else {  throw new \Phalcon\Mvc\View\Exception("Macro 'buildMenuTreeItem' was called without parameter: _menuids");  } }  ?>
                        <?php $depths = ['2' => 'second', '3' => 'third']; ?>
                        <?php if ($depth > 1) { ?><ul class="nav nav-<?= $depths[$depth] ?>-level"><?php } ?>
                        <?php foreach ($menus as $menu) { ?>
                        <li class="<?php if (in_array($menu['id'], $_menuids)) { ?> active <?php } ?>">
                            <a href="<?php if (!$menu['childs'] && $menu['link']) { ?><?= $menu['link'] ?>&menuid=<?= $menu['id'] ?><?php } ?>">
                                <?php if ($menu['settings']['icon']) { ?><i class="fa <?= $menu['settings']['icon'] ?>"></i><?php } ?>
                                <span class="nav-label" style="display: inline-block"> <?= $menu['title'] ?> </span>
                                <?php if ($menu['childs']) { ?><span class="fa arrow"></span><?php } ?>
                            </a>
                            <?php if ($menu['childs']) { ?>
                            <?= $this->callMacro('buildMenuTreeItem', [$menu['childs'], $depth + 1, $_menuids]) ?>
                            <?php } ?>
                        </li>
                        <?php } ?>
                        <?php if ($depth > 1) { ?></ul><?php } ?><?php }; $this->_macros['buildMenuTreeItem'] = \Closure::bind($this->_macros['buildMenuTreeItem'], $this); ?>
                    <?= $this->callMacro('buildMenuTreeItem', [\Xin\Lib\Utils::arrayToTree($menuLists), 1, $menuids]) ?>
                </ul>

            </div>
        </nav>
        <!--左侧导航结束-->

        
        <div id="page-wrapper" class="gray-bg" style="margin-left:0px;width:100%;">
            <div class="row border-bottom white-bg">
                <nav class="navbar navbar-static-top" role="navigation">                
                    <div class="navbar-header" style="display:none;">
                        <a class="navbar-minimalize minimalize-styl-2 btn btn-primary " href="#"><i class="fa fa-bars"></i></a>
                    </div>
                    <div class="navbar-collapse collapse" id="navbar">
                        <?php $uid = $this->auth->getTicket()['uid']; ?>
                        <?php $history = (function() use ($uid){$_mod=new \Xin\Module\order\DataTag();$_mod->setDI($this->di);return $_mod->orderItemHistoryList( ['user_id' => $uid]);})(); ?>
                        <!-- 下面这条代码以后优化 -->
                        <?php $msgPageResult = (function(){$_mod=new \Xin\Module\message\DataTag();$_mod->setDI($this->di);return $_mod->unreadSlice( ['pagesize' => 10]);})(); ?>
                        <ul class="nav navbar-top-links navbar-right">
                            <li class="dropdown">
                                <a class="dropdown-toggle count-info" data-toggle="dropdown" href="#">
                                    <i class="fa fa-envelope"></i>
                                    <span class="label label-warning"><?= $history['count'] ?></span>
                                    <!-- <span class="label label-warning"><?= $msgPageResult['count'] ?></span> -->
                                </a>
                              
                                <ul class="dropdown-menu dropdown-messages">
                                    <?php if ($history['count']) { ?>
                                    <li>
                                        <div class="dropdown-messages-box">
                                            <div class="media-body">
                                                <small class="pull-right">46h ago</small>
                                                <strong>Mike Loreipsum</strong> started following
                                                <strong>Monica Smith</strong>.
                                                <br>
                                                <small class="text-muted">3 days ago at 7:58 pm - 10.06.2014</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="divider"></li>
                                    <?php } ?>
                                    <!-- <?php foreach ($msgPageResult['items'] as $item) { ?>
                                    <li>
                                        <div class="dropdown-messages-box">
                                            <div class="media-body">
                                                <small class="pull-right">46h ago</small>
                                                <strong>Mike Loreipsum</strong> started following
                                                <strong>Monica Smith</strong>.
                                                <br>
                                                <small class="text-muted">3 days ago at 7:58 pm - 10.06.2014</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="divider"></li>
                                    <?php } ?> -->
                                    <li>
                                        <div class="text-center link-block">
                                            <a href="<?= \Xin\Lib\Utils::url('admin/message/list') ?>">
                                                <i class="fa fa-envelope"></i>
                                                <strong>Read All Messages</strong>
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                                    <i class="fa fa-user"></i> 注销
                                </a>
                                <ul class="dropdown-menu animated fadeInRight m-t-xs">
                                    <li><a href="<?= \Xin\Lib\Utils::url('admin/account/profile') ?>" data-index="1">个人资料</a></li>
                                    <li><a href="<?= \Xin\Lib\Utils::url('admin/account/logout') ?>">安全退出</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>

            

        </div>
    </div>
    
    <?= $this->tag->javascriptInclude('js/jquery-3.1.1.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/bootstrap.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/metisMenu/jquery.metisMenu.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/slimscroll/jquery.slimscroll.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/sweetalert/sweetalert.min.js') ?>
    <?= $this->tag->javascriptInclude('js/inspinia.js') ?> 
	<?= $this->tag->javascriptInclude('js/plugins/pace/pace.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/sweetalert/sweetalert.min.js') ?>    
    <?= $this->tag->javascriptInclude('js/plugins/toastr/toastr.min.js') ?>
    <?= $this->tag->javascriptInclude('js/jquery.form.js') ?>
    <?= $this->tag->javascriptInclude('js/plugins/validate/jquery.validate.min.js') ?>
    <?= $this->tag->javascriptInclude('js/plugins/validate/messages_zh.min.js') ?>
    <?= $this->tag->javascriptInclude('../plugins/layer/layer.js') ?>
	<?= $this->tag->javascriptInclude('js/vue.js') ?> 
	<?= $this->tag->javascriptInclude('js/common.js') ?> 
	
	


</body>
</html>
