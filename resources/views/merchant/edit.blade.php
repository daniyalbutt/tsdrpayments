@extends('layouts.front-app')
@section('content')

<div class="container-fluid">
	<div class="row page-titles">
		<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
			<li class="breadcrumb-item"><a href="javascript:void(0)">Merchant</a></li>
			<li class="breadcrumb-item active"><a href="javascript:void(0)">Edit Merchant - {{ $data->name }}</a></li>
		</ol>
	</div>
	<div class="row">
		<div class="col-lg-8 col-12">
		    <div class="card">
		        <div class="card-header">
					<h4 class="card-title">Edit Merchant Form - {{ $data->name }}</h4>
		        </div>
		        <!-- /.box-header -->
				<div class="card-body">
					<div class="basic-form">
						<form class="form" method="post" action="{{ route('merchant.update', $data->id) }}" enctype="multipart/form-data">
							@csrf
							@method('PUT')
							<div class="box-body">
								@if($errors->any())
									{!! implode('', $errors->all('<div class="alert alert-danger">:message</div>')) !!}
								@endif
								@if(session()->has('success'))
								<div class="alert alert-success">
									{{ session()->get('success') }}
								</div>
								@endif
								<div class="row">
									<div class="col-md-4">
										<div class="form-group mb-3">
											<label class="form-label">Name</label>
											<input type="text" class="form-control" name="name" value="{{ $data->name }}" required value="{{ old('name') }}">
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group mb-3">
											<label class="form-label">Type</label>
											<select name="type" id="type" class="form-control" required>
												<option value="">Select Merchant</option>
												<option value="0" {{ $data->merchant == 0 ? 'selected' : '' }}>STRIPE</option>
												<option value="4" {{ $data->merchant == 4 ? 'selected' : '' }}>AUTHORIZE</option>
												<option value="5" {{ $data->merchant == 5 ? 'selected' : '' }}>PAYPAL</option>
												<option value="6" {{ $data->merchant == 6 ? 'selected' : '' }}>SQUARE</option>
												<option value="7" {{ $data->merchant == 7 ? 'selected' : '' }}>PAYKINGS / TG</option>
												<option value="8" {{ $data->merchant == 8 ? 'selected' : '' }}>NOMOD</option>
											</select>
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group mb-3">
											<label class="form-label">Status</label>
											<select name="status" id="status" class="form-control">
												<option value="0" {{ $data->status == 0 ? 'selected' : '' }}>Active</option>
												<option value="1" {{ $data->status == 1 ? 'selected' : '' }}>Deactive</option>
											</select>
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group mb-3">
											<label class="form-label">Publishable key / Login ID / Client ID / Application ID / Security Key / API KEY</label>
											<input type="text" class="form-control" name="public_key" required value="{{ old('public_key', $data->public_key) }}">
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group mb-3">
											<label class="form-label">Secret key / Transaction Key / Client Secret / Access Token</label>
											<input type="text" class="form-control" name="private_key" value="{{ old('private_key', $data->private_key) }}">
										</div>
									</div>
									<div class="col-md-12 {{ $data->merchant == 6 ? '' : 'd-none' }}" id="square_location_div">
										<div class="form-group mb-3">
											<label class="form-label">Square Location ID</label>
											<input 
												type="text" 
												class="form-control" 
												name="square_location_id" 
												value="{{ old('square_location_id', $data->square_location_id ?? '') }}"
											>
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group mb-3">
											<label class="form-label">Production/Sandbox</label>
											<select name="sandbox" id="sandbox" class="form-control">
												<option value="0" {{ $data->sandbox == 0 ? 'selected' : '' }}>Production</option>
												<option value="1" {{ $data->sandbox == 1 ? 'selected' : '' }}>Sandbox</option>
											</select>
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group mb-3">
											<button type="button" id="checkCredentialsBtn" class="btn btn-info">
												<i class="ti-shield"></i> Check Credentials
											</button>
											<span id="checkResult" style="margin-left: 10px;"></span>
										</div>
									</div>
								</div>
							</div>
							<!-- /.box-body -->
							<div class="box-footer">
								<button type="submit" class="btn btn-primary">
								<i class="ti-save-alt"></i> Save
								</button>
							</div>
						</form>
					</div>
				</div>
		    </div>
		    <!-- /.box -->			
		</div>
		<div class="col-lg-4">
			<div class="card">
				<div class="card-header">
					<h4 class="card-title">Credentials For Merchant</h4>
				</div>
				<!-- /.box-header -->
				<div class="card-body">
					@include('merchant.merchant-details')
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('scripts')
<script>
    function toggleSquareField() {
        let type = document.getElementById('type').value;

        if (type == "6") { // SQUARE
            document.getElementById('square_location_div').classList.remove('d-none');
        } else {
            document.getElementById('square_location_div').classList.add('d-none');
        }
    }

    document.getElementById('type').addEventListener('change', toggleSquareField);

    // Run on page load
    window.onload = toggleSquareField;

    // Check Credentials Functionality
    document.getElementById('checkCredentialsBtn').addEventListener('click', function() {
        let type = document.getElementById('type').value;
        let publicKey = document.querySelector('input[name="public_key"]').value;
        let privateKey = document.querySelector('input[name="private_key"]').value;
        let sandbox = document.getElementById('sandbox').value;
        let squareLocationId = document.querySelector('input[name="square_location_id"]')?.value || '';
        
        // Show loading state
        let btn = this;
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ti-reload"></i> Checking...';
        btn.disabled = true;
        
        // Make AJAX request
        $.ajax({
            url: '{{ route("merchant.check", $data->id) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                type: type,
                public_key: publicKey,
                private_key: privateKey,
                sandbox: sandbox,
                square_location_id: squareLocationId
            },
            success: function(response) {
                if (response.success) {
                    showResult(response.message, 'success');
                } else {
                    showResult(response.message, 'danger');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error checking credentials';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showResult(errorMsg, 'danger');
            },
            complete: function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    });
    
    function showResult(message, type) {
        let resultSpan = document.getElementById('checkResult');
        resultSpan.innerHTML = '<span class="badge badge-' + type + '" style="padding: 8px 12px;">' + message + '</span>';
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            if (resultSpan.innerHTML.includes(message)) {
                resultSpan.innerHTML = '';
            }
        }, 5000);
    }
</script>
@endpush