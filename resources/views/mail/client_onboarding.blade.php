@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div>
                <div class="card">
                    <div class="card-header">Welcome to {{ ucwords(env('APP_NAME')) }}! Your Login Details</div>

                    <div class="card-body">
                        <p>Dear {{ $data['entity_name'] }},</p>

                        <p>We're excited to welcome you to the {{ ucwords(env('APP_NAME')) }} family! Thank you for choosing
                            our
                            platform.</p>

                        <p><strong>Your Login Details:</strong></p>

                        <ul>
                            <li>Email: <strong>{{ $data['email'] }}</strong></li>
                            <li>Password: <strong>{{ $data['password'] }}</strong></li>
                        </ul>

                        <p><strong>Accessing Your Account:</strong></p>

                        <ol>
                            <li>Visit our website at
                                <a href="{{ env('WEB_URL') }}" target="_blank"
                                    rel="noopener noreferrer">{{ env('WEB_URL') }}</a>
                            </li>
                            <li>Click on the "Login" button.</li>
                            <li>Enter your email and password.</li>
                        </ol>

                        <p><strong>Important Notes:</strong></p>

                        <ul>
                            <li><strong>Password Change:</strong> If you need to change your password, please login to your
                                account and
                                follow
                                the password change instructions.</li>
                            <li><strong>Support:</strong> If you have any questions or encounter any issues, please don't
                                hesitate to
                                contact our support team at {{ env('APP_SUPPORT_EMAIL') }}.</li>
                        </ul>

                        <p>We're committed to providing you with a seamless and valuable experience. Please let us know if
                            you have any questions or require further assistance.</p>

                        <p>Best regards,</p>

                        <p>Support team,</p>
                        <p>{{ ucwords(env('APP_NAME')) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        /* Container */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Card */
        .card {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 20px;
        }

        /* Header */
        .card-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Paragraph */
        p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        /* Lists */
        ul {
            list-style: none;
            padding-left: 20px;
        }

        /* Links */
        a {
            color: #007bff;
            text-decoration: none;
        }
    </style>
@endsection
