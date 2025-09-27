@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">Bulk CSV Import</h2>

    <form id="csvImportForm" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label">Upload CSV File</label>
            <input type="file" name="file" id="csvFile" class="form-control" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
        <span id="importSpinner" class="ms-3" style="display:none;">Processing...</span>
    </form>

    <div id="importResult" class="mt-4" style="display:none;">
        <h5>Import Summary</h5>
        <ul class="list-group">
            <li class="list-group-item">Total Rows: <strong id="total">0</strong></li>
            <li class="list-group-item">Imported: <strong id="imported">0</strong></li>
            <li class="list-group-item">Updated: <strong id="updated">0</strong></li>
            <li class="list-group-item">Invalid: <strong id="invalid">0</strong></li>
            <li class="list-group-item">Duplicates: <strong id="duplicates">0</strong></li>
        </ul>

        <div id="invalidFileLink" class="mt-3"></div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function(){
    $('#csvImportForm').submit(function(e){
        e.preventDefault();
        const fd = new FormData(this);
        $('#importSpinner').show();
        $('#importResult').hide();

        $.ajax({
            url: "{{ route('imports.handle') }}",
            method: "POST",
            data: fd,
            processData: false,
            contentType: false,
            success: function(res){
                $('#importSpinner').hide();
                $('#total').text(res.total);
                $('#imported').text(res.imported);
                $('#updated').text(res.updated);
                $('#invalid').text(res.invalid);
                $('#duplicates').text(res.duplicates);
                if (res.invalid_file) {
                    $('#invalidFileLink').html('<a href="' + res.invalid_file + '" target="_blank">Download invalid rows</a>');
                }
                $('#importResult').show();
            },
            error: function(xhr){
                $('#importSpinner').hide();
                alert("Error processing CSV import: " + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    });
});
</script>

@endpush
