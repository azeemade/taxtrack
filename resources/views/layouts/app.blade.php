<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ Str::title(env('APP_NAME')) }} Email</title>
    <style>
        /* Add your email styles here */
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .content {
            background-color: #fff;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="content">
            @yield('content')
        </div>
    </div>
</body>

</html>
