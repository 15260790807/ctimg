/**
 * [Tool 二次封装工具类 echarts图表,ajax]   注意：使用时注意引入其他的第三方插件
 * @type {Object}
 * myecharts  基于jquery.min.js||echarts.min.js
 * ajax 使用了layer弹窗      
 */
var Tool = {
    myecharts:function(obj,config){
        var myecharts = {};
        var myechart = echarts.init(document.getElementById(obj));

        //颜色模板,根据情况拓展
        var colors = [
            {color:'#000',alabel:'#000',line_style:'#000',text_style:'#000'},
            {color:'#e5e4e4',alabel:'#000',line_style:'#2b55a2',text_style:'#e5e4e4'},
            {color:'#69b7ec',alabel:'#69b7ec',line_style:'#69b7ec',text_style:'#69b7ec'},
        ];
        config['tem'] = config['tem']?config['tem']:0;

        //基础图形配置项
        myecharts.option = {
            title: {text:config['title'], x: 'center', textStyle: {color:colors[config['tem']]['color'], fontSize: 25 }},   
            tooltip: {trigger: 'axis'}, 
            xAxis: {
                type: 'category',
                name:config["xAxisName"]?config["xAxisName"]:'',
                boundaryGap: true,
                data: config.xAxis?config.xAxis:[],
                //坐标颜色
                axisLabel: {show: true, textStyle: {color: colors[config['tem']]['alabel'] }}, 
                //左边线的颜色 width: '1' //坐标线的宽度
                axisLine: {lineStyle: {type: 'solid', color: colors[config['tem']]['line_style']}} 
            },
            yAxis: {
                type: 'value',
                name:config["yAxisName"]?config["yAxisName"]:'',
                axisLabel: {formatter: '{value}',textStyle: {color: colors[config['tem']]['alabel']}},
                //左边线的颜色 width: '1' //坐标线的宽度 
                splitLine: {
                    show: true, lineStyle: {color: [colors[config['tem']]['line_style']] } }, 
                    axisLine: {lineStyle: {type: 'solid', color:colors[config['tem']]['line_style'] }} 
                },
            grid: config['grid']?{top:config['grid'][0], left:config['grid'][1], right:config['grid'][2], bottom:config['grid'][3], containLabel: true }:{top:'5%', left:'5%', right:'5%', bottom:'5%', containLabel: true }, 
            series:config.series,
        },
        myecharts.bar = function(obj){
            myecharts.option.title = obj.title;
            myecharts.option.color = obj.color;
            myecharts.option.legend = obj.legend;
            myecharts.option.tooltip =   {
                trigger: 'axis',
                axisPointer : {            // 坐标轴指示器，坐标轴触发有效
                    type : 'shadow'        // 默认为直线，可选为：'line' | 'shadow'
                }
            },
            myechart.setOption(myecharts.option);
        },
        myecharts.line = function(obj){
            myecharts.option.legend = obj.legend;
            myecharts.option.color = obj.color;
            myechart.setOption(myecharts.option);
        },   
        myecharts.pie = function(obj){
            myecharts.option.xAxis = '';
            myecharts.option.yAxis = '';
            //图例浮动
            myecharts.option.tooltip = obj.tooltip?obj.tooltip:{trigger: 'item', formatter: "{a} <br/>{b} : {c} ({d}%)"};
            myecharts.option.color = obj.color;
            //图例
            myecharts.option.legend =  obj.legend;
            myecharts.option.title =  obj.title;
            myecharts.option.graphic =  obj.graphic;
            myechart.setOption(myecharts.option);
        },
        myecharts.liquidFill=function(obj){
            
            myechart.setOption(obj);
        }
        return myecharts;
    },
    http:function(){
        var http = {};
        http.request = function(url, data, success, cache, alone, async, type, dataType, error,lording){
            var type = type || 'post';//请求类型
            var dataType = dataType || 'json';//接收数据类型
            var async = async || true;//异步请求
            var alone = alone || false;//独立提交（一次有效的提交）
            var cache = cache || false;//浏览器历史缓存
            var ajaxStatus;
            var loading = loading || false;

            var success = success || function (res) {
                    /*console.log('请求成功');*/
                    setTimeout(function () {
                        layer.msg(res.data);//通过layer插件来进行提示信息
                    },10000);
                    if(res.status){//服务器处理成功
                        setTimeout(function () {
                            if(res.url){
                                location.replace(res.url);
                            }else{
                                location.reload(true);
                            }
                        },10000);
                    }else{//服务器处理失败
                        if(alone){//改变ajax提交状态
                            ajaxStatus = true;
                        }
                    }
                };
            var error = error || function (res) {
                layer.closeAll('loading');
                setTimeout(function () {
                    if(res.status == 404){
                        layer.msg('请求失败，请求未找到');
                    }
                    if(res.status == 503){
                        layer.msg('请求失败，服务器内部错误');
                    }
                    // else {
                    //     layer.msg('请求失败,网络连接超时');
                    // }
                    ajaxStatus = true;
                },500);
            };
            /*正常情况下1秒后可以再次多个异步请求，为true时只可以有一次有效请求（例如添加数据）*/
            if(!alone){
                setTimeout(function () {
                    ajaxStatus = true;
                },1000);
            }
            if (loading) {
               var beforeSend = function(){
                    layer.msg('加载中', {
                        icon: 16,
                        shade: 0.01
                    });
                }
            }

            $.ajax({
                'url': url,
                'data': data,
                'type': type,
                'dataType': dataType,
                'async': async,
                'success': success,
                'error': error, 
                'jsonpCallback': 'jsonp' + (new Date()).valueOf().toString().substr(-4),
                'beforeSend': beforeSend,
            });
        },
        http.post = function(url, data, success, cache, alone){
            var ajax = Tool.http();
            ajax.request(url, data, success, cache, alone, false, 'post','json');
        },
        http.get = function(url, success, cache, alone){
            var ajax = Tool.http();
            ajax.request(url, {}, success, alone, false, 'get','json');
        },
        http.form = function(){
            var ajax = Tool.http();
            cache = cache || true;
            var form = $(form);
            var url = form.attr('action');
            var data = form.serialize();
            ajax.request(url, data, success, cache, alone, false, 'post','json');
        }
        return http;
    },
    bootstrap_validate(){
        var validator = {};
        validator.init = function(obj){
        _obj = $(obj);
        _obj.bootstrapValidator({  
            message: '文本框不能为空',  
            feedbackIcons: {  
                valid: 'glyphicon tip glyphicon-ok',  
                invalid: 'glyphicon tip glyphicon-remove ',  
                validating: 'glyphicon tip glyphicon-refresh '  
            },   
            fields: {  
                name: {  
                    validators: {  
                        notEmpty: {  
                            message: '用户名称不能为空'  
                        },
                        stringLength: {
                            min: 2,
                            max: 32,
                            message: '用户名称不能小于2个字符大于32个字符'
                        },

                    }  
                },  
                id_card: {  
                    validators: {  
                        notEmpty: {  
                            message: '身份证不能为空'  
                        },
                        stringLength: {
                            min: 15,
                            max: 18,
                            message: '身份证不少于15位,不高于18位'
                        },
                        regexp: {
                          regexp: /(^\d{15}$)|(^\d{17}([0-9]|X)$)/,
                          message: '身份证格式错误'
                        },
                        callback: {
                             message: '获取生日失败',
                             callback:function(value, validator,$field){
                                if (value.length == 15) {
                                    var birthday = '19'+value.substr(6,2)+'-'+value.substr(8,2)+'-'+value.substr(10,2);
                                    var sex = value.substr(14,1);
                                }
                                if (value.length == 18) {
                                    var birthday = value.substr(6,4)+'-'+value.substr(10,2)+'-'+value.substr(12,2);                                         
                                    var sex = value.substr(16,1);
                                }
                                 $("#birthday").val(birthday);
                                 var _sex = $('input[name="sex"]');
                                 if (sex%2!==null) {
                                   _sex.val("男");
                                 }else{
                                   _sex.val("女");
                                 }
                                 return true;
                             }
                         },


                    }  
                },  
                phone: {  
                    validators: {  
                        notEmpty: {  
                            message: '电话号码不能为空'  
                        },
                        regexp: {
                          regexp: /^1[34578]\d{9}$/,
                          message: '电话格式错误'
                        }
                    }  
                },  
            }  
            }); 
        }
        return validator;
    },
    //html提示框，坐标数据data = [html:[],latitude_longitude:[]]
    map(obj,intPoint,event){
        var map = {};
       // 创建地址解析器实例
        var _map = new BMap.Map(obj);
        //初始化
        map.init = function(data,k){
            var cmap = Tool.map();
            // 添加带有定位的导航控件
            var navigationControl = new BMap.NavigationControl({
                // 靠左上角位置
                anchor: BMAP_ANCHOR_TOP_LEFT,
                // LARGE类型
                type: BMAP_NAVIGATION_CONTROL_LARGE,
                // 启用显示定位
                enableGeolocation: true
            });
            _map.addControl(navigationControl);
            var mapType2 = new BMap.MapTypeControl({anchor: BMAP_ANCHOR_TOP_RIGHT});
            _map.addControl(mapType2);          //左上角，默认地图控件 
            if (!intPoint) {
                var point = new BMap.Point(data[k][0],data[k][1]);
                _map.enableScrollWheelZoom();
                _map.centerAndZoom(point,15); 
                for(var i=0;i<data.length;i++){
                    var marker = new BMap.Marker(new BMap.Point(data[i][0],data[i][1]));  // 创建标注
                    var newpoint = new BMap.Point(data[i][0],data[i][1]);
                    var content = data[k][2]?data[k][2]:'';
                    _map.addOverlay(marker);
                    cmap.setPoint(content,marker,_map,point,event);
                }
            }else{
                for(var i=0;i<intPoint.length;i++){
                    var content = event=="click" ?intPoint[i][1]:'';
                    var point = intPoint[i][0];
                    cmap.getmapPoint(point,_map,content,event);
                }
            }
        },
         //绑定事件
        map.setPoint = function(content,marker,_map,newpoint,event){
            var opts = {
                width : 350,     // 信息窗口宽度
                height: 200,     // 信息窗口高度
                title : "详情" , // 信息窗口标题
               };
               var cmap = Tool.map();
               if (event == 'dragend') {
                    marker.enableDragging();
                    cmap.getpointinfo(marker);
                    var myGeo= new BMap.Geocoder();
                    marker.addEventListener("dragend", function (e) {
                        cmap.getpointinfo(marker);
                    })
               }else{
                    var infoWindow = new BMap.InfoWindow(content,opts);  // 创建信息窗口对象
                    _map.openInfoWindow(infoWindow,newpoint); //开启信息窗口

               }

        },
        //获取点位的周边信息
        map.getpointinfo = function(marker){
            var myGeo= new BMap.Geocoder();
            myGeo.getLocation(marker.point,function(rs){
                 var dragend_location = {};
                dragend_location.province = rs.addressComponents.province;
                dragend_location.latitude_longitude =[rs.point.lng,rs.point.lat].toString();
                dragend_location.city = rs.addressComponents.city;
                dragend_location.district = rs.addressComponents.district;
                dragend_location.street = rs.addressComponents.street;
                dragend_location.streetNumber = rs.addressComponents.streetNumber;
                $("#latitude_longitude").val(dragend_location.latitude_longitude);
                dragend_location = JSON.stringify(dragend_location); 
                $("#address").val(dragend_location);
                // console.log(rs.address); //地址描述(string)
                // console.log(rs.addressComponents);//结构化的地址描述(object)
                //console.log(rs.addressComponents.province); //省
                //console.log(rs.addressComponents.city); //城市
                // console.log(rs.addressComponents.district); //区县
                // console.log(rs.addressComponents.street); //街道
                // console.log(rs.addressComponents.streetNumber); //门牌号
                // console.log(rs.surroundingPois); //附近的POI点(array)
                // console.log(rs.business); //商圈字段，代表此点所属的商圈(string)
             });
        }
        //获取地址信息
        map.getmapPoint = function(point,_map,content,event){ 
            var cmap = Tool.map();
            var opts = {
                width : 350,     // 信息窗口宽度
                height: 100,     // 信息窗口高度
                title : "详情" , // 信息窗口标题
               };
            _map.enableScrollWheelZoom();
            if (event == 'dragend') {
                //设置初始坐标
                var lat_lng_int = {};
                // if (typeof(point)  ==  'object') {
                //     var latitude_longitude = intPoint.latitude_longitude.splice(',');
                //     lat_lng_int = {lng:parseFloat(latitude_longitude[0]),lat:parseFloat(latitude_longitude[1])};
                // }else{
                lat_lng_int =  {lng:119.323017,lat:26.062774};
                // }

                var centerpoint = new BMap.Point(lat_lng_int.lng,lat_lng_int.lat);
                _map.centerAndZoom(centerpoint,15);
                
                //根据地名获取详细的经纬度
                var localSearch = new BMap.LocalSearch(_map);
            　　localSearch.setSearchCompleteCallback(function (searchResult) {
            　　　　 var poi = searchResult.getPoi(0);
                    if(poi==undefined){
                        var poi = {};
                        poi.point = lat_lng_int;
                        var marker = new BMap.Marker(new BMap.Point(poi.point.lng, poi.point.lat));  // 创建标注，为要查询的地址对应的经纬度
                     }else{
                        var marker = new BMap.Marker(new BMap.Point(poi.point.lng, poi.point.lat));  // 创建标注，为要查询的地址对应的经纬度
                    }
                  $("#latitude_longitude").val(poi.point.lng+','+poi.point.lat);
                  _map.centerAndZoom(poi.point, 15);
                  cmap.getpointinfo(marker);
                  marker.enableDragging();
                  marker.addEventListener("dragend", function (e) {
                      $("#latitude_longitude").val(poi.point.lng+','+poi.point.lat);
                         cmap.getpointinfo(marker);
                    });
                    _map.addOverlay(marker);
                    
            　　});
                localSearch.search(point);

            }else if(event == 'click'){
                var infoWindow = new BMap.InfoWindow(content,opts);  // 创建信息窗口对象
                var centerpoint = new BMap.Point(119.323017,26.062774);
                _map.centerAndZoom(centerpoint,15);                
                var localSearch = new BMap.LocalSearch(_map);
            　　localSearch.setSearchCompleteCallback(function (searchResult) {
            　　　　 var poi = searchResult.getPoi(0);
                    _map.centerAndZoom(poi.point, 15);
                    var marker = new BMap.Marker(new BMap.Point(poi.point.lng, poi.point.lat));  // 创建标注，为要查询的地址对应的经纬度
                        marker.enableDragging();
                        marker.addEventListener("click", function (e) {
                            console.log(e);
                            this.openInfoWindow(infoWindow);    
                    });
                    _map.addOverlay(marker);
            　　});
                localSearch.search(point);
            }         
        }   
        return map;
    },
    // paging(){
    //     var page = {};
    //     page.init = function(obj,page,totalpage,totalSize,funcname) {
    //         $("#"+obj).paging({
    //             pageNo:page,
    //             totalPage:totalpage ,
    //             totalSize: totalSize,
    //             callback: function(num) {   
    //                 funcname(num);
    //             }
    //         })
    //     }
    // }
};

