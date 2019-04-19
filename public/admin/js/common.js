function showBottomTip(message,type,topic){
    var op=toastr;
    if(location.href!=top.location.href){
        op=top.toastr;
    }
    if(!topic) topic='';
    switch(type){
        case 'error':op.error(message,topic);break;
        case 'warning':op.warning(message,topic);break;
        default :op.success(message,topic);break;
    }
}
function ajaxDeleteData(url, params,succCallback,errCallback) {
    swal({
        title: "数据删除",
        text: "数据删除将无法恢复，您确认要删除！",
        type: "warning",
        showCancelButton: true,
        cancelButtonText: "取消",
        confirmButtonColor: "#DD6B55",
        confirmButtonText: "是的，删除",
        closeOnConfirm: false,
        showLoaderOnConfirm: true
    }, function () {
        if (params == undefined || params == null) params = {};
            params['_format'] = 'json';
            $.post(url, params, function (result) {
                if (result.status == 'ok') {
                    if(succCallback!=undefined && succCallback!=null){
                        swal.close();
                        succCallback();
                    }else{                       
                        swal("成功", "选定数据已删除！", "success");
                        location.reload();
                    }
                } else {
                    if(errCallback!=undefined && errCallback!=null){
                        swal.close();
                        errCallback();
                    }else{                       
                        swal("删除选定数据失败", result.message, "error");
                    }
                }
            }, 'json');
    });
}
function repUrl(url,params){
    if(typeof(params)!='object') return url;
    for(var k in params){
        url=url.replace('__'+k+'__',encodeURI(params[k]));
    }
    return url;
}
function batchDel(url,succCallback,errCallback,boxid){
    if(boxid==undefined || boxid=='' || boxid==null) boxid='id[]';
    var o=$('input[name="'+boxid+'"]');
    if(!o.is('input')) return;
    var ids=[];
    o.each(function(){
        if($(this).is(':checked')){
            ids.push($(this).attr('value'));
        }
    });
    if(ids.length<1){
        swal("", "未选择操作对象", "error");
        return;
    }
    ajaxDeleteData(url, {id:ids},succCallback,errCallback);
}
function showDialog(title, options) {
    var _opt = options;
    if(_opt==undefined) {
        _opt={};
    }
    if (_opt.area==undefined) {
        _opt.area = ['600px', '400px'];
    }
    if (title) _opt.title = title;
    if (_opt.iframe) {
        _opt.type = 2;
        _opt.content = options.iframe.url;
    }
    if (location.href != top.location.href) {
        return top.layer.open(_opt);
    } else {
        return layer.open(_opt);
    }
}
function guid() {
    function S4() {
       return (((1+Math.random())*0x10000)|0).toString(16).substring(1);
    }
    return (S4()+S4()+"-"+S4()+"-"+S4()+"-"+S4()+"-"+S4()+S4()+S4());
}

toastr.options = {
    "closeButton": false,
    "debug": false,
    "progressBar": false,
    "preventDuplicates": false,
    "positionClass": "toast-bottom-right",
    "onclick": null,
    "showDuration": "400",
    "hideDuration": "1000",
    "timeOut": "5000",
    "extendedTimeOut": "1000",
    "showEasing": "swing",
    "hideEasing": "linear",
    "showMethod": "fadeIn",
    "hideMethod": "fadeOut"
};
$.validator.setDefaults({
    highlight: function(e) {
        $(e).closest(".form-group").removeClass("has-success").addClass("has-error")
    },
    success: function(e) {
        e.closest(".form-group").removeClass("has-error").addClass("has-success")
    },
    errorElement: "span",
    errorPlacement: function(e, r) {
        e.appendTo(r.is(":radio") || r.is(":checkbox") ? r.parent().parent().parent() : r.parent())
    },
    errorClass: "help-block m-b-none",
    validClass: "help-block m-b-none"
});
jQuery.validator.addMethod("pattern",function(value,element,param){
    var reg = $(element).attr('pattern');
    if(reg=='' || reg==undefined) return true;
    var exp = new RegExp(reg);     
    return this.optional(element) || exp.test(value);
} ,$.validator.format("pattern not match"));