<?php

namespace App\Services;

use App\Models\AgendaDay;

class AgendaService
{
    public static function getAgenda()
    {
        try {
            $agenda = AgendaDay::with([
                'sessions' => function($query) {
                    $query->select(
                        'agenda_point_name AS name',
                        'agenda_day_id',
                        'agenda_point_id',
                        'agenda_point_assignable AS draggable',
                        'agenda_point_start_time AS startTime',
                        'agenda_point_sort_id'
                    )->orderBy('agenda_point_sort_id');
                },
                'sessions.lectures' => function($query) {
                    $query->with([
                        'user' => function($userQuery) {
                            $userQuery->select('user_id');
                        },
                        'user.userData' => function($userDataQuery) {
                            $userDataQuery->select(
                                'user_id',
                                'data_name AS name',
                                'data_surname AS surname',
                                'data_title AS title',
                                'data_company AS affiliation'
                            );
                        },
                        'ArticleMainTopics' => function($topicsQuery) {
                            $topicsQuery->select('article_main_topic');
                        }
                    ])->select(
                        'articles.article_id',
                        'article_title AS name',
                        'article_docx AS download_link',
                        'user_id',
                        'article_authors AS authors',
                        'sort_id',
                        'agenda_point_article_id'
                    )->orderBy('sort_id');
                },
            ])->select('agenda_day_name', 'agenda_day_id')->get();
        } catch (\Exception $exception) {
            return ['error' => $exception];
        }
        return $agenda;
    }
}
