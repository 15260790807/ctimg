
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
	
<?= $this->tag->stylesheetLink('../plugins/webuploader/webuploader.css') ?>

<style>
    .shipment{
        display: flex;
        flex-direction: row;
    }
    .shipment-item{
        width:100px;
        height:125px;
        display: flex;
        flex-direction: column;
        margin: 5px;
    }
    .text-align-center{
        text-align: center;
    }
    .shipment-item img{
        width: 100%;
        height: 100px;
        border: 1px solid #2f4050;
    }
    .shipment-item span{
        width: 100%;
        height: 100%;
        line-height: 25px;
    }
	.progress-bar{
		height: 20px;
	}
</style>

</head>

<body class="gray-bg animated fadeInRight">
    
<br><br><br><br><br>
<form class="container" id="dataForm" method="POST" action="">
	<?php if ($id) { ?>
	<input type="hidden" name="id" value="<?= $id ?>">
	<?php } else { ?>
	<input type="hidden" name="ordersn" value="<?= $ordersn ?>">
	<?php } ?>
	<input type="hidden" name="save" id="save">
	<div id="picPicker">选择图片</div>
        <div class="shipment">
            <div class="shipment-item text-align-center"  v-for="(file,index) in picList" :id="file.fileid"   :data-id="file.id" >
                <input type="hidden" name="shipment[]" :value="file.id">
                <img :src="file.path" :data-id="file.id">
				<span class="btn btn-danger" >删除</span>
				<div class="progress">
					<div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 60%;">
					  <span class="sr-only">60% Complete</span>
					</div>
				  </div>
			</div>
			
        </div>
</form>



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
	
<?= $this->tag->javascriptInclude('../plugins/webuploader/webuploader.min.js') ?>

<script>
	var picList = <?php if ($objectlist) { ?> <?= json_encode($objectlist) ?>  <?php } else { ?>[] <?php } ?>;
	var dataForm = new Vue({
        delimiters: ['${', '}'],
        el: '#dataForm',
        data: {
            picList: picList,
        }
	});
	var picuploader,fileuploader;// Web Uploader实例

	$(document).ready(function () {
		$('.shipment').on('click','span.btn', function () {
			var id = $(this).parent().attr('data-id');
			parent.$('img[data-id="'+id+'"]').remove();
			$('.shipment-item[data-id="' + id + '"]').remove();
			$('#save').val('true');
			var data;
			data=$('#dataForm').serialize();
			$.ajax({
				url: "<?= \Xin\Lib\Utils::url("admin/order/shipmentgallery", ['_format' => 'json', 'save' => 'true', 'type' => $type, 'id' => $id, 'header' => 'false']) ?>",
				type: "post",
				dataType: "json",
				data:data,
				success: function(res) {
					if (res.status == 'ok') {
						swal("删除成功");
					}else{
						swal("删除失败", res.message[0], "error");
					}
				}
			});
		})
		picuploader = WebUploader.create({
			auto: true, // 选完文件后，是否自动上传。
			swf: '<?= $this->url->getStatic('../plugins/webuploader/Uploader.swf') ?>', // swf文件路径
			server: '<?= \Xin\Lib\Utils::url('admin/picture/uploadsPic', ['_format' => 'json']) ?>', // 文件接收服务端。
			pick: '#picPicker',
			resize: false, // 不压缩image, 默认如果是jpeg，文件上传前会压缩一把再上传！
			accept: {
                title: 'Images',
                extensions: 'gif,jpg,jpeg,bmp,png',
                mimeTypes: 'image/*'
			}
        });
        
        // 当有文件添加进来的时候
        picuploader.on( 'fileQueued', function( file ) {
            dataForm.picList.push({
                fileid:file.id,
                id:'',
                path:'',
			});
        });
		// 文件上传过程中创建进度条实时显示。
		picuploader.on('uploadProgress', function (file, percentage) {
			$('#'+file.id+' .progress').css({
				'overflow':'inherit'	
			}).find('.progress-bar').width(parseInt(percentage * 100)+'%');;
			// $('#'+file.id+' .progress-bar')
			// $('#'+file.id+' .only').text(parseInt(percentage * 100)+'%');
			// if(parseInt(percentage * 100)==100){
			//     $('#'+file.id+' .progress').hide();
			//     $('#'+file.id+' .fa-check').show();
			// }
		});

		picuploader.on('error', function (type) {
			console.log(type);
			if (type === "F_EXCEED_SIZE") {
				layer.msg("Please upload a file with maximum size of 200M!");
			}
		});

		// 文件上传成功，给item添加成功class, 用样式标记上传成功。
		picuploader.on('uploadSuccess', function (file, response) {
			debugger
			console.log(response);
			picuploader.removeFile(file);
			$('#' + file.id).addClass('upload-state-done');
			if(typeof response!=='undefined'){
				if (response.status == 'ok') {
					updateDataFormList(file.id,{
						id:response.data.file.id,
						path:response.data.file.url,
					});
				}
			}
		});
	});
	function updateDataFormList(fval,setkv,type='<?= $type ?>') {
		if(fval){
			var data;
			for (var k in dataForm['picList']) {
				if (dataForm['picList'][k]['fileid'] == fval) {
					for (var k1 in setkv) {
						dataForm['picList'][k][k1] = setkv[k1];
					}
					data=JSON.stringify(dataForm['picList']);
					dataForm.$forceUpdate();
					break;
				}
			}
			$('#'+fval+' .progress').css({
				'overflow':'auto'	
			})
			$.ajax({
				url: "<?= \Xin\Lib\Utils::url("admin/order/shipmentgallery", ['_format' => 'json', 'save' => 'true', 'type' => $type, 'id' => $id, 'ordersn' => $ordersn, 'header' => 'true']) ?>",
				type: "post",
				dataType: "json",
				data:data,
				success: function(res) {
					if (res.status !== 'ok') {
						swal("保存图片失败", res.message[0], "error");
					}
				}
			});
		}
		
		
	}
</script>

</body>

</html>
