@component('mail::message')
    # {{ ucwords($data['model']) }} for {{ $data['entity']['name'] }}

    Dear {{ $data['entity']['name'] }},

    Please find the attached {{ strtolower($data['model']) }} for your reference.

    Best regards,
    {{ $data['company']['name'] }},
    {{ $data['company']['adress'] }}
    Contact: {{ $data['company']['phone_number'] }}
@endcomponent
