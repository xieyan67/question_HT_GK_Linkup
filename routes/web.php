<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'QuestionController@index');

Route::match(['get','post'],'question/index','QuestionController@index')->name('question.index');
Route::match(['get','post'],'question/save_question','QuestionController@saveQuestion')->name('question.save_question');
Route::match(['get','post'],'question/question_query','QuestionController@questionQuery')->name('question.question_query');
















