@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none"
        tabindex="-1">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $title !!}</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">
            <small>Upload a CSV or Excel file to import data.</small>
        </p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Step 1: Select File</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ url($crud->route . '/import') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="import_file" class="form-label">Choose File</label>
                            <input type="file" class="form-control @error('import_file') is-invalid @enderror"
                                   id="import_file" name="import_file"
                                   accept=".csv,.xls,.xlsx,.txt">
                            @error('import_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Supported formats: CSV, XLS, XLSX</div>
                        </div>

                        @if(!empty($exampleFileUrl))
                            <div class="mb-3">
                                <a href="{{ $exampleFileUrl }}" class="btn btn-sm btn-outline-info" download>
                                    <i class="la la-download"></i> Download Template File
                                </a>
                            </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="la la-upload"></i> Upload & Continue
                            </button>
                            <a href="{{ url($crud->route) }}" class="btn btn-default">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
