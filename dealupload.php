<?php
//require_once __DIR__."/myautoload.php";
//include 'Loader.php'; // 引入加载器
//spl_autoload_register('Loader::autoload'); // 注册自动加载
function classLoader($class)
{
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . DIRECTORY_SEPARATOR .'osssrc'. DIRECTORY_SEPARATOR . $path . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('classLoader');
//require_once __DIR__."/vendor/OSS/OssClient.php";
//require_once __DIR__."/vendor/OSS/Core/OssException.php";
$con=mysqli_connect("127.0.0.1","root","","ctimg"); 
if (mysqli_connect_errno($con)) 
{ 
    echo "连接 MySQL 失败: " . mysqli_connect_error(); exit;
} 
 
// 执行查询
$lists=mysqli_query($con,"SELECT * FROM dc_upload_task Where `status`=0"); 
//$lists = mysqli_fetch_array($lists);
//没有就直接退出
$accessKeyId = "LTAIjXjQKCDZVCu9";
$accessKeySecret = "DyWPeToNGXknaN2u9aCpuajqcojzSs";
// Endpoint以杭州为例，其它Region请按实际情况填写。
$endpoint = "http://oss-us-east-1.aliyuncs.com";
// 存储空间名称
$bucket= "cfm-resources";
//阿里oss的图片域名http://cfm-resources.oss-us-east-1.aliyuncs.com

// 文件名称
//$object = "20180626001039.jpg";
// <yourLocalFile>由本地文件路径加文件名包括后缀组成，例如/users/local/myfile.txt
//$filePath = "D:/20180626001039.jpg";
$ossClient = new \Oss\OssClient($accessKeyId, $accessKeySecret, $endpoint);
//var_dump($lists);
foreach($lists as $key=>$value){
    $object=$value['name'];
    $filePath=__DIR__."/public/uploads/".$value['path'];
    try{
       // $ossClient->uploadFile($bucket, $object, $filePath);
        //更新状态
        $id=$value['id'];
        mysqli_query($con,"Update  dc_upload_task SET `status`=1 Where id=$id");
        echo "ok1";
    } catch(\OSS\Core\OssException $e) {
        printf(__FUNCTION__ . ": FAILED\n");
        printf($e->getMessage() . "\n");
        return;
    }
}
mysqli_close($con); 

?>