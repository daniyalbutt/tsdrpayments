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
        function safeDecode($value) {
            if (is_null($value)) return null;
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return $decoded;
        }

        function renderTable($data) {
            if (!is_array($data) || empty($data)) return '<p class="text-muted">No data available.</p>';
            $output = '<table class="table table-bordered table-sm mb-0">';
            foreach ($data as $key => $value) {
                $label = ucwords(str_replace(['_', '-'], ' ', $key));
                $output .= '<tr>';
                $output .= '<th style="width:35%;background:#f8f9fa;">' . e($label) . '</th>';
                if (is_array($value)) {
                    $output .= '<td>' . renderTable($value) . '</td>';
                } else {
                    $output .= '<td>' . (isset($value) && $value !== '' ? e($value) : '<span class="text-muted">N/A</span>') . '</td>';
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

        $merchantLabels = [
            0 => 'Stripe',
            1 => 'Manual',
            2 => 'Square',
            5 => 'Authorize.net',
            6 => 'PayKings',
            7 => 'PayKings',
        ];
        $merchantLabel = $merchantLabels[$data->merchant] ?? 'Unknown Gateway';

        $statusMap = [
            0 => ['label' => 'Pending',  'class' => 'warning'],
            1 => ['label' => 'Declined', 'class' => 'danger'],
            2 => ['label' => 'Paid',     'class' => 'success'],
        ];
        $statusInfo = $statusMap[$data->status] ?? ['label' => 'Unknown', 'class' => 'secondary'];
    @endphp

    {{-- Summary Card --}}
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
                            <td>{{ $data->package }}</td>
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
                        @if($data->status == 1 && !is_array($returnData))
                        <tr>
                            <th style="background:#f8f9fa;">Decline Reason</th>
                            <td class="text-danger">{{ $data->return_response }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment Form Data --}}
    @if(!empty($paymentData))
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

    {{-- Gateway Transaction Response --}}
    @if($data->status == 2 || $data->status == 1)
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ $merchantLabel }} Transaction Response</h4>
                </div>
                <div class="card-body">
                    @php
                        if (in_array($data->merchant, [6, 7])) {
                            $gatewayResponse = is_array($returnData) ? $returnData : $squareData;
                        } elseif ($data->merchant == 5) {
                            $gatewayResponse = $authorizeData;
                        } else {
                            $gatewayResponse = $returnData;
                        }
                    @endphp

                    @if(is_array($gatewayResponse) && !empty($gatewayResponse))
                        {!! renderTable($gatewayResponse) !!}
                    @else
                        <div class="p-3 text-muted">No gateway response data available.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection