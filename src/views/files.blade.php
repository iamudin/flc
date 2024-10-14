<button class="btn btn-primary btn-sm mb-2" onclick="$('.upload').click()"> <i class="fa fa-upload"></i> Baru </button>
<form action="{{ route('media.upload') }}" method="POST" class="mediaupload" enctype="multipart/form-data">
@csrf
<input accept="{{ allow_mime() }}" type="file" onchange="if(confirm('Upload Berkas ?')){$('.mediaupload').submit()}" name="media" class="upload d-none">
</form>
<div class="table-responsive">
<table class="table-striped table table-bordered table-hover  bg-white">
    <thead>
        <tr>
            <th width="20px">No</th>
            <th>Time</th>
            <th>File Name</th>
            <th>Size</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $key=>$item)
            <tr>
                <td align="center">{{ $key+1 }}</td>
                <td>{{ $item->created_at->format('d-m-y H:i T') }}</td>
                <td><b class="text-primary">{{ $item->file_name}}</b><br><small class="text-muted"><i class="fa fa-user"></i> {{ $item->user?->name }}</small></td>
                <td>{{ size_as_kb($item->file_size)}}</td>

                <td width="70px">
                    <div class="btn-group">
                    <button data-copy="{{ route('stream',$item->file_name) }}" class="copy btn btn-sm btn-warning fa fa-link "></button>
                    <a href="{{ route('stream',$item->file_name) }}" class="btn btn-sm btn-primary fa fa-eye"></a>
                    <button onclick="lw_media_destroy('{{ $item->file_name }}')" href="" class="btn btn-sm btn-danger fa fa-trash-o"></button>
                </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

</div>
{{ $data->links('pagination::bootstrap-5') }}

<script>
    function lw_media_destroy(source){
    if(confirm('Hapus ? ')){
    $.post( "{{ route('media.destroy') }}", { _token:"{{ csrf_token() }}",media:source  })
.done(function( data ) {
    location.reload();
});
}
}
</script>
