@if ($crud->hasAccess('import'))
    <a href="{{ url($crud->route . '/import') }}" class="btn btn-secondary" data-style="zoom-in">
        <span class="ladda-label"><i class="la la-file-upload"></i> Import {{ $crud->entity_name_plural }}</span>
    </a>
@endif
