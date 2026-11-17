<?php

namespace Ernestdefoe\Syllabus\Content;

use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\RichText;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * A course page's title, summary and cover in the HTML itself, for link
 * previews, search engines and readers without JavaScript. One query, on this
 * page only, through the same visibility as the API: a draft gives nothing
 * away. Lesson content is never put here.
 */
class CourseDocument
{
    public function __construct(private RichText $rich, private UrlGenerator $url, private Uploads $uploads)
    {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $id = (int) Arr::get($request->getQueryParams(), 'id');
        if ($id < 1 || $id > 2147483647) {
            return $document;
        }

        $course = Course::query()->visibleTo(RequestUtil::getActor($request))->find($id);
        if (! $course) {
            return $document;
        }

        $summary = $course->summary
            ?: Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $this->rich->html($course->description, $course)))), 200);
        $url = $this->url->to('forum')->route('syllabus.course', ['id' => $course->id.'-'.$course->slug]);
        $cover = $this->uploads->coverUrl($course);

        $document->title = $course->title;
        $document->canonicalUrl = $url;
        if ($summary !== '') {
            $document->meta['description'] = $summary;
        }
        $document->head[] = '<meta property="og:title" content="'.e($course->title).'">';
        $document->head[] = '<meta property="og:type" content="website">';
        $document->head[] = '<meta property="og:url" content="'.e($url).'">';
        if ($summary !== '') {
            $document->head[] = '<meta property="og:description" content="'.e($summary).'">';
        }
        if ($cover) {
            $document->head[] = '<meta property="og:image" content="'.e($cover).'">';
        }
        $document->content = '<h1>'.e($course->title).'</h1>'.($summary !== '' ? '<p>'.e($summary).'</p>' : '');

        return $document;
    }
}
