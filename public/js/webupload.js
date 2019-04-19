  //解决tab不显示时无法计算按钮大小问题
  function bindPicUploader(){        
      var $list , inputname,
          ratio = window.devicePixelRatio || 1,// 优化retina, 在retina下这个值是2        
          thumbnailWidth = 150 * ratio,// 缩略图大小
          thumbnailHeight = 150 * ratio;
      if(picuploader!=null) return;
      
      inputname='productimage';
      picuploader = WebUploader.create({        
          auto: true,// 选完文件后，是否自动上传。
             swf: '/public/plugins/webuploader/Uploader.swf',
          server: '{{u("home/gallery/upload",["_format":"json"])}}',// 文件接收服务端。
          pick: '#picPicker', 
          resize: false, // 不压缩image, 默认如果是jpeg，文件上传前会压缩一把再上传！
          // 只允许选择图片文件。
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
              name:file.name,
              src:'',
              hash:'',
              error:'',
              proc:0
          });
  
          // 创建缩略图
          // 如果为非图片文件，可以不用调用此方法。
          // thumbnailWidth x thumbnailHeight 为 100 x 100
          picuploader.makeThumb( file, function( error, src ) {
              /*if ( error ) {
                  $img.replaceWith('<span>不能预览</span>');
                  return;
              }*/
              updateDataFormList('picList','fileid',file.id,{'src':src});
          }, thumbnailWidth, thumbnailHeight );
      });
      // 文件上传过程中创建进度条实时显示。
      picuploader.on( 'uploadProgress', function( file, percentage ) {            
          updateDataFormList('picList','fileid',file.id,{'proc':(percentage * 100).toFixed(2)});
      });
      // 文件上传成功，给item添加成功class, 用样式标记上传成功。
      picuploader.on( 'uploadSuccess', function( file,response ) {
          $('#'+file.id ).addClass('upload-state-done');
          if(response.status=='ok'){
              console.log(response);
              updateDataFormList('picList','fileid',file.id,{'hash':response.data.file.hash});
          }
      });
      // 文件上传失败，显示上传出错。
      picuploader.on( 'uploadError', function( file ) {
          updateDataFormList('picList','fileid',file.id,{'error':'上传失败'});
      });
  }

    function updateDataFormList(field,fkey,fval,setkv){      
      for(var k in dataForm[field]){
        if(dataForm[field][k][fkey]==fval){
          for(var k1 in setkv){
            dataForm[field][k][k1]=setkv[k1];
          }                  
          dataForm.$forceUpdate();                   
          break;
        }
      }
    }