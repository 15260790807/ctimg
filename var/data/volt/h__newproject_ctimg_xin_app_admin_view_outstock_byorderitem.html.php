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

            
<style type="text/css">
.main-content-box{
	width: 450px;
	display: inline-block;
}
.small-graph{
	height: 100%;
	background:blue;
	display: inline-block;
}
.camera-box{
	width:100%;
	background:gray;
	display: inline-block;
}
.preshow-box{
	width:448px;
	border:1px;
	display: inline-block;
	background:red;
}
.operate-row{
	width: 100%;
	text-align: right;
	border:1px;
	float:right;
	
}
.operate-box{
	margin-top:10px;
	width: 900px;
}
.get-picture{
	background: #169DB5;
	height: 30px;
	line-height: 30px;
	border:0px;
	border-radius: 2px;
	color:#fff;
}
.confirm-upload{
	background:#169DB5;
	height: 30px;
	line-height: 30px;
	border:0px;
	border-radius: 2px;
	color:#fff;
}
.small-graph{
	width: 150px;
	height: 448px;
	text-align: center;
	overflow-y: auto;
}
.smallimg-box{
	width: 150px;
	height: 448px;
	display: inline-block;
}
.search-row{
	width: 48%;
	text-align: right;
	border:1px;
	display: inline-block;
	margin-bottom:5px; 
	text-align: left;
}
.main-content{
	padding-left:20px;
}
.common-btn{
	color:#fff;border-radius: 2px;background:#169DB5;border:0px;height: 30px;
	min-width:77px;  
}
.search-input{
	height: 30px;
}
.show-order-detail{
	width: 900px;
}
ul {
    list-style: none;
    padding: 0;
}
.small-graph img{
	width: 100%;
}

.left-content-box{
	width:50%;
	height: 600px;
	display: inline-block;
	vertical-align: middle;
}
.right-content-box{
	width:49%;
	height: 600px;
	display: inline-block;
	vertical-align: middle;
}
.product-box1{
	height: 500px;
	overflow-y: auto;
	border:1px solid #000;
}
.product-stock{
	display:flex;
	align-items:flex-start;
	margin-top:3px;
}
.product-detail{
	width:48%;
	display:flex;
	align-items:flex-start;
}
.product-detail1{
	width:48%;
	display:flex;
	align-items:flex-start;
}
.outstock-box{
	padding:3px;
	width:48%;
	display: inline-block;
}
.product-name{
	padding:3px;
	width: 44%;
	display: inline-block;
}
.param-detail{
	width: 53%;
	padding:3px;
	display: inline-block;
}
.product-img{
	width:100%;
}
.outstock-img{
	width:97px;
	margin-bottom:3px;
	margin-left:3px;
}
.upload-btn{
	float:left;
}
.take-pic{
	float:right;
}
.title-stock{
	line-height:30px;
}
.title-name{
	width: 44%;
}
.info-detail{
	width:54%;
}
.ordersn-span{
	color:red;
}
.param-ul{
	margin-bottom:0px !important;
}
.selected-product{
	border:1px solid red;
}
.noticechoice-box{
	display: flex;
	justify-content : space-between;
	padding:5px; 
}
.notice-btn{
	color:#fff;
	background:gray;
	margin-top:10px;
	line-height: 30px;
	padding: 0px 5px;
	border-radius: 3px;
}
.btn-box{
	display: none;
	margin-top:10px;
	padding:5px;
}
.layui-layer-prompt .layui-layer-input {
    display: block;
    width: 246px;
    height: 53px;
    margin: 0 auto;
    line-height: 44px;
    padding-left: 10px;
    border: 1px solid #e6e6e6;
    color: #333;
	font-size:45px;
}
.imgDiv {
	
	display: inline-block;
	position: relative;
}

.imgDiv .deleteBtn {
	position: absolute;
	top: 0px;
	right: 0px;
	width: 28px;
	height: 28px;
}
.order-show{
	display: inline-block;
	width:50%;
}
</style>
<div class="left-content-box">
	<div  class="camera-box">
		<video id="video" width="" height="500" autoplay="autoplay" style="width: 100%;"></video>
	</div>
	<canvas id="canvas" width="" height="500" style="display:none;"></canvas>
	<div class="noticechoice-box">
			<span class="notice-btn">请在右边选择产品>></span>
			<span class="notice-btn">请在右边选择产品>></span>
		</div>
	<div class="btn-box">
		<button class="common-btn upload-btn" >从本地上传</button>
		<button class="common-btn take-pic" onclick="takePhoto()">拍照</button>
	</div>
	
