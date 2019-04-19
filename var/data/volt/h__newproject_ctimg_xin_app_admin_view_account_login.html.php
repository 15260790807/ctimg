<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \Xin\Model\Config::getValByKey('WEB_SITE_TITLE') ?></title>
    <?= $this->tag->stylesheetLink('css/bootstrap.min.css') ?> 
    <?= $this->tag->stylesheetLink('fontAwesome/css/font-awesome.css') ?>
    <?= $this->tag->stylesheetLink('css/animate.css') ?> 
    <?= $this->tag->stylesheetLink('css/style.css') ?>
</head>

<body class="gray-bg">
    <div class="middle-box text-center loginscreen animated fadeInDown">
        <div>
            <div>
                <h1 class="logo-name">CONSOLE</h1>
            </div>
            <h3>登录控制台</h3>
            <form class="m-t" role="form" method="post" action="">
                <input type='hidden' name='<?= $this->security->getTokenKey() ?>' value='<?= $this->security->getToken() ?>' />
                <input type='hidden' name='forward' value='<?= $forward ?>' />
                <div class="form-group">
                    <input type="text" name="username" class="form-control" placeholder="用户名" required="">
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="form-control" placeholder="密码" required="">
                </div>
                <button type="submit" class="btn btn-primary block full-width m-b">登录</button>
                <!--
                <a href="#">
                    <small>忘记密码?</small>
                </a>
                -->
            </form>
            <p class="m-t">
                <small>Copyright</strong> DCat &copy; 2017-2018</small>
            </p>
        </div>
    </div>
    <?= $this->tag->javascriptInclude('js/jquery-3.1.1.min.js') ?> 
    <?= $this->tag->javascriptInclude('js/bootstrap.min.js') ?>
</body>

</html>