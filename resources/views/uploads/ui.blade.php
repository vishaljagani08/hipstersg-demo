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
        <div class="mt-3">
            <h5>Upload Status</h5>
            <p>Total: <span id="total">0</span> Success: <span id="success">0</span> Error: <span id="error">0</span></p>
            <p class="time-diff d-none"></p>
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
    const csrf = '{{ csrf_token() }}';
    const chunkSize = 2 * 1024 * 1024; // 2MB
    const uploadMap = {}; 
    var uploadComplate = {
        error:0,
        success:0,
        total:0,
    }; 
    var uploadTimer = false;
    var initiateTimer = false;
    var dateDiff = {
        start: null,
        end: null
    };
    function setComplate(){
        $('#total').text(uploadComplate.total);
        $('#success').text(uploadComplate.success);
        $('#error').text(uploadComplate.error);
        if(uploadComplate.total == (uploadComplate.success + uploadComplate.error)){
            dateDiff.end = new Date();
            const differenceInMilliseconds = dateDiff.end.getTime() - dateDiff.start.getTime();
            const differenceInSeconds = Math.round(differenceInMilliseconds / 1000);

            console.log("All complate",new Date());
            console.log("Total time taken (ms): ", differenceInMilliseconds);
            console.log("Total time taken (Secound): ", differenceInSeconds);
            $(".time-diff").removeClass('d-none').text("Total time taken (Secound): " + differenceInSeconds + " sec");
            //need in secounds

        }
    }
    
    $(function(){
         
        const r = new Resumable({
            target: "{{ route('uploads.chunk') }}",
            chunkSize: chunkSize,
            simultaneousUploads: 3,
            testChunks: false,
            query: (file, obj)=> {
                const uploadId = uploadMap[file.uniqueIdentifier].uploadId;
                
                file.opts = Object.assign(file.opts.query || {}, { upload_id: uploadId });
                file = {...file, uploadId:uploadId};

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
                    if(r.files.length == Object.keys(uploadMap).length){
                        r.upload();
                        clearInterval(uploadTimer);
                        uploadTimer = false;
                    }
                }, 1000);
            }
        }

        r.on('fileAdded', async function(file){
            if(dateDiff.start == null){
                console.log("start Apis",new Date());
                dateDiff.start = new Date();
            }
            uploadMap[file.uniqueIdentifier] = {
                original_name: file.fileName,
                size: file.size,
                checksum: null,
                uploadId:null
            };

            if(initiateTimer == false){
                initiateTimer = setInterval(async () => {
                    if(r.files.length == Object.keys(uploadMap).length){
                        clearInterval(initiateTimer);
                        initiateTimer = false;
                        let checksum = null;

                        let body = [];
                        Object.values(uploadMap).forEach((val, index) => {
                            body.push(val);
                        });
            
                        const initResp = await fetch("{{ route('uploads.initiate') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({"images":body})
                        });

                        if (!initResp.ok) {
                            alert('Failed to initiate upload');
                            return;
                        }
                        const init = await initResp.json();
                        const uploadIdDetails = init.upload_details;
                        
                        for (const [uploadId, fileName] of Object.entries(init.upload_details)) {
                            for (let key in uploadMap) {
                                if (uploadMap[key].original_name === fileName) {
                                    uploadMap[key].uploadId = uploadId; // update value
                                    uploadComplate.total++;
                                    addListItem(uploadId, file.fileName);
                                    $('#status-' + uploadId).text('Uploading');
                                }
                            }
                        }
                        
                        // uploadAllItem();
                        r.upload(); // this function will start upload but all file upload will be handled by simultaneousUploads


                        setComplate();

                    }
                }, 1000);
            }

            
        });
        r.on('fileProgress', function(file){
            const progress = Math.round(file.progress() * 100);
            const uploadId = uploadMap[file.uniqueIdentifier].uploadId;
            $('#progress-' + uploadId).text(progress + '%');
        });

        r.on('fileSuccess', async function(file, message){
            const uploadId = uploadMap[file.uniqueIdentifier].uploadId;
            $('#status-'+uploadId).text('Finalizing...');

            const resp = await fetch('/uploads/' + uploadId + '/complete', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Accept':'application/json'}
            });

            const json = await resp.json();
            if (resp.ok && json.ok) {
                $('#status-'+uploadId).text('Completed');
                uploadComplate.success++;
                $('#progress-'+uploadId).text('100%');
            } else {
                uploadComplate.error++;
                $('#status-'+uploadId).text('Error: ' + (json.error || 'failed'));
            }
            setComplate();
        });

        r.on('fileError', function(file, message){
            const uploadId = uploadMap[file.uniqueIdentifier].uploadId;
            uploadComplate.error++;
            setComplate();
            $('#status-'+uploadId).text('Upload Error');
        });
    });
    </script>
@endpush
