<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>question_HT_GK</title>
    <script src="/js/jquery.min.js"></script>
    <style>
        .div-HT{ float:left;width:45%;}
        .div-GK{ float:right;width:45%;}

        img {
            width: 500px;
            height: 150px
        }
        .btn{
            height:1.7em;
            font-size:1em;
            border:1px solid #c8cccf;
            border-radius:10px;
            color:#986655;
            outline:0;
            text-align:center;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div style="margin-bottom: 20px;margin-left: 50px;">
        <form method="POST" action="{{ route('question.index') }}" id="form_type">
            {{ csrf_field() }}
            <input type="hidden" name="knowledge" id="knowledge" value="{{ $knowledge ?? '' }}"/>
            @foreach($questionType as $v)
                <input type="button" value="{{ $v }}" class="btn btnType" onclick="btnSearchClick(this,'{{ $v }}')">
            @endforeach
            <input type="button" value="保存" onclick="btnSubmit(this)" style="font-size:1em;height:1.7em;text-align:center;border:1px solid #c8cccf;background-color: #da4f49"/>
            <span style="margin-left: 30px;">
                剩余：<span style="color: red">{{ $residueNum }}</span> 次
            </span>
        </form>
    </div>
    {{-- HT --}}
    <div  class="div-HT">
        <table border="1px solid #111" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <td colspan="3">
                        <span style="text-align: center;display:block;font-size: 20px;">
                            华图题库
                        </span>
                        <input type="button" value="换一批" onclick="renovateQuestion(this,0)" style="height:1.7em;font-size:1em;background-color:#5bb75b;float: right">
                    </td>
                </tr>
            </thead>
            <tbody id="questionHT_tbody">
                @foreach($questionHT as $HT)
                    <tr>
                        <td>
                            <input type="checkbox" id="ht_check{{ $HT['id'] }}" onclick="htCheckBoxClick(this,'{{ $HT['id'] }}')"/>{{ $HT['id'] }}
                        </td>
                        <td>
                            {{ $HT['qTitle']['title'] }}

                            @foreach($HT['qTitle']['src'] as $src)
                                <img src='{{ $src }}'/>
                            @endforeach

                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{-- GK --}}
    <div class="div-GK">
        <table border="1px solid #111" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <td colspan="3">
                        <span style="text-align: center;display:block;font-size: 20px;">
                            公考题库
                        </span>
                        <input type="button" value="换一批" onclick="renovateQuestion(this,1)" style="height:1.7em;font-size:1em;background-color:#5bb75b;">
                    </td>
                </tr>
            </thead>
            <tbody id="questionGK_tbody">
                @foreach($questionGK as $GK)
                    <tr>
                        <td>
                            <input type="checkbox" id="gk_check_{{ $GK['id'] }}" onclick="gkCheckBoxClick(this,'{{ $GK['id'] }}')" value="{{ $GK['id'] }}"/>
                            <input type="text" style="width: 50px;" disabled id="gk_{{ $GK['id'] }}">
                        </td>
                        <td>
                            {{ $GK['question'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
<script>
    $(function () {
        showQuestionImages();
        selectedQuestionType();
    })
    /**
     *  加载 公考 题目图片
     */
    function showQuestionImages(){
        var url = "http://tsingzone-gk.oss-cn-beijing.aliyuncs.com/questions/";
        $('#questionGK_tbody').find('tr').each(function () {
            question = $(this).find('td').eq(1);
            question_name = question.text();

            if (question_name.indexOf('<!--[img]') != -1) {
                question.html(getImg_8(question_name, url));
            }
        })
    }
    /**
     *  题目类型选中
     */
    function selectedQuestionType(){
        $('.btnType').each(function (n,v) {
            if("{{ $knowledge }}" == $(v).val()){
                $(v).css('background-color','#bfbfbf');
            }
        })
    }

    var questionIds = [];
    var checkqid = '';
    var gkAllQids = {{ $gkAll }}
    var htAllQids = {{ $htAll }}

    function htCheckBoxClick(self,ht_qid) {
        if($(self).prop('checked') && checkqid.length > 0){
            $(self).prop('checked',false);
            alert('请先对已选中的华图题目与公考题目进行匹配！');
            return;
        }
        var len = questionIds.length;
        if($(self).prop('checked')){
            checkqid = ht_qid;
        }else {
            //清除已选的数据
            for (var i = 0;i < len; i++){
                if(questionIds[i].ht_qid == ht_qid){
                    $('#gk_check'+questionIds[i].gk_qid).prop('checked',false)
                    $('#gk_'+questionIds[i].gk_qid).val('');
                    questionIds.splice(i,1)
                }
            }
            checkqid = '';
        }
    }

    function gkCheckBoxClick(self,gk_qid) {
        if(checkqid.length <= 0 && $(self).prop('checked')){
            $(self).prop('checked',false)
            alert('请先选择华图题目!');
            return;
        }
        var len = questionIds.length;
        if($(self).prop('checked')){
            //验证是否已存在
            if(len > 0){
                var ck = true;
                for (var i = 0;i < len; i++){
                    if(questionIds[i].ht_qid == checkqid){
                        ck = false;
                        $('#gk_'+questionIds[i].gk_qid).val('');
                        $('#gk_check_'+questionIds[i].gk_qid).prop('checked',false);
                        questionIds[i].gk_qid = gk_qid;
                    }
                }
                if(ck){
                    questionIds.push({'ht_qid':checkqid,'gk_qid':gk_qid});
                }
            }else {
                questionIds.push({'ht_qid':checkqid,'gk_qid':gk_qid});
            }
            $('#gk_'+gk_qid).val(checkqid);
        }else {
            //清除已选的数据
            for (var i = 0;i < len; i++){
                if(questionIds[i].gk_qid == gk_qid){
                    $('#ht_check'+questionIds[i].ht_qid).prop('checked',false)
                    $('#gk_'+gk_qid).val('');
                    questionIds.splice(i,1)
                }
            }
        }
        checkqid = '';
    }

    function btnSubmit(self) {
        if(confirm('确定要保存该组题目吗？')){
            $(self).attr('disabled','disabled');

            $.ajax({
                url: "{{route('question.save_question')}}",
                type: "POST",
                data: {
                    _token: "{{csrf_token()}}",
                    questionIds: questionIds,
                    gkAllQid:gkAllQids,
                    htAllQid:htAllQids
                },
                success: function (result) {
                    $(self).removeAttr('disabled')
                    alert(result.message)
                    if(result.result){
                        window.location.reload();
                    }
                }
            })
        }
    }

    function btnSearchClick(self,knowledge) {
        $('.btn').each(function (n,v) {
            $(v).css('background-color','');
        });
        $(self).css('background-color','#bfbfbf');

        $('#knowledge').val(knowledge);
        $('#form_type').submit();

    }
    var isRenovate = true;
    //换一批 区分 0华图 和 1公考
    function renovateQuestion(self,type) {
        if(!isRenovate){
            alert('正在请求刷新，请耐心等待！');
            return;
        }

        var magess = questionIds.length > 0 ? '您有匹配的数据未提交，刷新将会清空已选中的数据，确定刷新吗？' : '确定刷新吗？';
        if(confirm(magess)){
            //禁用按钮
            $(self).attr('disabled','disabled');
            isRenovate = false

            $.ajax({
                url:"{{ route('question.question_query') }}",
                type:"POST",
                data:{
                    question_type:type,
                    knowledge:$('#knowledge').val(),
                    _token: "{{csrf_token()}}"
                },
                success:function(result){
                    $(self).removeAttr('disabled');
                    isRenovate = true

                    var question = result.question
                    if(type == 1){

                        $('#questionGK_tbody').html(questionGK_tr(question))

                        gkAllQids = JSON.parse(result.qids)

                        $('#questionHT_tbody input:checked').each(function (n,v) {
                            $(v).prop('checked',false);
                        })
                    }else {

                        $('#questionHT_tbody').html(questionHT_tr(question))
                        htAllQids = JSON.parse(result.qids)

                        $('#questionGK_tbody input:checked').each(function (n,v) {
                            $(v).prop('checked',false);
                            var obj = $('#gk_'+$(v).val());
                            obj.val('');
                        })
                    }

                    checkqid = ''
                    questionIds.splice(0,questionIds.length)
                }
            })
        }

    }

    function questionGK_tr(question) {
        var len = question.length
        var url = "http://tsingzone-gk.oss-cn-beijing.aliyuncs.com/questions/";
        var htm = ''
        for (var i = 0; i < len; i++){
            var que = getImg_8(question[i].question, url);
            htm += '<tr>' +
                        '<td>' +
                            '<input type="checkbox" id="gk_check_'+ question[i].id +'" onclick="gkCheckBoxClick(this,'+ question[i].id +')" value='+ question[i].id +'/>' +
                            '<input type="text" style="width: 50px;" disabled id="gk_'+ question[i].id +'">'+
                        '</td>'+
                        '<td>'+ que +'</td>'+
                    '</tr>';
        }
        return htm;
    }

    function questionHT_tr(question) {
        var len = question.length
        var htm = ''
        for(var j = 0;j < len; j++){
            htm += '<tr>' +
                    '<td>' +
                    '<input type="checkbox" id="ht_check'+ question[j].id +'" onclick="htCheckBoxClick(this,'+ question[j].id +')"/>'+ question[j].id +
                    '</td>'+
                    '<td>'+ question[j].qTitle.title ;
            var src = question[j].qTitle.src
            var srcLen = src.length
            for (var k = 0; k < srcLen; k++){
                htm += '<img src='+ src[k] +'>';
            }
            htm += '</td></tr>';
        }
        return htm;
    }

    //对8取余作为上级目录名
    function getImg_8(str, url) {

        while (str.indexOf('<!--[img]') != -1) {
            var hash = str.substr(str.indexOf('<!--[img]') + 9);
            var first = getAscii(hash[0]);
            var second = getAscii(hash[1]);
            var directory = ((first + second) % 8) + '/';
            str = str.replace('<!--[img]', '<img src="' + url
                + directory).replace('[/img]-->', '"></img>').replace('[img]-->', '"></img>');
        }

        return str.replace("\n\n", "<br /><br />", "g").replace("\n", "<br />", "g");
    }

    //获取字符串的Ascii码值
    function getAscii(str) {
        if (isNaN(parseInt(str))) {
            var ascii = str.charCodeAt();
        } else {
            var ascii = parseInt(str);

        }
        return ascii;
    }
</script>