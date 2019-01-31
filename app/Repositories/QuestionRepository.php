<?php
/**
 * Created by PhpStorm.
 * User: xieyan
 * Date: 2019/1/24
 * Time: 下午2:10
 */
namespace App\Repositories;

use App\Models\QuestionModel;
use Illuminate\Support\Facades\DB;

class QuestionRepository{

    protected $model;

    public function __construct(QuestionModel $question)
    {
        $this->model = $question;
    }

    /** 获取公考提
     * @param $knowledge
     * @return array
     */
    public function getQuestionGk($knowledge,$qAnswer)
    {

        $count = $this->getQuestionGkCount($knowledge,$qAnswer);
        $offset = ($count - 5) > 0 ? rand(0,$count - 5) : 0;

        return $this->model->select(['q.id','q.question','q.qAnswer'])
            ->from('question AS q')
            ->join('question_ht_gk AS qh',function($join){
                $join->on('q.id','qh.gk_qid')->where('qh.status',0)->whereNull('qh.repeat_qid');
            })
            ->where('q.knowledge_name',$knowledge)
            ->where('q.qAnswer',$qAnswer)
            ->groupBy(['q.id'])
            ->offset($offset)
            ->limit(5)
            ->get()->toArray();
    }

    public function getQuestionGkCount($knowledge,$qAnswer)
    {
        return $this->model->fromQuery("SELECT q.id FROM question AS q JOIN question_ht_gk AS qh ON q.id = qh.gk_qid AND qh.status = 0 AND qh.repeat_qid IS NULL 
          WHERE q.knowledge_name = '$knowledge' AND q.qAnswer = '$qAnswer' GROUP BY q.id")->count();
    }

    /** 获取question_12_11 数据
     * @param $knowledge
     * @return array
     */
    public function getQuestionHT($knowledge,$qAnswer)
    {
        $count = $this->getQuestionHTCount($knowledge,$qAnswer);
        $offset = ($count - 5) > 0 ? rand(0,$count - 5) : 0;

        return $this->model->select(['q.id','q.qTitle','q.qAnswer'])
            ->from('question_12_11 AS q')
            ->join('question_ht_gk AS qh',function($join){
                $join->on('q.id','qh.ht_qid')->where('qh.status',0)->whereNull('qh.repeat_qid');
            })
            ->where('q.qExamineCenter',$knowledge)
            ->where('q.qAnswer',$qAnswer)
            ->groupBy(['q.id'])
            ->offset($offset)
            ->limit(5)
            ->get()->toArray();
    }

    public function getQuestionHTCount($knowledge,$qAnswer)
    {
        return $this->model->fromQuery("SELECT q.id FROM question_12_11 AS q JOIN question_ht_gk AS qh ON q.id = qh.ht_qid AND qh.status = 0  AND qh.repeat_qid IS NULL 
          WHERE q.qExamineCenter = '$knowledge' AND q.qAnswer = '$qAnswer' GROUP BY q.id")->count();
    }

    public function getQusertionAnswerByKnowledge($knowledge)
    {
        return $this->model->select('qAnswer')
                    ->from('question_ht_gk')
                    ->where('knowledge',$knowledge)
                    ->where('status',0)
                    ->whereNull('repeat_qid')
                    ->groupBy('qAnswer')
                    ->get()->toArray();
    }

    /** 有重题
     * @param $htQid
     * @param $gkQiq
     * @return bool
     */
    public function repeatQuestionHTGK($htQid,$gkQiq)
    {
        DB::beginTransaction();
       $rep = $this->model->from('question_ht_gk')
            ->where('ht_qid' , $htQid)
            ->where('gk_qid',$gkQiq)
            ->where('status',0)
            ->whereNull('repeat_qid')
            ->update(['status' => 1]);

       $res = $this->model->from('question_ht_gk')
            ->where('ht_qid' , $htQid)
            ->where('gk_qid','!=',$gkQiq)
            ->where('status',0)
            ->whereNull('repeat_qid')
            ->update(['status' => 2]);
        if($rep && $res){
            DB::commit();
        }else{
            DB::rollBack();
        }
       return $rep && $res;
    }

    public function updateQuestionStatusBy($htQids,$gkQids)
    {
        return $this->model->from('question_ht_gk')
            ->whereIn('ht_qid',$htQids)
            ->whereIn('gk_qid',$gkQids)
            ->where('status',0)
            ->whereNull('repeat_qid')
            ->update(['status' => 2]);
    }

    public function getResidueNum($knowledge)
    {
        return $this->model->from('question_ht_gk')
            ->where('knowledge',$knowledge)
            ->where('status',0)
            ->whereNull('repeat_qid')
            ->count();
    }

    public function selfQuestionRepeatHT($qid,$repeatQids)
    {
        return $this->model->from('question_ht_gk')
            ->whereIn('ht_qid',$repeatQids)
            ->whereNull('repeat_qid')
            ->update(['repeat_qid' => $qid]);
    }

    public function selfQuestionRepeatGK($qid,$repeatQids)
    {
       $htRes = $this->model->from('question_ht_gk')
            ->whereIn('gk_qid',$repeatQids)
            ->whereNull('repeat_qid')
            ->update(['status' => 2]);

        foreach ($repeatQids as $repeat_qid){
            $this->model->from('question_gk_repeat')
                ->insert(['gk_qid' => $qid,'repeat_qid' => $repeat_qid]);
        }

        return $htRes;
    }

}