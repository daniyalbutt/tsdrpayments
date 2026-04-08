<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('front/css/style.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <style>
        .StripeElement {
            box-sizing: border-box;
            height: 45px;
            padding: 12px 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            background-color: white;
            transition: box-shadow 150ms ease;
            border-width: 1px;
            border-color: lightgrey;
            border-style: solid;
            margin-bottom: 10px;
            margin-top: 3px;
        }

        .StripeElement--focus {
            box-shadow: 0 1px 3px 0 #cfd7df;
        }

        .StripeElement--invalid {
            border-color: #fa755a;
        }

        .StripeElement--webkit-autofill {
            background-color: #fefde5 !important;
        }
        /*NEW CSS*/
        *{
            font-family: "Oswald", sans-serif;
            font-optical-sizing: auto;
            font-style: normal;
        }
        .payment-right {
            padding: 40px;
            box-shadow: -2px -2px 2px 0 #e5e5e5;
            background-color: white;
            height: 100%;
            display: flex;
            align-items: center;
        }
        input, textarea, select {
            border-radius: 8px !important;
            font-size: 15px !important;
            height: 42px !important;
            padding-left: 15px !important;
        }
        label {
            font-weight: 400;
            font-size: 14px;
            color: #0000008a;
        }
        select#state{
            margin: 0;
            border-top-left-radius: 0px !important;
            border-bottom-left-radius: 0px !important;
            border-top-right-radius: 0 !important;
        }
        .payment-left {
            display: flex;
            align-items: center;
            height: 100%;
            background-color: white;
            padding-left: 40px;
        }
        
        .payment-left-inner h3 {
            font-weight: bold;
            font-size: 25px;
        }
        .payment-left-inner h2 {
            font-weight: bold;
            font-size: 40px;
            margin: 0;
            color: #17a2b8;
        }
        .payment-left-inner img {
            margin-bottom: 20px;
        }
        .payment-left-inner h1 {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-size: 32px;
        }
        input#cardnumber {
            border-bottom-left-radius: 0 !important;
            border-bottom-right-radius: 0px !important;
            margin-bottom: 0;
            border-top-right-radius: 0px !important;
            margin-top: 0;
            border-right: 0;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
        
        input#expiry {
            margin: 0;
            border-top-left-radius: 0 !important;
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }
        input#cvv {
            border-top-left-radius: 0 !important;
            border-top-right-radius: 0px !important;
            margin-top: 0;
            border-bottom-left-radius: 0 !important;
        }
        input#exp_year {
            margin-top: 0;
            border-radius: 0px !important;
        }
        .form-control::placeholder {
            color: #d1d1d1;
            opacity: 1; /* Firefox */
        }
        
        .form-control::-ms-input-placeholder { /* Edge 12 -18 */
            color: #d1d1d1;
        }
        
        .error.hide {
            display: none;
        }
        
        .form-control.required {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .form-control.required::placeholder {
            color: #721c24;
            opacity: 1; /* Firefox */
        }
        
        .form-control.required::-ms-input-placeholder { /* Edge 12 -18 */
            color: #721c24;
        }
        
        span#basic-addon2 {
            background-color: white;
            padding: 0;
            border-bottom-right-radius: 0;
            border-left: 0;
            padding-right: 10px;
        }
        
        .input-group-append {
            margin: 0;
        }
    </style>
</head>

<body>
    @if (Session::has('error'))
        <p class="alert alert-danger">{{ Session::get('error') }}</p>
    @endif
    @if (session('message'))
        <div class="success-alert alert alert-info">{{ session('message') }}</div>
    @endif

    @if (Session::has('stripe_error'))
        <p class="alert alert-danger">{{ Session::get('stripe_error') }}</p>
    @endif
    
    @if ($data->status == 0)
    <div id="card-form">
        <input type="hidden" name="id" value="{{ $data->id }}">
        <input type="hidden" name="amount" value="{{ $data->price }}">
        @csrf
        <div class="container" style="height: 100vh;">
            <div id="error-message"></div>
            <div class="row h-100">
                <div class="col-md-5 pr-0">
                    <div class="payment-left">
                        <div class="payment-left-inner">
                            @if($data->client->brand->id != 13)
                            <h1>{{ $data->client->brand->name }}</h1>
                            @endif
                            <img src="{{ asset($data->client->brand->image) }}" width="180"/>
                            <!--<h3>{{ $data->package }}</h3>-->
                            <h2>${{ $data->price }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-7 pl-0">
                    <div class="payment-right">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label for="user_name">Name</label>
                                <input id="user_name" name="user_name" class="form-control" type="text" value="{{ $data->client->name }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label for="user_email">Email Address</label>
                                <input id="user_email" name="user_email" class="form-control" type="email" value="{{ $data->client->email }}">
                            </div>
                            <div class="col-md-12 mt-4">
                                <div class="error hide">
                                    <p class="alert alert-danger"></p>
                                </div>
                                <form action="{{ route('paypal.create', $data->id) }}" method="POST" style="display:inline-block; margin-left:10px;" onsubmit="handlePaypalSubmit(this)">
                                    @csrf
                                    <button type="submit" id="paypal-btn" class="btn pl-5 pr-5" style="background:#FFC439; color:#003087; font-weight:bold;">
                                        <img src="{{ asset('images/paypal-logo.png') }}"
                                            alt="PayPal" height="20" style="vertical-align:middle;margin-right:5px;width: auto;">
                                    </button>
                                </form>
                                <div id="loader" style="display: none;">
                                    <img src="{{ asset('images/loader.gif') }}" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>
    @else
        @if ($data->status == 2)
            <div class="success-alert alert alert-info">PAID!</div>
        @elseif($data->status == 1)
            <div class="success-alert alert alert-info">{{ $data->return_response }}</div>
        @endif
    @endif
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"
        integrity="sha512-3P8rXCuGJdNZOnUx/03c1jOTnMn3rP63nBip5gOP2qmUh5YAdVAvFZ1E+QLZZbC1rtMrQb+mah3AfYW11RUrWA=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{ asset('front/js/country-states.js') }}"></script>
    <script>
        function handlePaypalSubmit(form) {
            var btn = document.getElementById('paypal-btn');
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
            document.getElementById('loader').style.display = 'inline-block';
            return true; // allow form to submit
        }
    </script>
</body>

</html>
