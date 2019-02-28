<?php
/**
 * Created by PhpStorm.
 * User: xieyan
 * Date: 2019/2/14
 * Time: 下午6:31
 */
include_once './PHPExcel/PHPExcel.php';
include_once './PHPExcel/PHPExcel/IOFactory.php';

/** 递归获取所有文件
 * @param $arr_file
 * @param $directory
 * @param string $dir_name
 */
function getFilePathNameRecursion(&$arr_file, $directory, $dir_name='')
{
    $mydir = dir($directory);
    while($file = $mydir->read())
    {

        if((is_dir("$directory/$file")) AND ($file != ".") AND ($file != ".."))
        {
            getFilePathNameRecursion($arr_file, "$directory/$file", "$dir_name/$file");
        }
        else if(($file != ".") AND ($file != ".."))
        {
            $arr_file[] = "$dir_name/$file";
        }
    }
    $mydir->close();
}

//获取所有文件
$dirPath = './question/ht1231';//ht_资料分析_最终版 ht_数量关系_最终版   ht_常识判断_最终版  ht_判断推理_最终版
$arr_file = [];//['./question/言语-所有篇章阅读.xlsx']; 言语理解与表达
getFilePathNameRecursion($arr_file, $dirPath, './question/ht1231');

//连接数据库
$dsn =  "mysql:host=123.57.209.247;port=3306;dbname=question-ht-gk;charset=utf8";
$user = 'questionbank';
$pass = 'some_pass';
$pdo = new PDO($dsn, $user, $pass);
//// 2>设置字符集
$pdo->exec('SET NAMES UTF8');
//$new_htSql = "select * from exams_excel";
//$new_htRes = $pdo->query($new_htSql);
//$arr_file = $new_htRes->fetchAll(2);

if(!empty($arr_file)){
    //Excel 表导入
    foreach ($arr_file as $files){
        // 7获取第N题 9真题试卷名称 12题目ID
        $question = importExecl($files, 0);
        if(!empty($files)){
            $sql = getInsertSql($question,$pdo,'判断推理',$files);

//            $sql = getInsertSql($arr_file,$pdo,'判断推理');
            if(isset($sql)){
                $examRes = $pdo->exec($sql);
                var_dump($files);
                if(!$examRes){//添加失败
                    var_dump($sql);
                    return;
                }
                var_dump($examRes);
            }

        }
    }
}

function getInsertSql($question,$pdo,$category,$files = NULL){
    $examQuestionSql = 'INSERT INTO exam_questions(eid,qid,`index`,score,difficulty,category) VALUE ';
//    $examQuestionSql = 'INSERT INTO ht_questions_excel(qExamineCenter,qTitle,source,ht_qid,qExamTitle,examYear,qid,category) VALUE ';
    $num = 0;
    $qids = [];
    foreach ($question as $q_key => $q_val){
//            if(!empty($q_val[1])){
//                $qid = is_numeric($q_val[11]) ? $q_val[11] : 'NULL';
//                $examQuestionSql .= "('".$q_val[1]."','".$q_val[3]."','".$q_val[7]."',".$q_val[8].",'".$q_val[10]."','".$q_val[9]."',".$qid.",'".$category."'),";
//            }

        $qid = $q_val[11];//$q_val[12];//$q_val[11];
        $questionSource = $q_val[7];
        $examsName = $q_val[10];//$q_val[9];//$q_val[10];
        $area = getAreaaName($examsName);//$q_val[10];//getAreaaName($examsName);
        $year = $q_val[9];//$q_val[11];//$q_val[9];
        $category = getQuestionCategory($q_val[1]);

//            $qid = $q_val['qid'];
//            $questionSource = $q_val['source'];
//            $examsName = $q_val['qExamTitle'];
//            $year = $q_val['examYear'];
//            $area = getAreaaName($examsName);
        $course = getCourse($examsName);
//            除去2018年套卷
        $delExam = [
            '2008年四川省公务员考试《行测》真题',
            '2014年重庆市公务员考试《行测》真题'
        ];
        if(!is_numeric($qid) || $year == '2018' || in_array($examsName,$delExam)){
            continue;
        }
        $examsId = getExamsID($examsName);
        if($examsId <= 0){
            //验证真题试卷是否存在
            $examsSql = "SELECT id,count(id) AS num FROM exams where name = '$examsName'";
            $exams = $pdo->query($examsSql);
            $examsArray = $exams->fetch(2);//fetchAll(PDO::FETCH_ASSOC);

            if($examsArray['num'] > 0){
                //真题试卷存在 验证exam_questions 是否已关联  未关联需要添加
                $examsQueSql = "SELECT COUNT(id) AS q_num FROM exam_questions WHERE eid = ".$examsArray['id']." AND qid = $qid";
                $examsQue = $pdo->query($examsQueSql);
                $examsQueArr = $examsQue->fetch(2);
                if($examsQueArr['q_num'] <= 0){
                    $examsId = $examsArray['id'];
//                        $isQuestion = true;
                }else{
                    continue;
                }
            }else{
                //真题试卷不存在
                //新增exams
                $segmentStr = strpos($examsName,'国家');
                $segment = $segmentStr >= 0 && $segmentStr !== false ? '国考' : '省考';
                $area = empty($area) && $segment == '国考' ? '全国' : $area;

                $examsInsertSql = "INSERT INTO exams(mid,name,area,year,segment,course) VALUE(0,'$examsName','$area','$year','$segment','$course')";
                $examRes = $pdo->exec($examsInsertSql);
                if(!$examRes){//添加失败
                    var_dump($files);
                    var_dump($examsInsertSql);
                    return;
                }
                $examsId = $pdo->lastInsertId();
//                    $isQuestion = true;
            }
        }else{
            $examsQueSql = "SELECT COUNT(id) AS q_num FROM exam_questions WHERE eid = ".$examsId." AND qid = $qid";
            $examsQue = $pdo->query($examsQueSql);
            $examsQueArr = $examsQue->fetch(2);
            if($examsQueArr['q_num'] > 0){
                continue;
            }
        }

        //过滤相同试卷且相同qid
        if($examsId > 0 && (!isset($qids[$examsId]) || !in_array($qid,$qids[$examsId]))){
            $qids[$examsId][] = $qid;
            $num++;
            //关联exam_questions
            $sourses = explode('、',$questionSource);
            $pattern = count($sourses) > 1 ?  "/\W*$examsName\W*第(\d+)题/" : "/\W*第(\d+)题/" ;
            preg_match($pattern,$questionSource,$match);
            $index = isset($match[1]) ? $match[1] : 0;
            if($index <= 0){
                $num++;
//                    $patt = "/\W*$year\W*[$area][\w\W\s\S]*第(\d+)题/";
                $patt = "/\W*$year+\W*$area+[\W|\s]+第(\d+)题/";
                preg_match($patt,$questionSource,$matchs);
                $index = isset($matchs[1]) ? $matchs[1] : 0;
            }

            $examQuestionSql .= "($examsId,$qid,$index,'0.8',0,'$category'),";

        }

    }

//    return  rtrim($examQuestionSql,',');
    return $num > 0 ? rtrim($examQuestionSql,',') : NULL;
}
function getQuestionCategory($know)
{
    $category = array(
        '数量关系' => ['最值问题',
            '几何问题',
            '概率问题',
            '基础计算',
            '费用问题',
            '方程与不等式1',
            '工程问题',
            '时间问题2',
            '行程问题',
            '趣味杂题',
            '容斥原理',
            '排列组合',
            '递推数列',
            '多级数列',
            '幂次数列',
            '非整数数列',
            '溶液问题',
            '数图推理',
            '拆分数列',
            '点阵数数',
            '特殊数列',
            '基本数列',
            '简单计算',
            '增长率计算',
            '其他类计算',
            '增长量计算',
            '综合分析',
            '平均数计算',
            '比重计算',
            '比重大小比较',
            '倍数计算',
            '其他类比较',
            '简单比较',
            '增长量大小比较',
            '间隔增长率计算',
            '平均数大小比较',
            '混合增长率计算',
            '增长量计算',
            '其他增长率比较',
            '平均值增长率计算',
            '增长率大小比较',
            '年均增长率计算',
            '基期量大小比较'],
        '常识判断' => [
            '哲学与马克思主义哲学',
            '唯物论',
            '辩证法',
            '认识论',
            '唯物史观',
            '毛泽东思想与中国特色社会主义理论体系',
            '中共党建',
            '国内时政',
            '国际时政',
            '法理学',
            '宪法',
            '民法',
            '刑法',
            '行政法',
            '诉讼法',
            '经济法',
            '马克思主义政治经济学',
            '社会主义市场经济学',
            '宏微观经济学',
            '中国古代史',
            '中国近现代史',
            '世界史',
            '文学常识',
            '艺术常识',
            '科技成就',
            '科学常识',
            '地理常识',
            '公共管理常识',
            '公文处理常识',
        ],
        '判断推理' => [
            '数量类',
            '样式类',
            '位置类',
            '重构类',
            '创新类',
            '多定义',
            '单定义',
            '语义类',
            '映射类',
            '集合类',
            '语法类',
            '论证',
            '翻译推理',
            '平行结构',
            '原因解释',
            '归纳推理',
            '分析推理',
            '事件排序',
            '真假推理',
            '其他类比',
            '实体信息',
            '其他逻辑',
            '集合推理',
            '科学推理',
        ],
        '言语理解与表达' => [
            '综合考察',
            '成语辨析',
            '实词辨析',
            '主旨概括',
            '意图判断',
            '细节理解',
            '标题选择',
            '语句填空',
            '语句排序',
            '下文推断',
            '词语理解',
            '态度理解',
            '细节理解',
            '综合考察',
            '虚词辨析',
            '病句辨析',
            '歧义句辨析',
            '意图判断（篇章）',
            '下文推断（篇章）',
            '上文推断',
            '细节理解（篇章）',
            '逻辑填空（篇章）',
            '主旨概括（篇章）',
            '代词指代',
            '语句填空（篇章）',
            '态度观点（篇章）',
            '标题选择（篇章）',
            '细节理解（篇章）',
            '语句排序（篇章）',
            '综合考察',
            '基础知识',
            '逻辑填空（篇章）',
            '词语理解（篇章）',
            '逻辑填空（篇章）',
            '基础知识（篇章）',
            '代词指代（篇章）',
            '综合考察（篇章）',
            '病句辨析（篇章）',
            '文段衔接（篇章）',
        ],
        '资料分析' => [
            '简单计算',
            '增长率计算',
            '其他类计算',
            '增长量计算',
            '综合分析',
            '平均数计算',
            '比重计算',
            '比重大小比较',
            '倍数计算',
            '其他类比较',
            '简单比较',
            '增长量大小比较',
            '间隔增长率计算',
            '平均数大小比较',
            '混合增长率计算',
            '增长量计算',
            '其他增长率比较',
            '平均值增长率计算',
            '增长率大小比较',
            '年均增长率计算',
            '基期量大小比较',
        ],
    );
    $cate = '';
    foreach ($category as $k=>$v){
        if(in_array($know,$v)){
            $cate = $k;
            break;
        }
    }
    return $cate;
}

function getExamsID($examName){
    $exams = [
        '1172' => ['2011年广州市公务员考试《行测》真题','2011年0528广州公务员《行测》试卷'],
        '1197' => ['2012年广州市公务员考试《行测》真题','2012年0602广州公务员《行测》试卷'],
        '1134' => ['2013年0511广州公务员《行测》试卷','2013年广州市公务员考试《行测》真题'],
        '1047' => ['2014年0427广州公务员《行测》试卷','2014年广州市公务员考试《行测》真题'],
        '902' => ['2015年广州市公务员考试《行测》真题','2015年0510广州公务员《行测》试卷'],
        '1071' => ['2016年0320广州公务员《行测》试卷','2016年广州市公务员考试《行测》真题'],
        '878' => ['2013年北京公务员《行测》真题','2013年北京市公务员考试《行测》真题'],
        '905' => ['2015年吉林省公务员考试《行测》真题（下半年乙级）','2015年0926吉林公务员《行测》试卷（乙级）'],
        '899' => ['2015年0425吉林公务员《行测》试卷（乙级）','2015年0425吉林公务员《行测》试卷（乙）'],
        '1003' => ['2015年吉林省公务员考试《行测》真题（上半年甲级）','2015年0425吉林公务员《行测》试卷（甲级）'],
        '1096' => ['2016年1022吉林公务员《行测》试卷（甲级）','2016年1022吉林公务员《行测》试卷（甲）'],
        '910' => ['2016年1022吉林公务员《行测》试卷（乙）','2016年1022吉林公务员《行测》试卷（乙级）'],
        '1100' => ['2017年0422吉林公务员《行测》试卷（甲）','2017年0422吉林公务员《行测》试卷（甲级）'],
        '1136' => ['2017年0422吉林公务员《行测》试卷（乙级）','2017年0422吉林公务员《行测》试卷（乙）'],
        '1191' => ['2008年山东公务员《行测》真题','2008年0323山东公务员《行测》试卷'],
        '1183' => ['2009年0315山东公务员《行测》试卷','2009年山东公务员《行测》真题'],
        '1219' => ['2012年0324山东公务员《行测》试卷','2012年山东公务员《行测》真题'],
        '1217' => ['2014年山东公务员《行测》真题','2014年0622山东公务员《行测》试卷'],
        '1222' => ['2008年0330广东公务员《行测》试卷','2008年广东省公务员考试《行测》真题'],
        '1227' => ['2009年0426广东公务员《行测》试卷','2009年广东省公务员考试《行测》真题'],
        '1199' => ['2010年0321广东公务员《行测》试卷（精选）','2010年广东省公务员考试《行测》真题（精选）'],
        '1224' => ['2011年广东省公务员考试《行测》真题（精选）','2011年0327广东公务员《行测》试卷'],
        '1244' => ['2012年0527广东公务员《行测》试卷','2012年广东省公务员考试《行测》真题'],
        '1230' => ['2013年广东省公务员考试《行测》真题（务工）','2013年0414广东公务员《行测》试卷（务工）'],
        '1213' => ['2013年广东省公务员考试《行测》真题','2013年0414广东公务员《行测》试卷'],
        '883' => ['2014年0316广东公务员《行测》试卷（县级）','2014年广东省公务员考试《行测》真题'],
        '1225' => ['2014年广东省公务员考试《行测》真题（乡镇）','2014年0316广东公务员《行测》试卷（乡镇）'],
        '1135' => ['2015年0322广东公务员《行测》（县级试卷）','2015年0322广东省公务员考试《行测二》试卷（县级）'],
        '898' => ['2015年0322广东公务员《行测》（乡镇试卷）','2015年0322广东省公务员考试《行测一》试卷（乡镇）'],
        '1215' => ['2016年0423广东公务员《行测》试卷（县级以上）','2016年广东省公务员考试《行测》真题（县级以上）'],
        '1242' => ['2016年广东省公务员考试《行测》真题（乡镇）','2016年0423广东公务员《行测》试卷（乡镇）'],
        '1223' => ['2017年0408广东省公务员考试《行测》试卷','2017年0408广东公务员《行测》试卷'],
        '808' => ['2008年0329广西公务员《行测》试卷','2008年广西壮族自治区公务员考试《行测》真题'],
        '1231' => ['2009年0328广西公务员《行测》试卷','2009年广西公务员考试《行测》真题'],
        '1196' => ['2017年0422江西公务员《行测》试卷','2017年江西省公务员考试《行测》真题'],
        '1246' => ['2008年河南公务员《行测》真题','2008年0706河南公务员《行测》试卷'],
        '1190' => ['2009年河南公务员《行测》真题','2009年1031河南公务员《行测》试卷'],
        '1214' => ['2013年河南公务员《行测》真题','2013年0921河南公务员《行测》试卷'],
        '1218' => ['2014年河南公务员《行测》真题','2014年0927河南公务员《行测》试卷'],
        '1241' => ['2009年湖南省公务员考试《行测》真题','2009年0426湖南公务员《行测》试卷'],
        '1238' => ['2016年0423甘肃公务员《行测》试卷','2016年甘肃公务员行测真题'],
        '1239' => ['2009年福建省公务员考试《行测》真题（春季）','2009年福建公务员《行测》试卷（春季）'],
        '1203' => ['2009年0913福建公务员《行测》试卷','2009年0913福建公务员考试《行测》试卷'],
        '1207' => ['2013年0413福建公务员《行测》试卷（大题库）','2013年0413福建公务员《行测》试卷'],
        '731' => ['2008年贵州省公务员考试《行测》真题','2008年0712贵州公务员《行测》试卷'],
        '1234' => ['2009年贵州省公务员考试《行测》真题','2009年0712贵州公务员《行测》试卷'],
        '1205' => ['2010年0613贵州公务员《行测》试卷','2010年贵州省公务员考试《行测》真题'],
        '1193' => ['2017年黑龙江省公务员考试《行测》真题','2017年0422黑龙江公务员《行测》试卷'],
        '972' => ['2008年1123深圳公务员《行测》试卷（下半年）','2008年深圳市公务员考试《行测》真题（下半年）'],
        '739' => ['2009年0322内蒙古公务员《行测》试卷','2009年内蒙古公务员《行测》试卷（上半年）'],
        '729' => ['2008年0525吉林公务员《行测》试卷（乙卷）','2008年吉林省公务员考试《行测》真题（乙卷）'],
        '1245' => ['2008年吉林省公务员考试《行测》真题（甲卷）','2008年0525吉林公务员《行测》试卷（甲卷）'],
        '741' => ['2009年吉林省公务员考试《行测》真题（甲卷）','2009年0530吉林公务员《行测》试卷（甲卷）'],
        '744' => ['2009年0530吉林公务员《行测》试卷（乙卷）','2009年吉林省公务员考试《行测》真题（乙卷）'],
        '752' => ['2010年吉林省公务员考试《行测》真题（甲卷）','2010年0327吉林公务员《行测》试卷（甲卷）'],
        '751' => ['2010年0327吉林公务员《行测》试卷（乙卷）','2010年吉林省公务员考试《行测》真题（乙卷）'],
        '1240' => ['2011年吉林省公务员考试《行测》真题（乙卷）','2011年0515吉林公务员《行测》试卷（乙卷）'],
        '1235' => ['2011年吉林省公务员考试《行测》真题（甲卷）','2011年0515吉林公务员《行测》试卷（甲卷）'],
        '769' => ['2012年吉林公务员《行测》真题（甲卷）（精选）','2012年1202吉林公务员《行测》试卷（甲卷）（精选）'],
        '768' => ['2012年吉林公务员《行测》真题（乙卷）（精选）','2012年1202吉林公务员《行测》试卷（乙卷）'],
        '771' => ['2013年吉林省公务员考试《行测》真题（乙卷）','2013年0915吉林公务员《行测》试卷（乙卷）'],
        '772' => ['2013年0915吉林公务员《行测》试卷（甲卷）','2013年吉林省公务员考试《行测》真题（甲卷）'],
        '780' => ['2014年0412吉林公务员《行测》试卷（甲）','2014年0412吉林公务员《行测》试卷（甲级）'],
        '785' => ['2014年0412吉林公务员《行测》试卷（乙级）','2014年0412吉林公务员《行测》试卷（乙）'],
        '984' => ['2012年1104四川公务员《行测》试卷','2012年1105四川公务员《行测》试卷'],
        '1233' => ['2017年0422四川公务员《行测》试卷（定向乡镇）','2017年0422四川公务员《行测》试卷（乡镇）'],
        '1202' => ['2017年0422河南（选调生）公务员《行测》试卷','2017年0422河南选调生《行测》试卷'],
        '809' => ['2008年0330重庆公务员《行测》试卷','2008年重庆公务员《行测》真题（上半年）'],
        '1109' => ['2017年0923重庆公务员《行测》试卷','2017年0923重庆市公务员《行测》试卷（下半年）'],
        '1252' => ['2011年1203深圳公务员《行测》试卷','2011年深圳市公务员考试《行测》真题'],
        '875' => ['2013年深圳市公务员考试《行测》真题（上半年）','2013年0414深圳公务员《行测》试卷'],
        '1212' => ['2016年0116深圳公务员《行测》试卷','2016年深圳市公务员考试《行测》真题'],
        '1243' => ['2017年深圳市公务员考试《行测》真题','2017年0326深圳市公务员《行测》真题'],
        '730' => ['2008年0830云南公务员《行测》试卷','2008年云南省公务员考试《行测》真题'],
        '743' => ['2009年0822云南公务员《行测》试卷','2009年云南省公务员考试《行测》真题'],
        '913' => ['2016年国家公务员考试《行测》真题（省部级）','2016年国家公务员考试《行测》真题（地市级）'],
        '924' => ['2017年国家公务员考试《行测》真题（省部级）','2017年国家公务员考试《行测》真题（地市级）'],
    ];
    $examsId = 0;
    foreach ($exams as $k => $v){
        if(in_array($examName,$v)){
            $examsId = $k;
            break;
        }
    }
    return $examsId;
}

