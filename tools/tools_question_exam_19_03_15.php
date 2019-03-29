<?php
/**
 * Created by PhpStorm.
 * User: shaoshuai
 * Date: 2018/3/13
 * Time: 下午2:47
 */

//连接数据库
$dsn = "mysql:host=47.99.45.103;port=3306;dbname=QuestionHTGK;charset=utf8";
$user = 'root';
$pass = 'root@123456';
$pdo = new PDO($dsn, $user, $pass);
//// 2>设置字符集
$pdo->exec('SET NAMES UTF8');
//// 4>执行SQL语句

$questionSql = "select examArea,examYear,qExamTitle from question_19_03_15";
$questionRes = $pdo->query($questionSql);
$questionArr = $questionRes->fetchAll(2);



foreach ($questionArr as $val){

    $examsSql = "SELECT count(id) AS num FROM exams where name = '".$val['qExamTitle']."'";
    $exams = $pdo->query($examsSql);
    $examsArray = $exams->fetch(2);

    if($examsArray['num'] <= 0){
        $examsName = $val['qExamTitle'];
        $area = $val['examArea'];
        $year = $val['examYear'];

        $course = getCourse($examsName);
        $segmentStr = strpos($examsName,'国家');
        $segment = $segmentStr >= 0 && $segmentStr !== false ? '国考' : '省考';

        $insertSql = "INSERT INTO exams(mid,name,area,year,segment,course) VALUE(0,'$examsName','$area','$year','$segment','$course')";
        $examRes = $pdo->exec($insertSql);
        if(!$examRes){//添加失败
            var_dump($insertSql);
            return;
        }
    }

}

//获取目标科目
function getCourse($examsName){
    $pos = strpos($examsName,'行测');
    $course = $pos >= 0 && $pos !== false ? '行测' : 'NULL';
    return $course;
}
