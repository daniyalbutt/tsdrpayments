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
        .square-input {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
            height: 38px;
            width: 100%;
            background-color: #fff;
        }
        .square-input iframe {
            height: 38px !important;
        }
        .error-message {
            margin-top: 10px;
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
                                <label for="card_information">Card Information</label>
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Square Card Container - Required for proper tokenization -->
                                        <div id="card-container"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="owner">Name on card</label>
                                <div id="cardname"></div>
                            </div>
                            <div class="col-md-12">
                                <label for="country">Country or region</label>
                                <div class="row no-gap-row">
                                    <div class="col-md-6 pr-0">
                                        <select name="country" id="country" class="form-control" required style="border-top-right-radius: 0 !important;border-bottom-right-radius: 0px !important;border-bottom-left-radius: 0 !important;margin-bottom: 0;">
                                            <option>Select Country *</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 pl-0 pr-0">
                                        <input name="city" id="city" required class="form-control" placeholder="City*" value="" style="border-radius: 0 !important;margin-bottom: 0;">
                                    </div>
                                    <div class="col-md-3 pl-0">
                                        <input name="zip" id="zip" class="form-control" placeholder="ZIP*" required style="border-top-left-radius: 0 !important;border-bottom-right-radius: 0px !important;border-bottom-left-radius: 0 !important;margin-bottom: 0;">
                                    </div>
                                    <div class="col-md-8 pr-0">
                                        <input name="address" id="address" class="form-control" placeholder="Address*" value="" required style="margin: 0;border-top-left-radius: 0 !important;border-top-right-radius: 0px !important;border-bottom-right-radius: 0px !important;">
                                    </div>
                                    <div class="col-md-4 pl-0">
                                        <span id="state-code"><input type="text" id="state" class="form-control" placeholder="State*" name="state" value=""></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 mt-4">
                                <div class="error hide">
                                    <p class="alert alert-danger"></p>
                                </div>
                                <button class="btn btn-info pl-5 pr-5 form-submit-btn" id="pay-button" type="button">Pay Now</button>
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
    <!-- Square Web Payments SDK -->
    @if($data->merchants->sandbox == 1)
    <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @else
    <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
    @endif
    
    <script>
        // User country code for selected option
        let user_country_code = "US";
        
        (function () {
            // Get the country name and state name from the imported script.
            let country_list = country_and_states['country'];
            let states_list = country_and_states['states'];
        
            // creating country name drop-down
            let option = '';
            option += '<option>select country</option>';
            for(let country_code in country_list){
                // set selected option user country
                let selected = (country_code == user_country_code) ? ' selected' : '';
                option += '<option value="'+country_code+'"'+selected+'>'+country_list[country_code]+'</option>';
            }
            document.getElementById('country').innerHTML = option;
        
            // creating states name drop-down
            let text_box = '<input type="text" class="form-control" id="state" name="state">';
            let state_code_id = document.getElementById("state-code");
        
            function create_states_dropdown() {
                // get selected country code
                let country_code = document.getElementById("country").value;
                let states = states_list[country_code];
                // invalid country code or no states add textbox
                if(!states){
                    state_code_id.innerHTML = text_box;
                    return;
                }
                let option = '';
                if (states.length > 0) {
                    option = '<select id="state" name="state" class="form-control">\n';
                    for (let i = 0; i < states.length; i++) {
                        option += '<option value="'+states[i].code+'">'+states[i].name+'</option>';
                    }
                    option += '</select>';
                } else {
                    // create input textbox if no states 
                    option = text_box
                }
                state_code_id.innerHTML = option;
            }
        
            // country select change event
            const country_select = document.getElementById("country");
            country_select.addEventListener('change', create_states_dropdown);
        
            create_states_dropdown();
        })();

        // Square payment processing with proper card mounting
        $(document).ready(function() {
            let paymentsInstance = null;
            let card = null;
            let cardholderNameField = null;
            
            // Initialize Square payments on page load
            async function initializeSquare() {
                try {
                    // Initialize Square payments
                    paymentsInstance = window.Square.payments(
                        '{{ $data->merchants->public_key }}', 
                        '{{ $data->merchants->square_location_id }}'
                    );
                    
                    // Create card object
                    card = await paymentsInstance.card();
                    
                    // Mount the card input fields
                    await card.attach('#card-container');
                    
                    // Create and mount cardholder name field
                    cardholderNameField = await paymentsInstance.cardholderName();
                    await cardholderNameField.attach('#cardholder-container');
                    
                    console.log('Square initialized successfully');
                } catch (error) {
                    console.error('Failed to initialize Square:', error);
                    $('#error-message').html('<div class="alert alert-danger">Failed to initialize payment system: ' + error.message + '</div>');
                }
            }
            
            initializeSquare();
            
            // Handle payment button click
            $('#pay-button').on('click', async function() {
                // Show loader
                $('#loader').show();
                $('#pay-button').prop('disabled', true);
                $('#error-message').html('');
                
                try {
                    if (!card) {
                        throw new Error('Payment system not initialized. Please refresh the page.');
                    }
                    
                    // Get the cardholder name
                    const cardholderName = cardholderNameField ? cardholderNameField.getValue() : $('#cardname').val();
                    
                    // Tokenize the card with verification details
                    const tokenResult = await card.tokenize({
                        cardholderName: cardholderName,
                        billingContact: {
                            givenName: $('#user_name').val().split(' ')[0] || $('#user_name').val(),
                            familyName: $('#user_name').val().split(' ').slice(1).join(' ') || '',
                            email: $('#user_email').val(),
                            phone: $('#user_phone').val() || ''
                        },
                        billingAddress: {
                            addressLine1: $('#address').val(),
                            locality: $('#city').val(),
                            administrativeDistrictLevel1: $('#state').val(),
                            postalCode: $('#zip').val(),
                            country: $('#country').val()
                        }
                    });
                    
                    if (tokenResult.status === 'OK') {
                        // Set the nonce token
                        $('#nonce').val(tokenResult.token);
                        // Submit the form
                        $('#card-form')[0].submit();
                    } else {
                        throw new Error(tokenResult.errors[0].message);
                    }
                } catch (error) {
                    console.error('Square payment error:', error);
                    $('#error-message').html('<div class="alert alert-danger">' + error.message + '</div>');
                    $('#loader').hide();
                    $('#pay-button').prop('disabled', false);
                }
            });
        });
    </script>
</body>
</html>