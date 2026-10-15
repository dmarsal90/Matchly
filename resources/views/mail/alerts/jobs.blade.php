<x-mail::message>
# {{ trans_choice('alerts.mail.heading.'.$frequency->value, $total, ['count' => $total, 'name' => $name]) }}

{{ __('alerts.mail.intro', ['name' => $name]) }}

@foreach ($jobs as $job)
**[{{ $job['title'] }}]({{ $job['url'] }})**
{{ $job['company'] }}@if ($job['location']) · {{ $job['location'] }}@endif
@if ($job['score'] !== null) · {{ __('alerts.mail.score', ['score' => $job['score']]) }}@endif


@endforeach
@if ($total > count($jobs))
{{ trans_choice('alerts.mail.more', $total - count($jobs), ['count' => $total - count($jobs)]) }}
@endif

<x-mail::button :url="$listingUrl">
{{ __('alerts.mail.view_all') }}
</x-mail::button>

{{ __('alerts.mail.reminder') }}

<x-mail::subcopy>
{{ __('alerts.mail.why', ['name' => $name]) }} [{{ __('alerts.mail.manage') }}]({{ $manageUrl }}) · [{{ __('alerts.mail.unsubscribe') }}]({{ $unsubscribeUrl }})
</x-mail::subcopy>
</x-mail::message>
