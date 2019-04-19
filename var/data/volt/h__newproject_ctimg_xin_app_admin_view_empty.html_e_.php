a:7:{i:0;s:796:"
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
	";s:6:"header";N;i:1;s:62:"
</head>

<body class="gray-bg animated fadeInRight">
    ";s:7:"content";N;i:2;s:897:"

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
	";s:6:"script";N;i:3;s:22:"
</body>

</html>
";}