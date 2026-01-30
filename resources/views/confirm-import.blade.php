@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none"
        tabindex="-1">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $title !!}</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">
            <small>Review your import settings before proceeding.</small>
        </p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-10 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Step 3: Confirm Import</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="alert alert-info">
                        <i class="la la-info-circle"></i>
                        Please review the column mapping below. Once confirmed, the import will begin
                        @if($crud->getOperationSetting('queued'))
                            <strong>in the background</strong>.
                        @else
                            <strong>immediately</strong>.
                        @endif
                    </div>

                    @if($importLog->config)
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>File Column</th>
                                        <th>Database Field</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($importLog->config as $mapping)
                                        <tr>
                                            <td>
                                                <i class="la la-file-excel text-success"></i>
                                                {{ $mapping['file_column'] ?? '-' }}
                                            </td>
                                            <td>
                                                <i class="la la-database text-primary"></i>
                                                <strong>{{ $mapping['field_name'] ?? '-' }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $mapping['field_type'] ?? 'text' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" action="{{ url($crud->route . '/import/' . $importLog->id . '/confirm') }}"
                              id="importForm">
                            @csrf
                            <button type="submit" class="btn btn-success" id="btnConfirmImport">
                                <i class="la la-check-circle"></i> Confirm & Start Import
                            </button>
                        </form>
                        <a href="{{ url($crud->route . '/import/' . $importLog->id . '/map') }}" class="btn btn-warning">
                            <i class="la la-redo"></i> Remap Fields
                        </a>
                        <a href="{{ url($crud->route) }}" class="btn btn-default">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('after_scripts')
    <script>
    document.getElementById('importForm').addEventListener('submit', function() {
        var btn = document.getElementById('btnConfirmImport');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...';
    });
    </script>
    @endpush
@endsection
