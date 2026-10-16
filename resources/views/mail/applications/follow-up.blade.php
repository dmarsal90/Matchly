<x-mail::message>
# {{ __('follow_up.mail.heading', ['company' => $company]) }}

{{ __('follow_up.mail.intro', ['title' => $title, 'date' => $date]) }}

<x-mail::panel>
{!! nl2br(e($draft)) !!}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('follow_up.mail.open') }}
</x-mail::button>

{{ __('follow_up.mail.reminder') }}
</x-mail::message>
