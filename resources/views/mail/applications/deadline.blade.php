<x-mail::message>
# {{ __('deadline.mail.heading', ['company' => $company]) }}

{{ __('deadline.mail.intro', ['title' => $title, 'date' => $date]) }}

<x-mail::button :url="$url">
{{ __('deadline.mail.open') }}
</x-mail::button>

{{ __('deadline.mail.reminder') }}
</x-mail::message>
