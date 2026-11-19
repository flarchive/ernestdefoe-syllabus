<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Enrolment;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/courses — the catalogue.
 *
 * `filter[q]` searches titles and summaries, `filter[category]` narrows to a
 * category, `filter[difficulty]` to a level, `filter[featured]=1` to featured
 * courses. `filter[mine]=1` is the actor's enrolled courses, most recently
 * studied first ("continue where you left off"). `filter[teaching]=1` is
 * the courses they teach, drafts included.
 */
class ListCoursesController implements RequestHandlerInterface
{
    public const PAGE = 24;

    public function __construct(private Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();
        $filter = (array) Arr::get($params, 'filter', []);
        $limit = max(1, min(self::PAGE, (int) Arr::get($params, 'page.limit', self::PAGE)));

        $query = Course::query()->visibleTo($actor)->with(['user', 'category']);
        if (Access::canCreate($actor)) {
            // Who may edit which card: the co-instructors, once for the page.
            $query->with('instructors');
        }

        $mine = ! empty($filter['mine']) && ! $actor->isGuest();
        if ($mine) {
            // Wrapped by the grammar: the join alias takes the table prefix too.
            $grammar = $query->getQuery()->getGrammar();
            $query->where('status', 'published')
                ->joinSub(
                    Enrolment::query()->where('user_id', $actor->id)->select(['course_id', 'last_seen_at', 'created_at as enrolled_at']),
                    'mine',
                    'mine.course_id',
                    '=',
                    'syllabus_courses.id'
                )
                ->select('syllabus_courses.*')
                ->orderByRaw('COALESCE('.$grammar->wrap('mine.last_seen_at').', '.$grammar->wrap('mine.enrolled_at').') DESC');
        } elseif (! empty($filter['teaching']) && Access::canCreate($actor)) {
            if (! Access::isManager($actor)) {
                $query->taughtBy($actor);
            }
            $query->orderByDesc('updated_at');
        } else {
            $query->where('status', 'published');
        }

        if (is_numeric($filter['category'] ?? null)) {
            $query->where('category_id', (int) $filter['category']);
        }
        if (is_string($filter['difficulty'] ?? null) && in_array($filter['difficulty'], Course::DIFFICULTIES, true)) {
            $query->where('difficulty', $filter['difficulty']);
        }
        if (! empty($filter['featured'])) {
            $query->where('featured', true);
        }
        if (is_string($q = $filter['q'] ?? null) && trim($q) !== '') {
            // Bound, without LIKE's wildcards (escaping them differs on
            // SQLite), and lower-cased on both sides for PostgreSQL.
            $words = '%'.trim(mb_strtolower(str_replace(['%', '_', '\\'], ' ', mb_substr(trim($q), 0, 100)))).'%';
            $grammar = $query->getQuery()->getGrammar();
            $query->where(fn ($w) => $w->whereRaw('LOWER('.$grammar->wrap('syllabus_courses.title').') LIKE ?', [$words])
                ->orWhereRaw('LOWER('.$grammar->wrap('syllabus_courses.summary').') LIKE ?', [$words]));
        }

        if (! $mine) {
            if (($params['sort'] ?? null) === 'popular') {
                $query->orderByDesc('enrolment_count');
            }
            $query->orderByDesc('featured')->orderByDesc('published_at')->orderByDesc('syllabus_courses.id');
        }

        $offset = max(0, (int) Arr::get($params, 'page.offset', 0));
        $courses = $query->skip($offset)->take($limit + 1)->get();
        $more = $courses->count() > $limit;

        return new JsonResponse([
            'data' => $this->presenter->courses($courses->take($limit), $actor),
            'meta' => [
                'canCreate' => Access::canCreate($actor),
                'next' => $more ? $offset + $limit : null,
            ],
        ]);
    }
}
