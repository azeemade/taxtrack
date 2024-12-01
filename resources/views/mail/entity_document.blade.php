@component('mail::message')
    # {{ ucwords($data['model']) }} for {{ $data['entity']['name'] }}

    Dear {{ $data['entity']['name'] }},

    Please find the attached {{ strtolower($data['model']) }} for your reference.

    @component('mail::button', ['url' => $data['document_url']])
        View attachment
    @endcomponent

    Best regards,
    {{ $data['company']['name'] }},
    {{ $data['company']['address'] }}
    {{ $data['company']['phone_number'] }}
@endcomponent
