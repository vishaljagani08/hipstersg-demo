@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <h2 class="mb-4">Chunked Image Upload</h2>

        <div id="dropArea" 
            class="p-4 text-center"
            style="width:100%;height:220px;border:2px dashed #0d6efd;background:#f8f9fa;cursor:pointer;">
            <div>
                <strong>Drag & Drop Images Here</strong>
                <div class="small text-muted">Or click to browse</div>
            </div>
        </div>

        <ul id="uploadList" class="list-group mt-3"></ul>
    </div>

@endsection
@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    {{-- <script src="https://cdn.jsdelivr.net/npm/resumablejs/resumable.js"></script> --}}
    <script src="/resumable.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>
    <script>
    $(function(){
        const csrf = '{{ csrf_token() }}';
        const chunkSize = 2 * 1024 * 1024; // 2MB
        const uploadMap = {}; 
        var uploadTimer = false; 
        const r = new Resumable({
            target: "{{ route('uploads.chunk') }}",
            // query: { _token: csrf },            // other params (upload_id) will be added per-file
            chunkSize: chunkSize,
            simultaneousUploads: 3,
            testChunks: false,
            query: (file, obj)=> {
                const uploadId = uploadMap[file.uniqueIdentifier];
                return {_token: csrf, ...file.opts };
            }
        });

        // UI elements
        const drop = document.getElementById('dropArea');
        r.assignDrop(drop);
        r.assignBrowse(drop);

        // helper to create list item
        function addListItem(uid, name) {
            const li = $('<li class="list-group-item" id="file-'+uid+'">'+
                '<div class="d-flex justify-content-between align-items-center">'+
                '<div><strong>'+name+'</strong><div class="small text-muted" id="status-'+uid+'">Queued</div></div>'+
                '<div><span id="progress-'+uid+'">0%</span></div>'+
                '</div></li>');
            $('#uploadList').append(li);
        }

        function uploadAllItem() {
            if(uploadTimer == false){
                uploadTimer = setInterval(() => {
                    if(r.files.length == Object.keys(r.files).length){
                        r.upload();
                        uploadTimer = false;
                    }
                }, 1000);
            }
        }

        r.on('fileAdded', async function(file){
            let checksum = null;
            
            const initResp = await fetch("{{ route('uploads.initiate') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    original_name: file.fileName,
                    size: file.size,
                    checksum: checksum
                })
            });

            if (!initResp.ok) {
                alert('Failed to initiate upload');
                return;
            }
            const init = await initResp.json();
            const uploadId = init.upload_id;
           
            file.opts = Object.assign(file.opts.query || {}, { upload_id: uploadId });
            file = {...file, uploadId:uploadId};
            uploadMap[file.uniqueIdentifier] = uploadId;
            
            // add UI entry
            addListItem(uploadId, file.fileName);
            $('#status-' + uploadId).text('Uploading');
            uploadAllItem();
        });
        r.on('fileProgress', function(file){
            const progress = Math.round(file.progress() * 100);
            const uploadId = uploadMap[file.uniqueIdentifier];
            $('#progress-' + uploadId).text(progress + '%');
        });

        r.on('fileSuccess', async function(file, message){
            const uploadId = uploadMap[file.uniqueIdentifier];
            $('#status-'+uploadId).text('Finalizing...');

            const resp = await fetch('/uploads/' + uploadId + '/complete', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Accept':'application/json'}
            });

            const json = await resp.json();
            if (resp.ok && json.ok) {
                $('#status-'+uploadId).text('Completed');
                $('#progress-'+uploadId).text('100%');
            } else {
                $('#status-'+uploadId).text('Error: ' + (json.error || 'failed'));
            }
        });

        r.on('fileError', function(file, message){
            const uploadId = uploadMap[file.uniqueIdentifier];
            $('#status-'+uploadId).text('Upload Error');
        });
    });
    </script>
@endpush
