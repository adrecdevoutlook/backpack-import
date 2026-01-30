@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none"
        tabindex="-1">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $title !!}</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">
            <small>Map file columns to database fields.</small>
        </p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-10 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Step 2: Map Columns</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <p class="text-muted mb-3">
                        For each database field, select the corresponding column from your file.
                        Fields marked with <span class="text-danger">*</span> are required.
                        @if($primaryKey)
                            The field <strong class="text-primary">{{ $primaryKey }}</strong> is the primary key
                            (used to update existing records).
                        @endif
                    </p>

                    <form method="POST" action="{{ url($crud->route . '/import/' . $importLog->id . '/map') }}">
                        @csrf

                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th style="width: 35%">Database Field</th>
                                        <th style="width: 15%">Type</th>
                                        <th style="width: 50%">File Column</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($crudColumns as $fieldName => $columnConfig)
                                        @php
                                            $isRequired = in_array($fieldName, $requiredColumns);
                                            $isPrimaryKey = $fieldName === $primaryKey;
                                            $type = $columnConfig['type'] ?? 'text';
                                            $label = $columnConfig['label'] ?? ucfirst($fieldName);
                                        @endphp
                                        <tr class="{{ $isPrimaryKey ? 'table-primary' : '' }}">
                                            <td>
                                                <strong>{{ $label }}</strong>
                                                <small class="text-muted d-block">{{ $fieldName }}</small>
                                                @if($isPrimaryKey)
                                                    <span class="badge bg-primary">Primary Key</span>
                                                @endif
                                                @if($isRequired)
                                                    <span class="text-danger">*</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $type }}</span>
                                            </td>
                                            <td>
                                                <select name="mappings[{{ $fieldName }}]" class="form-select form-select-sm">
                                                    <option value="__skip__">-- Skip --</option>
                                                    @foreach($headings as $heading)
                                                        <option value="{{ $heading }}"
                                                            {{ strtolower($heading) === strtolower($fieldName) ? 'selected' : '' }}>
                                                            {{ $heading }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="la la-check"></i> Confirm Mapping
                            </button>
                            <a href="{{ url($crud->route . '/import') }}" class="btn btn-default">
                                <i class="la la-arrow-left"></i> Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
