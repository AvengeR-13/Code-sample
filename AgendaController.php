<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgendaUpdateRequest;
use App\Models\AgendaDay;
use App\Models\AgendaPoint;
use App\Services\AgendaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgendaController extends Controller
{

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $agenda = AgendaService::getAgenda();
        if(is_array($agenda) && isset($agenda['error'])) {
            return Response()->json(['Error' => $agenda['error']], 500);
        } else {
            return Response()->json($agenda);
        }
    }

    /**
     * @param AgendaUpdateRequest $request
     * @return JsonResponse
     */
    public function update(AgendaUpdateRequest $request): JsonResponse
    {
        $agendaPoint = new AgendaPoint();
        $agendaDay = new AgendaDay();
        $agendaPointArticle = new AgendaPointArticle();
        try {
            $agenda = $request->get('agenda');
            foreach ($agenda as $day) {
                $tempAgendaDay = $agendaDay->updateOrCreate(['agenda_day_id' => $day['agenda_day_id']],
                    [
                        'agenda_day_name' => $day['agenda_day_name']
                    ]);
                foreach ($day['sessions'] as $session_sort_id => $session) {
                    $tempAgendaPoint = $agendaPoint->updateOrCreate(['agenda_point_id' => $session['agenda_point_id'] ?? ''],
                        [
                            'agenda_point_name' => $session['name'],
                            'agenda_point_assignable' => (bool)$session['draggable'],
                            'agenda_point_start_time' => date('2022-09-01 '.$session['startTime']),
                            'agenda_point_sort_id' => $session_sort_id + 1,
                            'agenda_day_id' => $day['agenda_day_id']
                        ]);
                    $tempAgendaPoint->save();
                    foreach ($session['lectures'] as $lecture_sort_id => $lecture) {
                        $agendaPointArticle::updateOrCreate(['agenda_point_article_id' => $lecture['agenda_point_article_id'] ?? ''],
                            [
                                'agenda_point_id' => $session['agenda_point_id'] ?? $tempAgendaPoint->agenda_point_id,
                                'article_id' => isset($lecture['pivot']) ? $lecture['pivot']['article_id'] : $lecture['id'],
                                'sort_id' => $lecture_sort_id + 1
                            ]);
                    }
                }
                $tempAgendaDay->save();
            }
            $this->unlinkArticles($request->get('unassignedArticles'));
            $this->deleteSessions($request->get('deletedSessions'));
        } catch (Exception $exception) {
            DB::rollback();
            return Response()->json(['Error' => $exception->getMessage(), 'Line' => $exception->getLine(), 'Trace' => $exception->getTrace()], 500);
        }
        return Response()->json(['Success']);
    }

    /**
     * @param array $articles
     */
    private function unlinkArticles(array $articles)
    {
        foreach ($articles as $article) {
            if(isset($article['pivot'])) {
                AgendaPointArticle::destroy($article['agenda_point_article_id']);
            }
        }
    }

    /**
     * @param array $sessions
     */
    private function deleteSessions(array $sessions)
    {
        foreach ($sessions as $session) {
            if (isset($session['agenda_point_id'])) {
                AgendaPoint::destroy($session['agenda_point_id']);
            }
        }
    }

    /**
     * @param string $change - Should be 'day', 'point' or 'lecture'.
     * @param bool $direction - For forward direction must be 1, for backward direction must be 0
     */
    public function setActualAgenda(string $change, bool $direction): JsonResponse
    {
        switch ($change) {
            case 'lecture' :
                if (Cache::has('agenda_point_lecture')) {
                    if ($direction) {
                        Cache::increment('agenda_point_lecture');
                    } else {
                        if (Cache::get('agenda_point_lecture') > 1) Cache::decrement('agenda_point_lecture');
                    }
                } else Cache::put('agenda_point_lecture', 1);
                return Response()->json(['Success']);
            case 'point' :
                if (Cache::has('agenda_point')) {
                    if ($direction) {
                        Cache::increment('agenda_point');
                    } else {
                        if (Cache::get('agenda_point') > 1) Cache::decrement('agenda_point');
                    }
                } else Cache::put('agenda_point', 1);
                Cache::put('agenda_point_lecture', 1);
                return Response()->json(['Success']);
            case 'day' :
                if (Cache::has('agenda_day')) {
                    if ($direction) {
                        Cache::increment('agenda_day');
                    } else {
                        if (Cache::get('agenda_day') > 1) Cache::decrement('agenda_day');
                    }
                } else Cache::put('agenda_day', 1);
                Cache::put('agenda_point', 1);
                Cache::put('agenda_point_lecture', 1);
                return Response()->json(['Success']);
            default:
                return Response()->json(['Error' => 'Wrong method parameter provided, should be: "day", "point" or "lecture"'], 400);
        }
    }

    /**
     * @return JsonResponse
     */
    public function getActualAgenda(): JsonResponse
    {
        $agenda_day_cache = Cache::get('agenda_day', function () {
            Cache::put('agenda_day', 1);
            return 1;
        });
        $agenda_point_cache = Cache::get('agenda_point', function () {
            Cache::put('agenda_point', 1);
            return 1;
        });
        $agenda_lecture_cache = Cache::get('agenda_point_lecture', function () {
            Cache::put('agenda_point_lecture', 1);
            return 1;
        });
        $agenda_day = AgendaDay::findOrFail($agenda_day_cache);
        $agenda_point = AgendaPoint::where('agenda_day_id', $agenda_day_cache)->where('agenda_point_sort_id', $agenda_point_cache)->first();
        $agenda_point_article = AgendaPointArticle::where('agenda_point_id', $agenda_point->agenda_point_id)->where('sort_id', $agenda_lecture_cache)->with('articles')->first();

        return Response()->json([
            'agendaDay' => $agenda_day,
            'agendaPoint' => $agenda_point,
            'agendaPointArticle' => $agenda_point_article
        ]);
    }
}
