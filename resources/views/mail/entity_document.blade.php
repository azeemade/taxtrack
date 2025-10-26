<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Notification</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f6f6f6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e9e9e9;
            border-radius: 4px;
            overflow: hidden;
        }
        .email-header {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e9e9e9;
        }
        .email-body {
            padding: 30px;
        }
        .email-footer {
            background-color: #f8fafc;
            padding: 20px;
            border-top: 1px solid #e9e9e9;
            font-size: 14px;
            color: #666;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3490dc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #2779bd;
        }
        .greeting {
            font-weight: bold;
            margin-bottom: 20px;
        }
        .message-body {
            margin-bottom: 20px;
        }
        .regards {
            margin-top: 30px;
        }
        .company-info {
            margin-top: 10px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>{{ ucwords($data['model']) }} for {{ $data['entity']['name'] }}</h1>
        </div>
        
        <div class="email-body">
            <p class="">Dear {{ $data['entity']['name'] }},</p>
            
            <div class="message-body">
                @if (isset($data['email_template']['mail_copy']) && $data['email_template']['mail_copy'])
                    <p>{{ $data['email_template']['mail_copy'] }}</p>
                @else
                    <p>Please find the attached {{ strtolower($data['model']) }} for your reference.</p>
                @endif
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $data['document_url'] }}" class="button">View attachment</a>
            </div>
        </div>
        
        <div class="email-footer">
            <div class="regards">Best regards,</div>
            <div class="company-info">
                <div>{{ $data['company']['name'] }},</div>
                <div>{{ $data['company']['address'] }}</div>
                <div>{{ $data['company']['phone_number'] }}</div>
            </div>
        </div>
    </div>
</body>
</html>


{{-- @component('mail::message')
    # {{ ucwords($data['model']) }} for {{ $data['entity']['name'] }}

    Dear {{ $data['entity']['name'] }},

    @if (isset($data['email_template']['mail_copy']) && $data['email_template']['mail_copy'])
        {{ $data['email_template']['mail_copy'] }}
    @else
        Please find the attached" . {{ strtolower($data['model']) }} . "for your reference
    @endif

    Please find the attached {{ strtolower($data['model']) }} for your reference.

    @component('mail::button', ['url' => $data['document_url']])
        View attachment
    @endcomponent

    Best regards,
    {{ $data['company']['name'] }},
    {{ $data['company']['address'] }}
    {{ $data['company']['phone_number'] }}
@endcomponent --}}