</div>
<div class="right-content-box">
	<div class="ordersn-info">
		<div class="order-show">
			<h3>当前订单编号：<span class="ordersn-span">请先查询订单！</span></h3>
		</div>
		<div class="search-row">
				<button class="common-btn search-btn">切换订单</button>
			</div>
	</div>
	<div class="product-stock">
		<div class="product-detail1">
			<div  class="title-name">
				产品号及图稿
			</div>
			<div class="info-detail">
				  产品信息
		      </div>
		</div>
		<div class="title-outstock">
			出库图片
		</div>
	</div>
	<div class="product-box1">
		<!-- <div class="product-stock">
			<div class="product-detail selected-product">
				<div  class="product-name">
					桌布
					<img class="product-img" src="/admin/img/a1.jpg"/>
				</div>
				<div class="param-detail">
					  <ul class="text-left"> 
				       <li>面料: 防皱防阻燃300D </li> 
				       <li>交期: 24H </li> 
				       <li> <img src="" width="100px" /> </li> 
				       <li>尺寸: 4FT(82&quot;X106&quot;) </li> 
				      </ul> 
				      <ul class="text-left"> 
				       <li>面料: 防皱防阻燃300D防皱防阻燃300D防皱防阻燃300D </li> 
				       <li>交期: 24H </li> 
				       <li> <img src="" width="100px" /> </li> 
				       <li>尺寸: 4FT(82&quot;X106&quot;) </li> 
				      </ul> 
			      </div>
			</div>
			<div class="outstock-box">
				<div class="imgDiv">
					<img class="outstock-img" src="/admin/img/a1.jpg" />
					<a href="#">
						<img src="/admin/img/deletebtn.png" class="deleteBtn" data-itemid="1530" data-id="524"/>
					</a>
				</div>
				<div class="imgDiv">
					<img class="outstock-img" src="/admin/img/a1.jpg" />
					<a href="#">
						<img src="/admin/img/deletebtn.png" class="deleteBtn" />
					</a>
				</div>
				<div class="imgDiv">
					<img class="outstock-img" src="/admin/img/a1.jpg" />
					<a href="#">
						<img src="/admin/img/deletebtn.png" class="deleteBtn" />
					</a>
				</div>
				<div class="imgDiv">
					<img class="outstock-img" src="/admin/img/a1.jpg" />
					<a href="#">
						<img src="/admin/img/deletebtn.png" class="deleteBtn" />
					</a>
				</div>
			</div>
		</div> -->
	</div>
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
	
	
	<script>
        function getMedia() {
            let constraints = {
                video: {width: camerabox_width, height: img_height},
            };
            //获得video摄像头区域
            let video = document.getElementById("video");
            //这里介绍新的方法，返回一个 Promise对象
            // 这个Promise对象返回成功后的回调函数带一个 MediaStream 对象作为其参数
            // then()是Promise对象里的方法
            // then()方法是异步执行，当then()前的方法执行完后再执行then()内部的程序
            // 避免数据没有获取到
            if(navigator.getUserMedia){
                let promise = navigator.mediaDevices.getUserMedia(constraints);
                promise.then(function (MediaStream) {
                    video.srcObject = MediaStream;
                    video.play();    
                });
             }else{
                alert('本电脑没摄像头');
             }
        }
		var img_height=500,camerabox_width=500;
		var quality=0.8;
      function takePhoto() {
		//获得Canvas对象
		//获得Canvas对象,获取截图存放到canvas，并转成base64
		let video = document.getElementById("video");
		let canvas = document.getElementById("canvas");
		let ctx = canvas.getContext('2d');
		ctx.drawImage(video, 0, 0, camerabox_width, img_height);
		var mycanvas = document.getElementById("canvas");
		base64Data = mycanvas.toDataURL("image/jpeg",quality); //重要
		var html_s="<img style='height:'"+img_height+"'px;' src='"+base64Data+"' />"; 
		layer.open({
			type: 1,
			skin: 'layui-layer-rim', //加上边框
			area: ["50%", '590px'], //宽高
			shadeClose:true,
			btn: ['重新拍摄','确定']
			,btn1: function(index, layero){
				layer.close(index);
				return false;
			},
			btn2:function(index,layero){
				//执行上传函数
				handleSave();
				return;
			},
			content: html_s
		});
		return false;
	  }
	  var base64Data ="";
      function handleSave () {
        //导出base64格式的图片数据
        /* base64Data='';
       console.log(base64Data); */
        //封装blob对象
        var file = dataURItoBlob(base64Data,"first.png");
        //console.log(blob);
        //组装formdata
        var fd = new FormData();
		fd.append("image", file);//fileData为自定义
		fd.append("itemid",selected_itemid);
        //fd.append("fileName", "123jpeg");//fileName为自定义，名字随机生成或者写死，看需求
        //ajax上传，ajax的形式随意，JQ的写法也没有问题
        //需要注意的是服务端需要设定，允许跨域请求。数据接收的方式和<input type="file"/> 上传的文件没有区别
        var loading=layer.load(2,{
        	shade:false,
        	time:0,
        })
        $.ajax({  
        	url:"<?= \Xin\Lib\Utils::url("admin/picture/uploadsPicByitem", ['_format' => 'json']) ?>",
			data:fd,
			datatype:"json",
			processData : false,         // 告诉jQuery不要去处理发送的数据
        	contentType : false, 
        	type:'post',
        	success:function(res){
				layer.close(loading);
				
        		var res=eval('('+res+')');
        		if(res.status=="ok"){
					//将该元素添加到该dom中
					var item_s='<div class="imgDiv">'+
					'<img class="outstock-img" src="'+res.data.file.url+'" />'+
					'<a href="#">'+
						'<img src="/admin/img/deletebtn.png" class="deleteBtn" data-itemid="'+selected_itemid+'" data-id="'+res.data.file.id+'"/>'+
					'</a>'+
				'</div>';
        			$("#item"+selected_itemid).append(item_s);
        			toastr.info('上传成功');
        		}else{
        			toastr.error('上传异常');
        		}
        	}
        })
    };
   
	var search_s='';
	var selected_itemid="";
    $(document).ready(function(){
		//按下est关闭弹窗
		$('body',document).on('keyup', function (e) {	
			if (e.which === 27) {
			// console.log("按下esc");
				layer.closeAll();
			}
		});
		//弹窗层
		showOrderInput();
		//设置相机的大小
		/* camera-box
		video canvas   
		*/
		camerabox_width=$(".camera-box").width();
		$("#video").attr({width:camerabox_width});
		$("#canvas").attr({width:camerabox_width});

	    toastr.options = {
	        "closeButton": true, //是否显示关闭按钮
	        "debug": false, //是否使用debug模式
	        "showDuration": "300",//显示的动画时间
	        "hideDuration": "1000",//消失的动画时间
	        "timeOut": "5000", //展现时间
	        "extendedTimeOut": "1000",//加长展示时间
	        "showEasing": "swing",//显示时的动画缓冲方式
	        "hideEasing": "linear",//消失时的动画缓冲方式
	        "showMethod": "fadeIn",//显示时的动画方式
	        "hideMethod": "fadeOut" //消失时的动画方式
	    };
		getMedia();
		createImgListener();
	    //console.log("1");
		//本地上传，先判断是否有选中图片，然后打开链接
		$(".upload-btn").on('click',function(){ 
			if(selected_itemid==''){
				toastr.error("请先选择商品");return false;
			}
			layer.open({
				type: 2,
				title: false,
				move: false,
				closeBtn: 0,
				shade: 0.4,
				scrollbar:false,
				shadeclose:true,
				area: ['90%', '90%'],
				content: ["<?= \Xin\Lib\Utils::url("admin/order/shipmentgallery") ?>&id=" + selected_itemid + "&type=CFM"],
				btn: ['保存'],
				yes: function (index) {
					//有iframe
					var win = window.frames['layui-layer-iframe' + index];
					var img=win.$('img');
					var img_s="";
					console.log(img);
					for(var i=0;i<img.length;++i){
						img_s +='<div class="imgDiv">'+
							'<img class="outstock-img" src="'+$(img[i]).attr("src")+'" />'+
							'<a href="#">'+
								'<img src="/admin/img/deletebtn.png" class="deleteBtn" data-itemid="'+selected_itemid+'" data-id="'+$(img[i]).data("id")+'"/>'+
							'</a>'+
						'</div>';
					}
					$("#item"+selected_itemid).empty().html(img_s);
					//console.log(img);
					layer.close(index);
				}
			});
		})
		//结束
    	$(".search-btn").click(function(){
    		showOrderInput();
    	})
    });
	function showOrderInput(){
		var myprompt=layer.prompt({title: '请输入订单ID（订单号后4位编码）:', formType: 0,area: ['800px', '350px'],shadeclose:true,btn:false}, function(pass, index){
			layer.close(index);
		});
		setTimeout(function(){
			$(".layui-layer-input").on("input propertychange",function(){
				search_s=$(this).val();
				/* console.log(search_s.length);
				if(search_s.length==17){
					//调用根据产品信息查询的接口
					console.log("根据产品");return true;
				} */
				if(search_s.length>=4){
					requestByOrdersn();
					layer.close(myprompt);
				}
			})
		},300);
	}
	function createImgListener(){
		$("body").off("click",".outstock-img",function(){})
		//点击图片查看大图
		$("body").on("click", ".outstock-img", function (e) {
			layer.photos({photos: {"data": [{"src": e.target.src}]}});
		});
		$("body").on("click", ".deleteBtn", function (e) {
			console.log("点击了");
			//获取itemid，已经图片的id
			var that_obj=$(this).parent().parent();
			var itemid=$(this).data("itemid");
			var picid=$(this).data("id");
			swal(
			{
				title : "确定删除？",
				type : "warning",
				showCancelButton : true,
				confirmButtonColor : '#DD6B55',
				confirmButtonText : 'Yes',
				cancelButtonText : "No",
				closeOnConfirm : true
			},
			function(isConfirm) {
				if (isConfirm) {
					$.ajax({
						type:'post',
						data:{
							itemid:itemid,
							picid:picid,
						},
						url:"<?= \Xin\Lib\Utils::url("admin/outstock/deleteImg", ['format' => 'json']) ?>",
						success:function(res){
							//删除该图片的
							console.log(res);
							if(res.code=="200"){
								//获取成功
								toastr.info("删除成功");
								that_obj.remove();
							}
						}
					})
				}
			});
		});
	}
	function requestByOrdersn(){
		$.ajax({
    			url:"<?= \Xin\Lib\Utils::url("admin/outstock/searchOrder", ['format' => 'json']) ?>",
    			data:{
    				ordersn:search_s,
    			},
    			type:"post",
    			success:function(res){ 
    				//将查询的结果显示到左上角，并且将按钮变成蓝色；
    				if(res.code==200){
						toastr.success("你可以开始拍照上传出库图了");
						var ordersn=res.data.ordersn;
						//将的上传的dom隐藏
						$(".noticechoice-box").show();
						$(".btn-box").hide();
						//显示订单号
						$(".ordersn-span").html(ordersn);
						//开始编辑dom
						var item_s="";
						var orderitem=res.orderitem;
						//console.log(orderitem);
						for(var i=0;i<orderitem.length;++i){
							var index_data=orderitem[i];
							//console.log(index_data);
							//获取该产品的属性
							var option_s='';
							var options=index_data.options;
							//console.log(options);
							for(var key in options){
								//console.log(options[key]);
								if(options[key].title!='artwork'){

									option_s +='<li>'+options[key].title_cn+': '+(options[key].value_cn ||options[key].value)+' </li>';
								}
							}
							var outstockimg=index_data.outstockimg;
							var img_s="";
							for(var j=0;j<outstockimg.length;++j){
								img_s +='<div class="imgDiv">'+
					'<img class="outstock-img" src="'+outstockimg[j].path+'" />'+
					'<a href="#">'+
						'<img src="/admin/img/deletebtn.png" class="deleteBtn" data-itemid="'+index_data.id+'" data-id="'+outstockimg[j].id+'"/>'+
					'</a>'+
				'</div>'; 
							}
							item_s +='<div class="product-stock">'+
								'<div class="product-detail" data-itemid="'+index_data.id+'">'+
									'<div  class="product-name">'+
											index_data.product_cache.title_cn+
										'<img class="product-img" src="'+index_data.artworkimg+'"/>'+
									'</div>'+
									'<div class="param-detail">'+
										'<ul class="text-left param-ul">' +
											option_s+ 
										'</ul>' +
									'</div>'+
								'</div>'+
								'<div class="outstock-box" data-itemid="'+index_data.id+'" id="item'+index_data.id+'">'+img_s+
								'</div>'+
							'</div>';
						}
						$(".product-box1").html(item_s);
						//取消事件，并添加时间
						$(".product-detail").off("click");
						$(".product-detail").on("click",function(){
							var rs=$(this).hasClass('selected-product');
							if(!rs){
								selected_itemid=$(this).data('itemid');
								$('.product-detail').removeClass('selected-product');
								$(this).addClass("selected-product");
							}
							//判断是否已经显示了
							if($('.btn-box').is(':hidden')){
								$('.btn-box').show();
								$('.noticechoice-box').hide();
							}
						})
    				}else{
    					toastr.error(res.msg); 
    				}
    			}
    		})
	}
	function dataURItoBlob(dataurl, filename) { 
		//将base64转换为文件
        var arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
        while(n--){
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, {type:mime});
    }
</script>


</body>
</html>
