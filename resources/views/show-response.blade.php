@extends('layouts.front-app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
            <li class="breadcrumb-item"><a href="javascript:void(0)">Invoice #{{ $data->id }}</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Show Response</a></li>
        </ol>
    </div>

    @php
        // Safely decode JSON — handles double-encoded, plain text, and concatenated JSONs
        function safeDecode($value) {
            if (is_null($value) || $value === '') return null;

            // Already an array
            if (is_array($value)) return $value;

            // Try direct JSON decode
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Double-encoded string
            if (is_string($decoded)) {
                $second = json_decode($decoded, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($second)) {
                    return $second;
                }
            }

            // Plain text (not JSON) — return as-is
            return $value;
        }

        // Render nested array as HTML table
        function renderTable($data) {
            if (!is_array($data) || empty($data)) {
                return '<p class="text-muted mb-0">No data available.</p>';
            }
            $output = '<table class="table table-bordered table-sm mb-0">';
            foreach ($data as $key => $value) {
                $label = ucwords(str_replace(['_', '-'], ' ', $key));
                $output .= '<tr>';
                $output .= '<th style="width:35%;background:#f8f9fa;vertical-align:top;">' . e($label) . '</th>';
                if (is_array($value)) {
                    $output .= '<td>' . renderTable($value) . '</td>';
                } else {
                    $display = (isset($value) && $value !== '')
                        ? e($value)
                        : '<span class="text-muted">N/A</span>';
                    $output .= '<td>' . $display . '</td>';
                }
                $output .= '</tr>';
            }
            $output .= '</table>';
            return $output;
        }

        $paymentData   = safeDecode($data->payment_data);
        $returnData    = safeDecode($data->return_response);
        $squareData    = safeDecode($data->square_response);
        $authorizeData = safeDecode($data->authorize_response);

        $merchantLabel = $data->merchants->name ?? 'Unknown Gateway';

        $statusMap = [
            0 => ['label' => 'Pending',  'class' => 'warning'],
            1 => ['label' => 'Declined', 'class' => 'danger'],
            2 => ['label' => 'Paid',     'class' => 'success'],
        ];
        $statusInfo = $statusMap[$data->status] ?? ['label' => 'Unknown', 'class' => 'secondary'];

        // Decide which response to show per gateway
        // Stripe(0): return_response is the charge JSON
        // PayPal(5): authorize_response is the full PayPal order JSON
        // Authorize(4): authorize_response is the transaction JSON
        // Square(6): square_response
        if ($data->merchant == 5) {
            // PayPal — full response is in authorize_response
            $gatewayResponse = is_array($authorizeData) ? $authorizeData : null;
        } elseif ($data->merchant == 4) {
            // Authorize.net
            $gatewayResponse = is_array($authorizeData) ? $authorizeData : null;
        } elseif (in_array($data->merchant, [6, 7])) {
            // Square / PayKings
            $gatewayResponse = is_array($squareData) ? $squareData
                             : (is_array($returnData) ? $returnData : null);
        } else {
            // Stripe and others — return_response has the charge JSON
            $gatewayResponse = is_array($returnData) ? $returnData : null;
        }

        // Plain-text decline/success message (non-JSON return_response)
        $plainReturnMessage = (!is_array($returnData) && !empty($returnData))
            ? $returnData
            : null;
    @endphp

    {{-- ===== Summary Card ===== --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        Invoice <strong>#{{ $data->id }}</strong>
                        &mdash; {{ $merchantLabel }}
                    </h4>
                    <span class="badge badge-{{ $statusInfo['class'] }} p-2" style="font-size:13px;">
                        {{ $statusInfo['label'] }}
                    </span>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-sm">
                        <tr>
                            <th style="width:35%;background:#f8f9fa;">Package</th>
                            <td>{{ $data->package ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th style="background:#f8f9fa;">Amount</th>
                            <td>${{ number_format($data->price, 2) }}</td>
                        </tr>
                        <tr>
                            <th style="background:#f8f9fa;">Gateway</th>
                            <td>{{ $merchantLabel }}</td>
                        </tr>
                        <tr>
                            <th style="background:#f8f9fa;">Date</th>
                            <td>{{ $data->updated_at }}</td>
                        </tr>

                        {{-- Show plain-text message (PayPal success text / decline reason) --}}
                        @if($plainReturnMessage)
                        <tr>
                            <th style="background:#f8f9fa;">
                                {{ $data->status == 1 ? 'Decline Reason' : 'Gateway Message' }}
                            </th>
                            <td class="{{ $data->status == 1 ? 'text-danger' : 'text-success' }}">
                                {{ $plainReturnMessage }}
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Submitted Payment Form Data ===== --}}
    @if(!empty($paymentData) && is_array($paymentData))
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Submitted Payment Data</h4>
                </div>
                <div class="card-body">
                    {!! renderTable($paymentData) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Gateway Transaction Response ===== --}}
    @if(($data->status == 2 || $data->status == 1) && !empty($gatewayResponse))
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ $merchantLabel }} Transaction Response</h4>
                </div>
                <div class="card-body p-0">
                    {!! renderTable($gatewayResponse) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection