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
$dirPath = './question/ht_判断推理_最终版';//ht_资料分析_最终版 ht_数量关系_最终版   ht_常识判断_最终版  ht_判断推理_最终版
$arr_file = [];//['./question/言语-所有篇章阅读.xlsx'];
//getFilePathNameRecursion($arr_file, $dirPath, './question/ht_判断推理_最终版');


//连接数据库
$dsn =  "mysql:host=123.57.209.247;port=3306;dbname=question-ht-gk;charset=utf8";
$user = 'questionbank';
$pass = 'some_pass';
$pdo = new PDO($dsn, $user, $pass);
//// 2>设置字符集
$pdo->exec('SET NAMES UTF8');

$questionHTSql = "SELECT qTitle,source FROM question_12_07 WHERE source LIKE '%2012%吉林公务员%'";
$questionHTRes = $pdo->query($questionHTSql);
$ht_question = $questionHTRes->fetchAll(2);

foreach ($ht_question as $v){
    $title = mb_substr($v['qTitle'],round(0,9),10);
    $gkSql = "select e.id AS eid,e.name,eq.id AS qid,q.question,q.selections from exams e
                join exam_questions eq on eq.eid = e.id
                join question2 q on q.id = eq.qid
                where q.question like '%$title%'";
    $gkRes = $pdo->query($gkSql);
    $gkQue = $gkRes->fetchAll(2);
    if(empty($gkQue)){
        var_dump($v);
    }
}

if(!empty($arr_file)){
    foreach ($arr_file as $files){
        // 7获取第N题 9真题试卷名称 12题目ID
        $question = importExecl($files, 0);
        var_dump($files);
        var_dump(count($question));

        if(!empty($question)){
            $sql = getInsertSql($question,$pdo,'常识判断',$files);
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

function getInsertSql($question,$pdo,$category,$files){
    $examQuestionSql = 'INSERT INTO exam_questions(eid,qid,`index`,score,difficulty,category) VALUE ';
    $num = 0;
    $qids = [];
        foreach ($question as $q_key => $q_val){
            $qid = $q_val[11];//$q_val[12];
            $questionSource = $q_val[7];
            $examsName = $q_val[10];//$q_val[9];
            $area = getAreaaName($examsName);//empty($q_val[10]) ? getAreaaName($examsName) : $q_val[10];
            $year = $q_val[9];//$q_val[11];
//            $pos = strpos($examsName,'2012年1202吉林公务员《行测》试卷（乙卷）');
//            if($pos >= 0 && $pos !== false){
//                $num++;
//                var_dump($files);
//                var_dump($q_val[10]);
//            }

            $course = getCourse($examsName);
            if(!is_numeric($qid)){
                continue;
            }
            //验证真题试卷是否存在
            $examsSql = "SELECT id,count(id) AS num FROM exams where name = '$examsName'";
            $exams = $pdo->query($examsSql);
            $examsArray = $exams->fetch(2);//fetchAll(PDO::FETCH_ASSOC);

            $isQuestion = false;
            $examsId = 0;
            if($examsArray['num'] > 0){
                //真题试卷存在 验证exam_questions 是否已关联  未关联需要添加
                $examsQueSql = "SELECT COUNT(id) AS q_num FROM exam_questions WHERE eid = ".$examsArray['id']." AND qid = $qid";
                $examsQue = $pdo->query($examsQueSql);
                $examsQueArr = $examsQue->fetch(2);
                if($examsQueArr['q_num'] <= 0){
                    $examsId = $examsArray['id'];
                    $isQuestion = true;
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
                    var_dump($examsInsertSql);
                    return;
                }
                $examsId = $pdo->lastInsertId();
                $isQuestion = true;
            }

            if($isQuestion && $examsId > 0 && (!isset($qids[$examsId]) || !in_array($qid,$qids[$examsId]))){
                $qids[$examsId][] = $qid;
                $num++;
                //关联exam_questions
                $sourses = explode('、',$questionSource);
                $pattern = count($sourses) > 1 ?  "/\w*$examsName\w*第(\d+)题/" : "/\w*第(\d+)题/" ;
                preg_match($pattern,$questionSource,$match);
                $index = isset($match[1]) ? $match[1] : 0;

                $examQuestionSql .= "($examsId,$qid,$index,'0.8',0,'$category'),";

            }

        }

    return $num > 0 ? rtrim($examQuestionSql,',') : NULL;
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

