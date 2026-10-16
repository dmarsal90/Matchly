<x-mail::message>
# {{ trans_choice('radar.mail.heading', $count, ['company' => $company, 'count' => $count]) }}

@foreach ($jobs as $job)
- [{{ $job['title'] }}]({{ $job['url'] }})
@endforeach
@if ($count > count($jobs))

{{ trans_choice('radar.mail.more', $count - count($jobs), ['count' => $count - count($jobs)]) }}
@endif

<x-mail::subcopy>
{{ __('radar.mail.why', ['company' => $company]) }} [{{ __('radar.mail.manage') }}]({{ $manageUrl }})
</x-mail::subcopy>
</x-mail::message>
