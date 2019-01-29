<?php
/**
 * Created by PhpStorm.
 * User: xieyan
 * Date: 2019/1/21
 * Time: 下午2:47
 */
namespace App\Models;
use \Illuminate\Database\Eloquent\Model;

class QuestionModel extends Model
{
    protected $connection = 'ht_question';
    public $timestamps = false;

}