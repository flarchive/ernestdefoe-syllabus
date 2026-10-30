<?php

namespace Ernestdefoe\Syllabus\Api;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Category;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/categories — the categories in order, each with its count
 * of published courses (one grouped query).
 *
 * POST (create), PATCH and DELETE /{id}, and POST /order (`ids`, in the order
 * they were dragged into) are for managers.
 */
class CategoriesController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private ConnectionInterface $db)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        if ($method === 'GET') {
            if (! $actor->hasPermission('syllabus.view')) {
                throw new PermissionDeniedException();
            }

            return $this->list();
        }

        if (! Access::isManager($actor)) {
            throw new PermissionDeniedException();
        }

        if ($method === 'POST' && str_ends_with($path, '/order')) {
            $ids = array_values(array_unique(array_map('intval', array_slice((array) ($this->body($request)['ids'] ?? []), 0, 500))));
            $this->db->transaction(function () use ($ids) {
                foreach ($ids as $position => $id) {
                    Category::query()->whereKey($id)->update(['position' => $position]);
                }
            });

            return $this->list();
        }

        if ($method === 'DELETE') {
            Category::query()->findOrFail($this->routeId($request))->delete();

            return new EmptyResponse(204);
        }

        $category = $method === 'POST' ? new Category() : Category::query()->findOrFail($this->routeId($request));
        $data = $this->body($request);

        if ($method === 'POST' || array_key_exists('name', $data)) {
            $name = trim(mb_substr((string) ($data['name'] ?? ''), 0, 100));
            if ($name === '') {
                $this->invalid('name', 'title_required');
            }
            $category->name = $name;
            $category->slug = Str::limit(Str::slug($name), 90, '') ?: 'category';
        }
        if (array_key_exists('description', $data)) {
            $description = trim(mb_substr((string) $data['description'], 0, 500));
            $category->description = $description === '' ? null : $description;
        }
        if ($method === 'POST') {
            $category->position = 1 + (int) Category::query()->max('position');
            $category->created_at = Carbon::now();
        }
        $category->save();

        return $this->list();
    }

    private function list(): ResponseInterface
    {
        $counts = $this->db->table('syllabus_courses')->where('status', 'published')->whereNotNull('category_id')
            ->groupBy('category_id')->selectRaw('category_id, COUNT(*) as aggregate')->pluck('aggregate', 'category_id')->all();

        return new JsonResponse(['data' => Category::query()->orderBy('position')->orderBy('id')->get()->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'courseCount' => (int) ($counts[$c->id] ?? 0),
        ])->values()->all()]);
    }
}