//获取目标科目
function getCourse($examsName){
    $pos = strpos($examsName,'行测');
    $course = $pos >= 0 && $pos !== false ? '行测' : 'NULL';
    return $course;
}

//获取地址名
function getAreaaName($examsName){

    $options = ProOptions();
    $area = '';
    foreach ($options as $v){
        $pos = strpos($examsName,$v);
        if($pos >= 0 && $pos !== false){
            $area = $v;
            break;
        }
    }
    return $area;
}

function ProOptions(){
    return  [
        "全国" => '全国',
        "北京" => '北京',
        "上海" => '上海',
        "天津" => '天津',
        "重庆" => '重庆',
        "广州" => '广州',
        "深圳" => '深圳',
        "河北" => '河北',
        "河南" => '河南',
        "吉林" => '吉林',
        "黑龙江" => '黑龙江',
        "辽宁" => '辽宁',
        "山东" => '山东',
        "山西" => '山西',
        "陕西" => '陕西',
        "安徽" => '安徽',
        "江苏" => '江苏',
        "浙江" => '浙江',
        "江西" => '江西',
        "宁夏" => '宁夏',
        "内蒙古" => '内蒙古',
        "甘肃" => '甘肃',
        "广东" => '广东',
        "广西" => '广西',
        "贵州" => '贵州',
        "青海" => '青海',
        "湖南" => '湖南',
        "湖北" => '湖北',
        "福建" => '福建',
        "四川" => '四川',
        "云南" => '云南',
        "海南" => '海南',
        "新疆" => '新疆',
        "西藏" => '西藏',
        "香港" => '香港',
        "澳门" => '澳门',
        "台湾" => '台湾'
    ];
}

//读取excel数据
function importExecl($file='', $sheet=0){
//    $file = iconv("UTF-8", "GB2312", $file);   //转码ou
    if(empty($file) OR !file_exists($file)) {
        var_dump('file not exists!');
        return;
    }
    $PHPReader = new PHPExcel_Reader_Excel2007();   //建立reader对象

    if(!$PHPReader->canRead($file)){
        $PHPReader = new PHPExcel_Reader_Excel5();
        if(!$PHPReader->canRead($file)){
            var_dump('No Excel!');
            return;
        }
    }
    //处理excel文件
    $phpExcel = $PHPReader->load($file);
    $sheet = $phpExcel->getSheet($sheet);
    //逐行读取excel数据
    $data = [];
    foreach ($sheet->getRowIterator() as $key => $row) {
        $row_data = [];
        if ($row->getRowIndex() < 2) {
            continue;
        }
        foreach ($row->getCellIterator() as $c_key => $cell) {
            #获取cell中数据
            $row_data[$c_key] = $cell->getValue();
        }
        $data[] = $row_data;
    }
    return $data;
}

