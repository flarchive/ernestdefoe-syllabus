{{-- Every Syllabus email: one translated sentence and a link. Course and lesson titles are escaped before they reach the Markdown converter. --}}
@php($mail = $blueprint->email())
<x-mail::html.notification>
    <x-slot:body>
        {!! $formatter->convert($translator->trans($mail['key'], array_map(fn ($value) => e($value), $mail['params']) + [
            '{url}' => $url->to('forum')->route($mail['route'], $mail['routeParams']),
        ])) !!}
    </x-slot:body>
</x-mail::html.notification>
