<?php
/**
 * Created by PhpStorm.
 * User: xieyan
 * Date: 2019/2/26
 * Time: 下午4:14
 */

$mysqlServerName='127.0.0.1';//'123.56.79.121';
$mysqlUsername='root';//'questionbank';
$mysqlPassword='123456';//'some_pass';
$mysqlDatabase='test';//'question-bank-gk';

$link=mysqli_connect($mysqlServerName,$mysqlUsername,$mysqlPassword,$mysqlDatabase) or die("error connecting") ;
mysqli_query($link,"set names 'utf8'");

if (!$link) {
    printf("Can't connect to MySQL Server. Errorcode: %s ", mysqli_connect_error());
    exit;
}

$i = 0;
$count = 1000;

while(true){

    $startRow = $i*$count;
    $selectSql = "select q.id,eq.qid,eq.eid,e.`name` from question as q
              left join exam_questions as eq on q.id = eq.qid
              left join exams as e on eq.eid = e.id WHERE e.is_real = 1 limit $startRow,$count";

    $result = mysqli_query($link, $selectSql);
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $selectQuestion = "select id,sources from question where id = {$id}";
        $questionResult = mysqli_query($link, $selectQuestion);
        $exam = trim($row['name']);
        while ($question = mysqli_fetch_assoc($questionResult)) {

            $sourcesArray = [];
            if (isset($question['sources'])) {
                $sourcesArray = json_decode($question['sources'],true);
                if (!in_array($exam, $sourcesArray)) {
                    $sourcesArray[] = $exam;
                }
            } else {
                $sourcesArray[] = $exam;
            }
            if(!empty($sourcesArray)){
                rsort($sourcesArray);
                $sourcesJson = json_encode($sourcesArray, JSON_UNESCAPED_UNICODE);
                $updateQuestion = $link->prepare("update question set sources = ? where id = ?");
                $updateQuestion->bind_param('si', $sourcesJson, $id);
                $updateQuestion->execute();
                $updateQuestion->close();
            }
        }

    }

    var_dump($i*$count.'-----'.(($i+1)*$count) .'执行完成！！');

    $i++;

    if ( $result->num_rows < $count ){
        break;
    }
}

mysqli_close($link);
