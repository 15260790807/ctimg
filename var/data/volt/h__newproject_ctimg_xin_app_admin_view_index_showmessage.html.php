
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \Xin\Model\Config::getValByKey('WEB_SITE_TITLE') ?></title>
    <?= $this->tag->stylesheetLink('css/bootstrap.min.css') ?> 
    <?= $this->tag->stylesheetLink('fontAwesome/css/font-awesome.css') ?>
    <?= $this->tag->stylesheetLink('css/animate.css') ?>     
    <?= $this->tag->stylesheetLink('css/plugins/sweetalert/sweetalert.css') ?>
    <?= $this->tag->stylesheetLink('css/style.css') ?>
    <?= $this->tag->stylesheetLink('css/plugins/toastr/toastr.min.css') ?>
    <?= $this->tag->stylesheetLink('css/plugins/awesome-bootstrap-checkbox/awesome-bootstrap-checkbox.css') ?>
    <?= $this->tag->stylesheetLink('css/dc.css') ?>
	
<style>
.container{  
    display:table;  
    height:100%; width:100% 
}  
.row{  
    display: table-cell;  
    vertical-align: middle;  
}
</style> 


</head>

<body class="gray-bg animated fadeInRight">
    
<?php $levelcss = ($level == 'error' ? 'danger' : $level); ?>
<?php $txt = ['succ' => '成功', 'info' => '信息', 'warning' => '提示', 'error' => '错误']; ?>
<?php $link = ['goback' => '<i class="icon-hand-left home-icon"></i>上一页', 'close' => '关闭', 'home' => '<i class="icon-home home-icon"></i>首页']; ?>
<div class="container">
        <div class="row">
            <div class="col-xs-2 col-sm-4"></div>
            <div class="col-xs-8 col-sm-4">
                <div class=" white-bg p-sm b-r-sm">
                        <h1 class="grey lighter smaller">
                            <span class="blue bigger-125"><i class="icon-check"></i>&nbsp;提示</span>
                        </h1>

                        <hr>
                        <h3 class="lighter smaller">
                            <span class="<?php if ($level == 'succ') { ?>green<?php } elseif ($level == 'error') { ?>red<?php } elseif ($level == 'info') { ?>blue<?php } ?> bigger-125">
                                <?php foreach ($message as $msg) { ?>
                                <p><?= $msg ?></p>
                                <?php } ?>
                            </span>
                        </h3>

                        <div class="center linkgroup">
                            <?php foreach ($forwards as $k => $v) { ?>
                            <?php if ($link[$v]) { ?>
                            <button class="btn btn btn-primary" onclick="golink('<?= strip_tags($v) ?>')"><?= $link[$v] ?></button>
                            <?php } else { ?>
                                <?php if (!is_numeric($k)) { ?>
                                <button class="btn btn btn-primary" onclick="golink('<?= strip_tags($v) ?>')"><?= $k ?></button>
                                <?php } else { ?>
                                <button class="btn btn btn-primary" onclick="golink('<?= strip_tags($v) ?>')">返回</button>
                                <?php } ?>
                            <?php } ?>
                            <?php } ?>
                        </div>
                </div>
            </div>
            <div class="col-xs-2 col-sm-4"></div>
    </div>
</div>



    <?= $this->tag->javascriptInclude('js/jquery-3.1.1.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/bootstrap.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/metisMenu/jquery.metisMenu.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/slimscroll/jquery.slimscroll.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/plugins/sweetalert/sweetalert.min.js') ?>
    <?= $this->tag->javascriptInclude('js/plugins/toastr/toastr.min.js') ?>
    <?= $this->tag->javascriptInclude('js/jquery.form.js') ?>
    <?= $this->tag->javascriptInclude('js/plugins/validate/jquery.validate.min.js') ?>
    <?= $this->tag->javascriptInclude('js/plugins/validate/messages_zh.min.js') ?>
    <?= $this->tag->javascriptInclude('../plugins/layer/layer.js') ?>
	<?= $this->tag->javascriptInclude('js/vue.js') ?> 
	<?= $this->tag->javascriptInclude('js/common.js') ?> 
	
<script type="text/javascript">
    var isFramed = parent.location.href != location.href;
    var isDialog = false;
    if (isFramed) {
        var o = parent.$('#dialog');
        if (o.is('div') && !o.is(':hidden')) {
            isDialog = true;
        }
    }
    if ('success' == '<?= $level ?>' && isDialog && '<?= join($forwards, ', ') ?>' == 'goback,home') {
        parent.closeDialog();
        parent.location.reload();
        showBottomTip("<?= addslashes(join($message, '<br/>')) ?>");
//        top.messageTip('<?= addslashes(join($message, '<br/>')) ?>')
    }

    function golink(url) {
        if (url == 'home') {
            top.location.href = '<?= \Xin\Lib\Utils::url($this->dispatcher->getModuleName()."/".$this->dispatcher->getControllerName()."/".$this->dispatcher->getActionName(),$_GET) ?>';
        } else if (url == 'goback' || url == '') {
            self.location=document.referrer;
        } else if (url == 'close') {
            if (isDialog) {
                parent.closeDialog();
                parent.location.reload();
            } else {
                top.window.close();
            }
        } else if (url=='referer') {
            location.href = document.referrer;
        } else if (url.indexOf('://')) {
            location.href = url;
        }
    }
    var delay=3;
    var linktxt=$('button').eq(0).text();
    $(document).ready(function(){
        $('button.btn').eq(0).text(linktxt+"("+delay+")");
        setInterval(function(){
            if(--delay<=0){
                $('button.btn').eq(0).click();}
            else{
                $('button.btn').eq(0).text(linktxt+"("+delay+")");
            }
        },1000);
    })
</script>

</body>

</html>
