<?php
/**
 * Created by PhpStorm.
 * User: xieyan
 * Date: 2019/1/21
 * Time: 下午2:33
 */
namespace App\Http\Controllers;
use App\Repositories\QuestionRepository;
use ClassesWithParents\D;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    protected $question;
    static $questionType = [
        '数量类',
        '创新类',
        '重构类',
        '位置类',
        '样式类'
    ];

    function __construct(QuestionRepository $question)
    {
        $this->question = $question;
    }

    public function index(Request $request){
        $knowledge = $request->input('knowledge','数量类');
        $questionType = self::$questionType;
        $data = $this->assemblyQuestion($knowledge);
        $questionGK = $data['questionGK'];
        $questionHT = $data['questionHT'];
        $htAll = $data['htAll'];
        $gkAll = $data['gkAll'];
        $residueNum = $this->question->getResidueNum($knowledge);
        return view('question',compact('questionGK','questionHT','questionType','htAll','gkAll','knowledge','residueNum'));
    }

    /** 获取 华图 题目中存在的图片连接
     * @param $str
     * @return array
     */
    function getImgsHT(&$str) {
//        $reg = '/(<\!--\[img\])+(\w+\.)+(jpg|gif|png)+\[\/img\]-->/i';
        $reg = '/((http|https):\/\/)+(\w+\.)+(\w+)[\w\/\.\-]*(jpg|gif|png)/';
        $matches = array();
        preg_match_all($reg, $str, $matches);
        $data = [
            'src' => [],
            'title' => '',
        ];
//        $reg2 = '/(\?imageView2\/)+\w+[\w\/\.\-]*(jpg|gif|png)/i';
//        $str = preg_replace($reg2,'',$str);
        foreach ($matches[0] as $value) {
            $str = str_replace('<br>','',$str);
            $str = str_replace($value,'',$str);
            $data['title'] = $str;
            $data['src'][] = $value;
        }
        return $data;
    }

    /** 换一批
     * @param Request $request
     */
    public function questionQuery(Request $request)
    {
        $questionType = $request->input('question_type');
        $knowledge = $request->input('knowledge');
        $data = $this->assemblyQuestion($knowledge,false,$questionType);
        $question = $questionType == 1 ? $data['questionGK'] : $data['questionHT'];
        $qids = $questionType == 1 ? $data['gkAll'] : $data['htAll'];
        return [
            'question' => $question,
            'qids' => $qids
        ];
    }

    /** 获取列表数据
     * @param $knowledge
     * @return array
     */
    private function assemblyQuestion($knowledge,$isAll = true,$questionType = 0)
    {
        $qAnswer = $this->question->getQusertionAnswerByKnowledge($knowledge);
        $qAnswer = isset($qAnswer[0]['qAnswer']) ? $qAnswer[0]['qAnswer'] : 'A';
        $questionGK = [];
        $questionHT = [];

        if($isAll){
            $questionGK = $this->question->getQuestionGk($knowledge,$qAnswer);
            $questionHT = $this->question->getQuestionHT($knowledge,$qAnswer);
        }else{
            switch ($questionType){
                case 0:
                    $questionHT = $this->question->getQuestionHT($knowledge,$qAnswer);
                    break;
                case 1:
                    $questionGK = $this->question->getQuestionGk($knowledge,$qAnswer);
                    break;
            }
        }

        $htAll = [];
        if(!empty($questionHT)){
            foreach ($questionHT as &$HT){
                $htAll[] = $HT['id'];
                $HT['qTitle'] = $this->getImgsHT($HT['qTitle']);
            }
        }
        $htAll = json_encode($htAll);
        $gkAll = json_encode(array_column($questionGK,'id'));

        return [
            'questionGK' => $questionGK,
            'questionHT' => $questionHT,
            'htAll' => $htAll,
            'gkAll' => $gkAll
        ];
    }

    /** 提交保存
     * @param Request $request
     */
    public function saveQuestion(Request $request)
    {
        $questionIds = $request->input('questionIds');
        $gkAllQid = $request->input('gkAllQid');
        $htAllQid = $request->input('htAllQid');

        if(empty($gkAllQid) || empty($htAllQid)){
            return false;
        }

        if(!empty($questionIds)){
            //有重题
            foreach ($questionIds as $val){
                $this->question->repeatQuestionHTGK($val['ht_qid'],$val['gk_qid']);
                $key = array_search($val['ht_qid'],$htAllQid);
                unset($htAllQid[$key]);
            }
        }

        $res = $this->question->updateQuestionStatusBy($htAllQid,$gkAllQid);

        return [
            'result' => $res,
            'message' => $res ? '保存成功' : '保存失败！请重试'
        ];
    }
    /**
     * select *,count(q.id) num from question_ht_gk q where q.status = 2 group by  q.ht_qid
     * HAVING num = (select count(qs.id) from question_ht_gk qs where qs.ht_qid = q.ht_qid)
     */
    public function selfRepeatQuestion(Request $request)
    {
        $type = $request->input('type');
        $qid = $request->input('self_qid');
        $repeatQids = $request->input('repeat_qid');
        $knowledge = $request->input('knowledge');
        if(empty($qid) || empty($repeatQids) || !is_array($repeatQids)){
            return [
                'success' => false,
                'message' => '请求参数错误！请重试'
            ];
        }
        if($type == 'ht'){
            $isSuccess = $this->question->selfQuestionRepeatHT($qid,$repeatQids);
        }else{
            $isSuccess = $this->question->selfQuestionRepeatGK($qid,$repeatQids);
        }
        return [
            'success' => $isSuccess,
            'message' => $isSuccess ? '保存成功！' : '保存失败！请重试',
            'residueNum' => $this->question->getResidueNum($knowledge)
        ];
    }

    //导数据代码
    public function questionsHTQuery(){
        $dsn =  "mysql:host=123.57.209.247;port=3306;dbname=question-ht-gk;charset=utf8";
        $user = 'questionbank';
        $pass = 'some_pass';
        $pdo = new \PDO($dsn, $user, $pass);
        //// 2>设置字符集
        $pdo->exec('SET NAMES UTF8');
//        $new_htSql = "select ht.*,count(q.id) num from question_ht_gk2 q JOIN question_12_11 ht ON ht.id = q.ht_qid
//                      where q.status = 2 and q.repeat_qid is null group by q.ht_qid HAVING num = (select count(qs.id) from question_ht_gk2 qs where qs.ht_qid = q.ht_qid)";
        $new_htSql = "select ht.*,q.gk_qid from question_ht_gk2 q join question_12_11 ht on ht.id = q.ht_qid where q.status = 1 group by q.gk_qid";
//        $new_htSql = "select * from exams_excel";
        $new_htRes = $pdo->query($new_htSql);
        $new_htArr = $new_htRes->fetchAll(2);
        var_dump(count($new_htArr));
        return;
        $knowledge = [
            '数量类' => 61,
            '位置类' => 75,
            '样式类' => 74,
            '重构类' => 76,
            '创新类' => 62,
        ];
        $storage = app('filesystem')->disk('local');
//        var_dump(count($new_htArr));//2338
//        return;
        foreach ($new_htArr as $k => $value){

//            $question = $this->replaceStr($value['qTitle'],$storage);//上传图片
//            $selections = $this->replaceStr($value['qSelections'],$storage);//上传图片
//            $parse = $this->replaceStr($value['qAnalysis'],$storage);//上传图片
//            $answer = json_encode(['answer' => $value['qAnswer'] ,'parse' => $parse],JSON_UNESCAPED_UNICODE);
//            $knowledge_id = isset($knowledge[$value['qExamineCenter']]) ? $knowledge[$value['qExamineCenter']] : NULL;
//            if(is_null($knowledge_id)){
//                var_dump($value);
//                return;
//            }
//            $gkInsertSql = "INSERT INTO question2(question,selections,type,answer,knowledge_id,sources,score,`index`,has_img) VALUE ";
//            $gkInsertSql .= "('$question','$selections','".$value['qType']."','$answer',$knowledge_id,'".$value['source']."','0.8',".$value['indexSource'].",1)";
//
//            if(!$pdo->exec($gkInsertSql)){
//                echo 'gkInsertSql ==' .$gkInsertSql;
//                return;
//            }
//            $qid = $value['gk_qid'];

            //添加exam_excel
//            $examExcelSql = "INSERT INTO exams_excel(indexSource,source,qExamTitle,examArea,examYear,qid,ht_qid) VALUE ";
//            $examArea = $value['examArea'] == '国家' ? '全国' : $value['examArea'];
//            $examExcelSql .= "('".$value['indexSource']."','".$value['source']."','".$value['qExamTitle']."','".$examArea."','".$value['examYear']."',$qid,".$value['id'].")";
//            if(!$pdo->exec($examExcelSql)){
//                echo 'examExcel === '.$examExcelSql;
//                return;
//            }
        }

    }

    public function replaceStr($str,$storage,$reg = '/http:\/\/tiku\.+\w+\.+\w+[\w\/\.\-]*(jpg|gif|png|PNG|jpeg)/',$path = 'ht_image')
    {
        //http://tsingzone-gk-test.oss-cn-beijing.aliyuncs.com/questions/4/31feed7a89a325aff521e824816649df.png

        while (preg_match($reg, $str,$res)){
//            echo $str.PHP_EOL;
            if (count($res) == 2){
                $extension = $res[1];
                $imageName = promotionCode(8,2);
                $imageUrl = $path.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.$imageName.'.'.$extension;
                $fileName = md5($imageName);
                $fileName = $fileName.'.'.$extension;
                $subFolder = (ord(substr($fileName, 0, 1)) + ord(substr($fileName, 1, 1))) % 8;
                $subFolder .= '/';
//                try{
                $storage->put($imageUrl,file_get_contents($res[0]));
//                }catch (\Exception $exception){
//                    break;
//                }
                $localImageUrl = storage_path().'/app/ht_image/images/'.$imageName.'.'.$extension;

                OSS::publicUpload('tsingzone-gk','questions/'.$subFolder.$fileName,$localImageUrl);

                $str = str_replace($res[0],"<!--[img]".$fileName."[/img]-->",$str);
            }
        }

        return $str;
    }
}