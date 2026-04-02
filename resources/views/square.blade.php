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
        #card-container {
            padding: 0;
            margin-bottom: 0;
        }
        .sq-card-wrapper {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        #card-container iframe {
            min-height: 40px !important;
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

    @if($data->status == 0)
    <form id="card-form" action="{{ route('payment.square') }}" method="post">
        <input type="hidden" name="id" value="{{ $data->id }}">
        <input type="hidden" name="amount" value="{{ $data->price }}">
        <input type="hidden" name="nonce" id="nonce" value="">
        @csrf
        <div class="container" style="height: 100vh;">
            <div id="error-message"></div>
            <div class="row h-100">
                <div class="col-md-5 pr-0">
                    <div class="payment-left">
                        <div class="payment-left-inner">
                            <h1>{{ $data->client->brand->name }}</h1>
                            <img src="{{ asset($data->client->brand->image) }}" width="180"/>
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

                            <div class="col-md-12 mb-2">
                                <label>Card Information</label>
                                <div id="card-container" class="form-control" style="height: auto; padding: 6px 12px;"></div>
                            </div>

                            <div class="col-md-12">
                                <label for="owner">Name on card</label>
                                <input type="text" id="cardname" name="owner" class="form-control" placeholder="{{ $data->client->name }}" required>
                            </div>

                            <div class="col-md-12">
                                <label for="country">Country or region</label>
                                <div class="row no-gap-row">
                                    <div class="col-md-6 pr-0">
                                        <select name="country" id="country" class="form-control" required style="border-top-right-radius:0!important;border-bottom-right-radius:0!important;border-bottom-left-radius:0!important;margin-bottom:0;">
                                            <option>Select Country *</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 pl-0 pr-0">
                                        <input name="city" id="city" required class="form-control" placeholder="City*" style="border-radius:0!important;margin-bottom:0;">
                                    </div>
                                    <div class="col-md-3 pl-0">
                                        <input name="zip" id="zip" class="form-control" placeholder="ZIP*" required style="border-top-left-radius:0!important;border-bottom-right-radius:0!important;border-bottom-left-radius:0!important;margin-bottom:0;">
                                    </div>
                                    <div class="col-md-8 pr-0">
                                        <input name="address" id="address" class="form-control" placeholder="Address*" required style="margin:0;border-top-left-radius:0!important;border-top-right-radius:0!important;border-bottom-right-radius:0!important;">
                                    </div>
                                    <div class="col-md-4 pl-0">
                                        <span id="state-code">
                                            <input type="text" id="state" class="form-control" placeholder="State*" name="state">
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4">
                                <div class="error hide">
                                    <p class="alert alert-danger"></p>
                                </div>
                                <button class="btn btn-info pl-5 pr-5 form-submit-btn" type="button" id="stripe-submit">Pay Now</button>
                                <div id="loader" style="display: none;">
                                    <img src="{{ asset('images/loader.gif') }}" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
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

    {{-- ✅ Load correct Square JS based on sandbox flag --}}
    @if($data->merchants->sandbox == 1)
        <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @else
        <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
    @endif

    <script>
        let user_country_code = "US";

        (function () {
            let country_list = country_and_states['country'];
            let states_list = country_and_states['states'];

            let option = '<option>select country</option>';
            for (let country_code in country_list) {
                let selected = (country_code == user_country_code) ? ' selected' : '';
                option += '<option value="' + country_code + '"' + selected + '>' + country_list[country_code] + '</option>';
            }
            document.getElementById('country').innerHTML = option;

            let text_box = '<input type="text" class="form-control" id="state" name="state" placeholder="State*" style="margin:0;border-top-left-radius:0!important;border-top-right-radius:0!important;">';
            let state_code_id = document.getElementById("state-code");

            function create_states_dropdown() {
                let country_code = document.getElementById("country").value;
                let states = states_list[country_code];
                if (!states) {
                    state_code_id.innerHTML = text_box;
                    return;
                }
                let option = '';
                if (states.length > 0) {
                    option = '<select id="state" name="state" class="form-control" style="margin:0;border-top-left-radius:0!important;border-top-right-radius:0!important;">';
                    for (let i = 0; i < states.length; i++) {
                        option += '<option value="' + states[i].code + '">' + states[i].name + '</option>';
                    }
                    option += '</select>';
                } else {
                    option = text_box;
                }
                state_code_id.innerHTML = option;
            }

            document.getElementById("country").addEventListener('change', create_states_dropdown);
            create_states_dropdown();
        })();

        let squareCard;

        async function initializeSquare() {
            if (!window.Square) {
                $('#error-message').html('<div class="alert alert-danger">Square.js failed to load.</div>');
                return;
            }

            const payments = window.Square.payments(
                '{{ $data->merchants->public_key }}',
                '{{ $data->merchants->square_location_id }}'
            );

            squareCard = await payments.card({
                style: {
                    '.input-container': {
                        borderColor: '#ced4da',
                        borderRadius: '4px',
                    },
                    '.input-container.is-focus': {
                        borderColor: '#80bdff',
                    },
                    input: {
                        fontSize: '14px',
                        color: '#495057',
                    },
                    'input::placeholder': {
                        color: '#6c757d',
                    },
                }
            });

            await squareCard.attach('#card-container');
        }

        $(document).ready(function () {
            initializeSquare().catch(function (err) {
                console.error('Square init error:', err);
                $('#error-message').html('<div class="alert alert-danger">Failed to load card fields: ' + err.message + '</div>');
            });

            $('#stripe-submit').on('click', async function (e) {
                e.preventDefault();

                if (!$('#cardname').val() || !$('#city').val() || !$('#zip').val() || !$('#address').val() || $('#country').val() === 'select country') {
                    $('#error-message').html('<div class="alert alert-danger">Please fill in all required fields.</div>');
                    return;
                }

                $('#loader').show();
                $('#stripe-submit').prop('disabled', true);
                $('#error-message').html('');

                try {
                    // ✅ No arguments — billing is handled on the backend
                    const result = await squareCard.tokenize();

                    if (result.status === 'OK') {
                        $('#nonce').val(result.token);
                        $('#card-form')[0].submit();
                    } else {
                        const errorMsg = result.errors.map(err => err.message).join(', ');
                        throw new Error(errorMsg);
                    }

                } catch (error) {
                    $('#error-message').html('<div class="alert alert-danger">' + error.message + '</div>');
                    $('#loader').hide();
                    $('#stripe-submit').prop('disabled', false);
                }
            });
        });
    </script>
</body>
</html>