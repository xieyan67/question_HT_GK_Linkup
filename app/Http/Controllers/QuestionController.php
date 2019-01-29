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

        return view('question',compact('questionGK','questionHT','questionType','htAll','gkAll','knowledge'));
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
}